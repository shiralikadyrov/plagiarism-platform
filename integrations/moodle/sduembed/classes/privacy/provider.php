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
 * Privacy API implementation.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * The plugin stores a score and matched sentences per submission, and sends submission text to the API server.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_plagiarism\privacy\plagiarism_provider,
        \core_plagiarism\privacy\plagiarism_user_provider {

    /**
     * Describe the data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('plagiarism_sduembed_sub', [
            'userid' => 'privacy:metadata:sub:userid',
            'submitter' => 'privacy:metadata:sub:submitter',
            'score' => 'privacy:metadata:sub:score',
            'report' => 'privacy:metadata:sub:report',
            'timechecked' => 'privacy:metadata:sub:timechecked',
        ], 'privacy:metadata:sub');
        $collection->add_external_location_link('plagiarism_sduembed_api', [
            'submissiontext' => 'privacy:metadata:api:submissiontext',
            'submissionid' => 'privacy:metadata:api:submissionid',
        ], 'privacy:metadata:api');
        return $collection;
    }

    /**
     * Export the results of one user in one activity.
     *
     * @param int $userid
     * @param \context $context
     * @param array $subcontext
     * @param array $linkarray
     */
    public static function export_plagiarism_user_data(int $userid, \context $context, array $subcontext, array $linkarray) {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE || !$userid) {
            return;
        }
        $records = $DB->get_records_select('plagiarism_sduembed_sub', 'cmid = ? AND (userid = ? OR submitter = ?)',
            [$context->instanceid, $userid, $userid], 'id ASC');
        $out = [];
        foreach ($records as $r) {
            $report = $r->report ? json_decode($r->report, true) : null;
            $sentences = [];
            // Only the user's own flagged sentences. The matched text belongs to other students.
            foreach ($report['sources'] ?? [] as $src) {
                foreach ($src['matches'] ?? [] as $m) {
                    $sentences[$m[0]] = true;
                }
            }
            $out[] = (object) [
                'itemtype' => $r->itemtype,
                'status' => $r->status,
                'score' => $r->score,
                'flagged_sentences' => array_keys($sentences),
                'timechecked' => $r->timechecked ? \core_privacy\local\request\transform::datetime($r->timechecked) : null,
            ];
        }
        if ($out) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, [get_string('privacy:path', 'plagiarism_sduembed')]),
                (object) ['results' => $out]);
        }
    }

    /**
     * Delete everything stored for an activity.
     *
     * @param \context $context
     */
    public static function delete_plagiarism_for_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $DB->delete_records('plagiarism_sduembed_sub', ['cmid' => $context->instanceid]);
        }
    }

    /**
     * Delete what is stored for one user in an activity.
     *
     * @param int $userid
     * @param \context $context
     */
    public static function delete_plagiarism_for_user(int $userid, \context $context) {
        self::delete_plagiarism_for_users([$userid], $context);
    }

    /**
     * Delete what is stored for several users in an activity.
     *
     * @param array $userids
     * @param \context $context
     */
    public static function delete_plagiarism_for_users(array $userids, \context $context) {
        global $DB;
        $userids = array_filter(array_map('intval', $userids));
        if ($context->contextlevel != CONTEXT_MODULE || !$userids) {
            return;
        }
        list($in1, $p1) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        list($in2, $p2) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 's');
        $select = "cmid = :cmid AND (userid $in1 OR submitter $in2)";
        $params = ['cmid' => $context->instanceid] + $p1 + $p2;
        $gone = [];
        foreach ($DB->get_fieldset_select('plagiarism_sduembed_sub', 'submissionid', $select, $params) as $sid) {
            $gone['s' . $sid] = true;
        }
        $DB->delete_records_select('plagiarism_sduembed_sub', $select, $params);

        // Classmates' reports quote sentences of the deleted work as the matched source. Remove those too.
        if (!$gone) {
            return;
        }
        $others = $DB->get_recordset_select('plagiarism_sduembed_sub', 'cmid = ? AND report IS NOT NULL',
            [$context->instanceid], '', 'id, report');
        foreach ($others as $r) {
            $report = json_decode($r->report, true);
            if (!is_array($report) || empty($report['sources'])) {
                continue;
            }
            $kept = array_values(array_filter($report['sources'], function($src) use ($gone) {
                return !isset($gone[$src['id']]);
            }));
            if (count($kept) != count($report['sources'])) {
                $report['sources'] = $kept;
                $DB->set_field('plagiarism_sduembed_sub', 'report',
                    json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ['id' => $r->id]);
            }
        }
        $others->close();
    }
}
