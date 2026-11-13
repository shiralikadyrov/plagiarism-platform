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
 * Plugin class and callbacks used by Moodle.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');

use plagiarism_sduembed\checker;

/**
 * Shows the result beside each submission.
 */
class plagiarism_plugin_sduembed extends plagiarism_plugin {

    /**
     * May the current user see the result of this record?
     *
     * @param stdClass|null $record
     * @param context $context
     * @return bool
     */
    public static function can_view(?stdClass $record, context $context): bool {
        global $USER;
        if (has_capability('plagiarism/sduembed:viewreport', $context)) {
            return true;
        }
        return $record && get_config('plagiarism_sduembed', 'studentview')
            && ($record->userid == $USER->id || $record->submitter == $USER->id);
    }

    /**
     * Result line for one submitted file or online text.
     *
     * @param array $linkarray keys: cmid, userid, and either file (stored_file) or content (string)
     * @return string
     */
    public function get_links($linkarray) {
        global $DB;

        if (empty($linkarray['cmid']) || !checker::cm_enabled((int) $linkarray['cmid'])) {
            return '';
        }
        $cmid = (int) $linkarray['cmid'];
        $record = null;
        if (!empty($linkarray['file']) && $linkarray['file'] instanceof stored_file) {
            $records = $DB->get_records('plagiarism_sduembed_sub', ['cmid' => $cmid, 'itemtype' => 'file',
                'identifier' => $linkarray['file']->get_pathnamehash()], 'id DESC', '*', 0, 1);
            $record = reset($records) ?: null;
        } else if (isset($linkarray['content']) && trim($linkarray['content']) !== '') {
            $records = $DB->get_records('plagiarism_sduembed_sub', ['cmid' => $cmid, 'itemtype' => 'onlinetext',
                'identifier' => sha1(trim($linkarray['content']))], 'id DESC');
            foreach ($records as $r) {
                if (!isset($linkarray['userid']) || $r->userid == $linkarray['userid']) {
                    $record = $r;
                    break;
                }
            }
        } else {
            return '';
        }

        $context = context_module::instance($cmid);
        if (!self::can_view($record, $context)) {
            return '';
        }
        if (!$record) {
            $text = get_string('statusnotqueued', 'plagiarism_sduembed');
        } else if ($record->status == checker::DONE) {
            $a = (object) ['percent' => format_float(100 * $record->score, 0)];
            $text = html_writer::link(new moodle_url('/plagiarism/sduembed/report.php', ['id' => $record->id]),
                get_string('scorelink', 'plagiarism_sduembed', $a),
                ['title' => get_string('scorelinktitle', 'plagiarism_sduembed')]);
        } else {
            $keys = [checker::QUEUED => 'statusqueued', checker::UNSUPPORTED => 'statusunsupported',
                checker::TOOSHORT => 'statustooshort', checker::NOSOURCES => 'statusnosources',
                checker::FAILED => 'statusfailed'];
            $text = get_string($keys[$record->status] ?? 'statusqueued', 'plagiarism_sduembed');
        }
        return html_writer::div(get_string('linkprefix', 'plagiarism_sduembed') . ' ' . $text, 'plagiarism_sduembed_result');
    }

    /**
     * Notice shown to students on the submission page.
     *
     * @param int $cmid
     * @return string
     */
    public function print_disclosure($cmid) {
        global $OUTPUT;
        if (!checker::cm_enabled((int) $cmid)) {
            return '';
        }
        $text = trim((string) get_config('plagiarism_sduembed', 'disclosure'));
        if ($text === '') {
            $text = get_string('disclosuredefault', 'plagiarism_sduembed');
        }
        return $OUTPUT->box(format_text($text, FORMAT_MOODLE, ['noclean' => false]), 'generalbox boxaligncenter plagiarism_sduembed_disclosure');
    }
}

/**
 * Add the on/off choice to the settings form of an assignment.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function plagiarism_sduembed_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;

    if (!get_config('plagiarism_sduembed', 'enabled') || $formwrapper->get_current()->modulename !== 'assign') {
        return;
    }
    $course = $formwrapper->get_course();
    if (!has_capability('plagiarism/sduembed:enable', context_course::instance($course->id))) {
        return;
    }
    $cm = $formwrapper->get_coursemodule();
    if ($cm) {
        $current = (int) $DB->get_field('plagiarism_sduembed_cm', 'enabled', ['cmid' => $cm->id]);
    } else {
        $current = (int) get_config('plagiarism_sduembed', 'defaulton');
    }
    $mform->addElement('header', 'plagiarism_sduembed_header', get_string('pluginname', 'plagiarism_sduembed'));
    $mform->addElement('selectyesno', 'plagiarism_sduembed_enabled', get_string('cmenable', 'plagiarism_sduembed'));
    $mform->addHelpButton('plagiarism_sduembed_enabled', 'cmenable', 'plagiarism_sduembed');
    $mform->setDefault('plagiarism_sduembed_enabled', $current);
}

/**
 * Save the on/off choice of an assignment.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function plagiarism_sduembed_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    if (!isset($data->plagiarism_sduembed_enabled) || empty($data->coursemodule)) {
        return $data;
    }
    $record = $DB->get_record('plagiarism_sduembed_cm', ['cmid' => $data->coursemodule]);
    $enabled = empty($data->plagiarism_sduembed_enabled) ? 0 : 1;
    if ($record) {
        if ($record->enabled != $enabled) {
            $DB->update_record('plagiarism_sduembed_cm', (object) ['id' => $record->id, 'enabled' => $enabled,
                'timemodified' => time()]);
        }
    } else if ($enabled) {
        $DB->insert_record('plagiarism_sduembed_cm', (object) ['cmid' => $data->coursemodule, 'enabled' => 1,
            'timemodified' => time()]);
    }
    return $data;
}
