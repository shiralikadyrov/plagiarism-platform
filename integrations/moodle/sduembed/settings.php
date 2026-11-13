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
 * Settings page. Moodle links to this script from the list of plagiarism plugins.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');

admin_externalpage_setup('plagiarismsduembed');
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$component = 'plagiarism_sduembed';
$fields = ['enabled', 'apiurl', 'apikey', 'timeout', 'defaulton', 'threshold', 'maxsources', 'recheckhours',
    'batchsize', 'pdftotext', 'studentview', 'disclosure'];
// Sites can forbid setting program paths in the web interface. Then pdftotext is set in config.php:
// $CFG->forced_plugin_settings['plagiarism_sduembed']['pdftotext'] = '/usr/bin/pdftotext'; .
$lockpaths = !empty($CFG->preventexecpath);

$form = new \plagiarism_sduembed\form\settings_form(null, ['lockpaths' => $lockpaths]);
$notices = [];

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/plagiarism.php'));
} else if ($data = $form->get_data()) {
    foreach ($fields as $field) {
        if ($field === 'pdftotext' && $lockpaths) {
            continue;
        }
        if (isset($data->$field)) {
            $value = is_string($data->$field) ? trim($data->$field) : $data->$field;
            if ($field === 'apiurl') {
                $value = rtrim($value, '/');
            }
            set_config($field, $value, $component);
        }
    }
    $notices[] = [get_string('changessaved'), \core\output\notification::NOTIFY_SUCCESS];

    if (!empty($data->testconnection)) {
        try {
            $api = new \plagiarism_sduembed\api_client();
            $health = $api->health();
            $a = (object) ['model' => s($health['model'] ?? '?'), 'threshold' => s($health['threshold'] ?? '?'),
                'version' => s($health['version'] ?? '1.0')];
            $notices[] = [get_string('testok', $component, $a), \core\output\notification::NOTIFY_SUCCESS];
            if ($api->ping()) {
                $notices[] = [get_string('testkeyok', $component), \core\output\notification::NOTIFY_SUCCESS];
            } else {
                $notices[] = [get_string('testkeyunknown', $component), \core\output\notification::NOTIFY_INFO];
            }
        } catch (\plagiarism_sduembed\api_exception $e) {
            $key = in_array($e->httpcode, [401, 403]) ? 'testkeybad' : 'testfailed';
            $notices[] = [get_string($key, $component, s($e->getMessage())), \core\output\notification::NOTIFY_ERROR];
        }
    }
}

$current = (object) (array) get_config($component);
$form->set_data($current);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', $component));
if (empty($CFG->enableplagiarism)) {
    echo $OUTPUT->notification(get_string('plagiarismoff', $component), \core\output\notification::NOTIFY_WARNING);
}
foreach ($notices as $n) {
    echo $OUTPUT->notification($n[0], $n[1]);
}

// Queue summary, so an administrator can see that the scheduled task is doing its work.
$counts = $DB->get_records_sql_menu('SELECT status, COUNT(*) FROM {plagiarism_sduembed_sub} GROUP BY status');
$a = (object) [
    'queued' => (int) ($counts[\plagiarism_sduembed\checker::QUEUED] ?? 0),
    'done' => (int) ($counts[\plagiarism_sduembed\checker::DONE] ?? 0),
    'failed' => (int) ($counts[\plagiarism_sduembed\checker::FAILED] ?? 0),
    'types' => implode(', ', \plagiarism_sduembed\extractor::supported_extensions()),
];
echo $OUTPUT->box(get_string('queuesummary', $component, $a), 'generalbox');

$form->display();
echo $OUTPUT->footer();
