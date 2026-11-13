<?php
// This file is part of the SDU NLP plagiarism plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Queue handling and the check of one submission.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed;

use plagiarism_sduembed\local\text;

defined('MOODLE_INTERNAL') || die();

/**
 * Finds the texts to compare, calls the API and stores the result.
 */
class checker {

    /** Waiting to be checked. */
    const QUEUED = 0;
    /** Checked; score and report are stored. */
    const DONE = 1;
    /** The file type cannot be read. */
    const UNSUPPORTED = 2;
    /** No sentence of three or more words. */
    const TOOSHORT = 3;
    /** No other submission to compare with yet. */
    const NOSOURCES = 4;
    /** Gave up after repeated errors. */
    const FAILED = 5;

    /** @var int Attempts before a record is marked as failed. */
    const MAX_ATTEMPTS = 5;

    /** @var api_client */
    protected $api;

    /** @var array Text of whole submissions read in this run, by assign_submission.id. */
    protected $cache = [];

    /**
     * Constructor.
     *
     * @param api_client|null $api
     */
    public function __construct(?api_client $api = null) {
        $this->api = $api ?? new api_client();
    }

    /**
     * Is the check switched on for this activity?
     *
     * @param int $cmid
     * @return bool
     */
    public static function cm_enabled(int $cmid): bool {
        global $DB;
        static $cache = [];
        if (PHPUNIT_TEST || !isset($cache[$cmid])) {
            $cache[$cmid] = get_config('plagiarism_sduembed', 'enabled')
                && $DB->record_exists('plagiarism_sduembed_cm', ['cmid' => $cmid, 'enabled' => 1]);
        }
        return $cache[$cmid];
    }

    /**
     * Put records back in the queue when classmates have submitted since they were last checked.
     *
     * The API keeps no archive, so a submission can only be compared with the work that existed at the time.
     * Without this step the first student to submit would never be compared with anyone.
     *
     * @return int number of records queued again
     */
    public function requeue_stale(): int {
        global $DB;
        $hours = (float) get_config('plagiarism_sduembed', 'recheckhours');
        if ($hours <= 0) {
            return 0;
        }
        $sql = "SELECT r.id
                  FROM {plagiarism_sduembed_sub} r
                 WHERE r.status IN (:done, :nosources)
                       AND r.timechecked < :cutoff
                       AND EXISTS (SELECT 1
                                     FROM {plagiarism_sduembed_sub} p
                                    WHERE p.cmid = r.cmid
                                          AND p.submissionid <> r.submissionid
                                          AND p.timequeued > r.timechecked)";
        $ids = $DB->get_fieldset_sql($sql, ['done' => self::DONE, 'nosources' => self::NOSOURCES,
            'cutoff' => time() - (int) ($hours * HOURSECS)]);
        foreach (array_chunk($ids, 500) as $chunk) {
            list($insql, $params) = $DB->get_in_or_equal($chunk);
            // The field timequeued is left alone on purpose, otherwise records would re-queue each other for ever.
            $DB->execute("UPDATE {plagiarism_sduembed_sub}
                             SET status = ?, attempts = 0, timenext = 0
                           WHERE id $insql", array_merge([self::QUEUED], $params));
        }
        return count($ids);
    }

    /**
     * Records that are due, oldest first.
     *
     * @param int $limit
     * @return \stdClass[]
     */
    public function due(int $limit): array {
        global $DB;
        return $DB->get_records_select('plagiarism_sduembed_sub', 'status = ? AND timenext <= ?',
            [self::QUEUED, time()], 'timemodified ASC, id ASC', '*', 0, $limit);
    }

    /**
     * Check one record and save the outcome.
     *
     * @param \stdClass $record row of plagiarism_sduembed_sub
     * @return int the new status
     * @throws api_exception only for a configuration problem, so that the caller can stop the run
     */
    public function process(\stdClass $record): int {
        global $DB;

        $cm = get_coursemodule_from_id('assign', $record->cmid);
        $submission = $DB->get_record('assign_submission', ['id' => $record->submissionid]);
        if (!$cm || !$submission) {
            $DB->delete_records('plagiarism_sduembed_sub', ['id' => $record->id]);
            return self::FAILED;
        }
        if (!self::cm_enabled($cm->id)) {
            // Switched off after the work was queued: keep the record, try again if it is switched back on.
            return $this->save($record, self::QUEUED, ['timenext' => time() + DAYSECS]);
        }

        $text = $this->item_text($record);
        if ($text === null) {
            return $this->save($record, self::UNSUPPORTED);
        }
        if (!text::has_sentence($text)) {
            return $this->save($record, self::TOOSHORT);
        }

        $sources = $this->sources($cm, $submission);
        if (!$sources) {
            return $this->save($record, self::NOSOURCES, ['score' => null, 'report' => null]);
        }

        $threshold = trim((string) get_config('plagiarism_sduembed', 'threshold'));
        $threshold = ($threshold === '') ? null : min(1.0, max(0.0, (float) $threshold));
        try {
            $parts = [];
            foreach (text::split($text) as $part) {
                if (!text::has_sentence($part)) {
                    continue;
                }
                $responses = [];
                foreach (text::batch($sources) as $batch) {
                    $responses[] = $this->api->compare($part, $batch, $threshold);
                }
                $parts[] = $responses;
            }
        } catch (api_exception $e) {
            if ($e->is_config_problem()) {
                throw $e;
            }
            $attempts = $record->attempts + 1;
            if (!$e->is_retryable() || $attempts >= self::MAX_ATTEMPTS) {
                return $this->save($record, self::FAILED, ['attempts' => $attempts,
                    'errormsg' => \core_text::substr($e->getMessage(), 0, 250)]);
            }
            // Wait longer after each failure: 10 minutes, 20, 40, 80.
            return $this->save($record, self::QUEUED, ['attempts' => $attempts,
                'timenext' => time() + 600 * (2 ** ($attempts - 1)),
                'errormsg' => \core_text::substr($e->getMessage(), 0, 250)]);
        }

        $report = text::merge($parts);
        $report['compared'] = count($sources);
        foreach ($report['sources'] as &$src) {
            foreach ($src['matches'] as &$m) {
                $m[0] = \core_text::substr($m[0], 0, 600);
                $m[1] = \core_text::substr($m[1], 0, 600);
            }
        }
        unset($src, $m);
        return $this->save($record, self::DONE, [
            'score' => $report['score'],
            'report' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'errormsg' => null,
            'timechecked' => time(),
        ]);
    }

    /**
     * Update a record.
     *
     * @param \stdClass $record
     * @param int $status
     * @param array $fields
     * @return int the status
     */
    protected function save(\stdClass $record, int $status, array $fields = []): int {
        global $DB;
        $update = (object) (['id' => $record->id, 'status' => $status, 'timemodified' => time()] + $fields);
        if ($status !== self::QUEUED && !isset($fields['timechecked'])) {
            $update->timechecked = time();
        }
        $DB->update_record('plagiarism_sduembed_sub', $update);
        return $status;
    }

    /**
     * Text of the item a record stands for.
     *
     * @param \stdClass $record
     * @return string|null null if it cannot be read
     */
    protected function item_text(\stdClass $record): ?string {
        global $DB;
        if ($record->itemtype === 'onlinetext') {
            $html = $DB->get_field('assignsubmission_onlinetext', 'onlinetext', ['submission' => $record->submissionid]);
            return ($html === false) ? null : extractor::from_html((string) $html);
        }
        $file = get_file_storage()->get_file_by_hash($record->identifier);
        if (!$file || $file->is_directory()) {
            return null;
        }
        return extractor::from_file($file);
    }

    /**
     * All readable text of one submission: its online text and its files.
     *
     * @param \context_module $context
     * @param int $submissionid
     * @return string
     */
    protected function submission_text(\context_module $context, int $submissionid): string {
        global $DB;
        if (!isset($this->cache[$submissionid])) {
            if (count($this->cache) > 2000) {
                $this->cache = [];
            }
            $chunks = [];
            $html = $DB->get_field('assignsubmission_onlinetext', 'onlinetext', ['submission' => $submissionid]);
            if ($html !== false && trim((string) $html) !== '') {
                $chunks[] = extractor::from_html((string) $html);
            }
            $files = get_file_storage()->get_area_files($context->id, 'assignsubmission_file', 'submission_files',
                $submissionid, 'id', false);
            foreach ($files as $file) {
                $t = extractor::from_file($file);
                if ($t !== null && $t !== '') {
                    $chunks[] = $t;
                }
            }
            $all = implode("\n\n", $chunks);
            $this->cache[$submissionid] = \core_text::substr($all, 0, text::MAX_CHARS);
        }
        return $this->cache[$submissionid];
    }

    /**
     * The texts to compare with: the latest work of every other student or group in the same assignment.
     *
     * Ids are submission ids. No names or user ids are sent to the API.
     *
     * @param \stdClass $cm
     * @param \stdClass $submission the submission being checked
     * @return array list of ['id' => string, 'text' => string]
     */
    protected function sources(\stdClass $cm, \stdClass $submission): array {
        global $DB;
        $max = (int) get_config('plagiarism_sduembed', 'maxsources') ?: 200;
        $context = \context_module::instance($cm->id);
        $sql = "SELECT s.id
                  FROM {assign_submission} s
                 WHERE s.assignment = :assignment
                       AND s.latest = 1
                       AND s.id <> :me
                       AND s.status IN ('submitted', 'draft')
                       AND (s.userid <> :userid OR s.userid = 0)
              ORDER BY s.timemodified DESC, s.id DESC";
        $ids = array_keys($DB->get_records_sql($sql, ['assignment' => $cm->instance, 'me' => $submission->id,
            'userid' => $submission->userid], 0, $max * 2));
        $sources = [];
        foreach ($ids as $id) {
            $t = $this->submission_text($context, (int) $id);
            if (text::has_sentence($t)) {
                $sources[] = ['id' => 's' . $id, 'text' => $t];
                if (count($sources) >= $max) {
                    break;
                }
            }
        }
        return $sources;
    }
}
