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
 * Event observers.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed;

defined('MOODLE_INTERNAL') || die();

/**
 * Queues submitted work. The check itself runs later, in the scheduled task.
 */
class observer {

    /**
     * A student saved files or online text in an assignment.
     *
     * @param \core\event\base $event
     */
    public static function assessable_uploaded(\core\event\base $event): void {
        global $DB;

        $cmid = (int) $event->contextinstanceid;
        if (!checker::cm_enabled($cmid)) {
            return;
        }
        $submission = $DB->get_record('assign_submission', ['id' => $event->objectid]);
        if (!$submission) {
            return;
        }
        $now = time();
        $base = [
            'cmid' => $cmid,
            'submissionid' => $submission->id,
            'userid' => (int) $submission->userid,
            'submitter' => (int) $event->userid,
        ];
        $other = $event->other;

        if ($event->component === 'assignsubmission_onlinetext') {
            $content = trim((string) ($other['content'] ?? ''));
            $existing = $DB->get_records('plagiarism_sduembed_sub',
                ['submissionid' => $submission->id, 'itemtype' => 'onlinetext'], 'id ASC');
            if ($content === '') {
                $DB->delete_records('plagiarism_sduembed_sub', ['submissionid' => $submission->id, 'itemtype' => 'onlinetext']);
                return;
            }
            // The same hash is computed in get_links() from the text Moodle shows beside the submission.
            $hash = sha1($content);
            self::upsert(reset($existing) ?: null, $base + ['itemtype' => 'onlinetext', 'identifier' => $hash,
                'contenthash' => $hash], $now);
            return;
        }

        // Files.
        $hashes = $other['pathnamehashes'] ?? [];
        $existing = [];
        foreach ($DB->get_records('plagiarism_sduembed_sub', ['submissionid' => $submission->id, 'itemtype' => 'file']) as $r) {
            if (in_array($r->identifier, $hashes)) {
                $existing[$r->identifier] = $r;
            } else {
                $DB->delete_records('plagiarism_sduembed_sub', ['id' => $r->id]);      // The file was removed.
            }
        }
        $fs = get_file_storage();
        foreach ($hashes as $pathnamehash) {
            $file = $fs->get_file_by_hash($pathnamehash);
            if (!$file || $file->is_directory()) {
                continue;
            }
            self::upsert($existing[$pathnamehash] ?? null, $base + ['itemtype' => 'file', 'identifier' => $pathnamehash,
                'contenthash' => $file->get_contenthash()], $now);
        }
    }

    /**
     * Insert a queue record, or queue an existing one again if its content changed.
     *
     * @param \stdClass|null $existing
     * @param array $fields
     * @param int $now
     */
    protected static function upsert(?\stdClass $existing, array $fields, int $now): void {
        global $DB;
        $queued = ['status' => checker::QUEUED, 'attempts' => 0, 'timenext' => 0, 'errormsg' => null,
            'timequeued' => $now, 'timemodified' => $now];
        if (!$existing) {
            $DB->insert_record('plagiarism_sduembed_sub', (object) ($fields + $queued + ['timechecked' => 0]));
            return;
        }
        if ($existing->contenthash === $fields['contenthash'] && $existing->status != checker::FAILED) {
            return;         // Saved again without changes.
        }
        $DB->update_record('plagiarism_sduembed_sub',
            (object) (['id' => $existing->id, 'score' => null, 'report' => null] + $fields + $queued));
    }

    /**
     * An activity was deleted: remove its results.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->delete_records('plagiarism_sduembed_sub', ['cmid' => $event->objectid]);
        $DB->delete_records('plagiarism_sduembed_cm', ['cmid' => $event->objectid]);
    }
}
