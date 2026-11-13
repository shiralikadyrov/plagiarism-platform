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
 * Report for one submitted item: the matched sentence pairs.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/plagiarism/sduembed/lib.php');

use plagiarism_sduembed\checker;

$id = required_param('id', PARAM_INT);
$record = $DB->get_record('plagiarism_sduembed_sub', ['id' => $id], '*', MUST_EXIST);
list($course, $cm) = get_course_and_cm_from_cmid($record->cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$component = 'plagiarism_sduembed';

$isreviewer = has_capability('plagiarism/sduembed:viewreport', $context);
if (!plagiarism_plugin_sduembed::can_view($record, $context)) {
    throw new required_capability_exception($context, 'plagiarism/sduembed:viewreport', 'nopermissions', '');
}

$PAGE->set_url('/plagiarism/sduembed/report.php', ['id' => $id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('reporttitle', $component));
$PAGE->set_heading($course->fullname);

$assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, blindmarking, revealidentities', MUST_EXIST);
$shownames = $isreviewer && !($assign->blindmarking && !$assign->revealidentities);
$report = $record->report ? json_decode($record->report, true) : null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', $component));
echo $OUTPUT->notification(get_string('reviewnotice', $component), \core\output\notification::NOTIFY_INFO, false);

if ($record->status != checker::DONE || !$report) {
    echo $OUTPUT->box(get_string('reportnotready', $component));
    echo $OUTPUT->footer();
    exit;
}

// What was checked.
$what = get_string('itemonlinetext', $component);
if ($record->itemtype === 'file') {
    $file = get_file_storage()->get_file_by_hash($record->identifier);
    $what = $file ? s($file->get_filename()) : get_string('itemfile', $component);
}
$summary = new html_table();
$summary->attributes['class'] = 'generaltable plagiarism_sduembed_summary';
$summary->data = [
    [get_string('reportitem', $component), $what],
    [get_string('reportscore', $component), get_string('reportscorevalue', $component, (object) [
        'percent' => format_float(100 * $report['score'], 1),
        'flagged' => (int) $report['flagged'],
        'sentences' => (int) $report['sentences'],
    ])],
    [get_string('reportcompared', $component), (int) ($report['compared'] ?? count($report['sources']))],
    [get_string('reportthreshold', $component), s($report['threshold'])],
    [get_string('reportmodel', $component), s($report['model'])],
    [get_string('reportchecked', $component), userdate($record->timechecked)],
];
echo html_writer::table($summary);
echo html_writer::tag('p', get_string('reportexplain', $component));

if (empty($report['sources'])) {
    echo $OUTPUT->box(get_string('reportnomatches', $component));
    echo $OUTPUT->footer();
    exit;
}

$n = 0;
foreach ($report['sources'] as $src) {
    $n++;
    $label = get_string('reportsource', $component, $n);
    if ($shownames) {
        $other = $DB->get_record('assign_submission', ['id' => (int) substr($src['id'], 1)], 'id, userid, groupid');
        if ($other && $other->userid && ($user = $DB->get_record('user', ['id' => $other->userid]))) {
            $label .= ': ' . s(fullname($user));
        } else if ($other && $other->groupid) {
            $label .= ': ' . s(groups_get_group_name($other->groupid));
        }
    }
    echo $OUTPUT->heading($label, 3);
    echo html_writer::tag('p', get_string('reportsourcestats', $component, (object) [
        'flagged' => (int) $src['flagged'],
        'share' => format_float(100 * $src['flagged_share'], 1),
        'containment' => format_float(100 * $src['containment'], 1),
        'jaccard' => format_float($src['jaccard'], 3),
        'cosine' => format_float($src['mean_best_cosine'], 3),
    ]));
    if (empty($src['matches'])) {
        continue;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable plagiarism_sduembed_pairs';
    if ($isreviewer) {
        $table->head = [get_string('reportsubsentence', $component), get_string('reportsrcsentence', $component),
            get_string('reportcosine', $component)];
    } else {
        // A student sees which of their own sentences were flagged, never a classmate's text.
        $table->head = [get_string('reportsubsentence', $component), get_string('reportcosine', $component)];
    }
    foreach ($src['matches'] as $m) {
        $row = [s($m[0])];
        if ($isreviewer) {
            $row[] = s($m[1]);
        }
        $row[] = format_float($m[2], 3);
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
