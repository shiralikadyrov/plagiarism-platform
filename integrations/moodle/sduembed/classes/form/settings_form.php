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
 * Site settings form.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Settings of the plugin.
 */
class settings_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $c = 'plagiarism_sduembed';

        $mform->addElement('header', 'connection', get_string('connection', $c));

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', $c));
        $mform->addHelpButton('enabled', 'enabled', $c);

        $mform->addElement('text', 'apiurl', get_string('apiurl', $c), ['size' => 60]);
        $mform->setType('apiurl', PARAM_URL);
        $mform->addHelpButton('apiurl', 'apiurl', $c);

        $mform->addElement('passwordunmask', 'apikey', get_string('apikey', $c), ['size' => 40]);
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('apikey', 'apikey', $c);

        $mform->addElement('text', 'timeout', get_string('timeout', $c), ['size' => 6]);
        $mform->setType('timeout', PARAM_INT);
        $mform->setDefault('timeout', 300);

        $mform->addElement('header', 'checking', get_string('checking', $c));
        $mform->setExpanded('checking');

        $mform->addElement('selectyesno', 'defaulton', get_string('defaulton', $c));
        $mform->addHelpButton('defaulton', 'defaulton', $c);

        $mform->addElement('text', 'threshold', get_string('threshold', $c), ['size' => 6]);
        $mform->setType('threshold', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('threshold', 'threshold', $c);

        $mform->addElement('text', 'maxsources', get_string('maxsources', $c), ['size' => 6]);
        $mform->setType('maxsources', PARAM_INT);
        $mform->setDefault('maxsources', 200);
        $mform->addHelpButton('maxsources', 'maxsources', $c);

        $mform->addElement('text', 'recheckhours', get_string('recheckhours', $c), ['size' => 6]);
        $mform->setType('recheckhours', PARAM_FLOAT);
        $mform->setDefault('recheckhours', 2);
        $mform->addHelpButton('recheckhours', 'recheckhours', $c);

        $mform->addElement('text', 'batchsize', get_string('batchsize', $c), ['size' => 6]);
        $mform->setType('batchsize', PARAM_INT);
        $mform->setDefault('batchsize', 20);
        $mform->addHelpButton('batchsize', 'batchsize', $c);

        $mform->addElement('text', 'pdftotext', get_string('pdftotext', $c), ['size' => 60]);
        $mform->setType('pdftotext', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('pdftotext', 'pdftotext', $c);
        if (!empty($this->_customdata['lockpaths'])) {
            $mform->hardFreeze('pdftotext');
        }

        $mform->addElement('header', 'students', get_string('students', $c));
        $mform->setExpanded('students');

        $mform->addElement('selectyesno', 'studentview', get_string('studentview', $c));
        $mform->addHelpButton('studentview', 'studentview', $c);

        $mform->addElement('textarea', 'disclosure', get_string('disclosure', $c), ['rows' => 4, 'cols' => 70]);
        $mform->setType('disclosure', PARAM_TEXT);
        $mform->addHelpButton('disclosure', 'disclosure', $c);

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'testconnection', get_string('testconnection', $c)),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $c = 'plagiarism_sduembed';
        if (!empty($data['enabled']) && trim((string) $data['apiurl']) === '') {
            $errors['apiurl'] = get_string('required');
        }
        if (trim((string) $data['apiurl']) !== '' && !preg_match('~^https?://~i', trim($data['apiurl']))) {
            $errors['apiurl'] = get_string('errorurl', $c);
        }
        $t = trim((string) $data['threshold']);
        if ($t !== '' && (!is_numeric($t) || $t < 0 || $t > 1)) {
            $errors['threshold'] = get_string('errorthreshold', $c);
        }
        $p = trim((string) ($data['pdftotext'] ?? ''));
        if ($p !== '' && empty($this->_customdata['lockpaths']) && !is_executable($p)) {
            $errors['pdftotext'] = get_string('errorpdftotext', $c);
        }
        return $errors;
    }
}
