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
 * Language strings, English.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'API key';
$string['apikey_help'] = 'The value of API_KEY that the API server was started with. Leave empty if the server has no key.';
$string['apiurl'] = 'API address';
$string['apiurl_help'] = 'Address of the project\'s API server, for example https://plagiarism.example.edu.kz. Use HTTPS unless the server is on the same machine or a trusted internal network.';
$string['batchsize'] = 'Submissions per run of the scheduled task';
$string['batchsize_help'] = 'The scheduled task runs every five minutes and checks up to this many queued items each time.';
$string['checking'] = 'Checking';
$string['cmenable'] = 'Check submissions for similarity';
$string['cmenable_help'] = 'If set to Yes, each submitted file and online text is compared with the other submissions to this assignment. The comparison uses a contextual embedding model, so it also finds sentences that were reworded. Results appear beside each submission in the grading view a few minutes after submission.';
$string['connection'] = 'Connection to the API server';
$string['defaulton'] = 'Switched on in new assignments';
$string['defaulton_help'] = 'The starting value of "Check submissions for similarity" when a teacher creates an assignment. Teachers can change it.';
$string['disclosure'] = 'Notice shown to students';
$string['disclosure_help'] = 'Shown on the submission page of every assignment that uses the check. Leave empty for the standard notice.';
$string['disclosuredefault'] = 'Work submitted here is compared with the other submissions to this assignment by the institution\'s similarity service. The service receives the text of your work without your name. Its result is a prompt for your teacher to read the matched passages; it is not a decision about misconduct.';
$string['enabled'] = 'Enable the plugin';
$string['enabled_help'] = 'Plagiarism plugins must also be enabled for the site under Site administration, Advanced features.';
$string['errorpdftotext'] = 'No program that can be run was found at this path';
$string['errorthreshold'] = 'Enter a number from 0 to 1, or leave empty';
$string['errorurl'] = 'The address must start with https:// or http://';
$string['itemfile'] = 'Submitted file';
$string['itemonlinetext'] = 'Online text';
$string['linkprefix'] = 'Similarity check:';
$string['maxsources'] = 'Largest number of other submissions to compare with';
$string['maxsources_help'] = 'Each submission is compared with the most recent work of up to this many other students or groups in the same assignment.';
$string['pdftotext'] = 'Path to pdftotext';
$string['pdftotext_help'] = 'Full path of the pdftotext program from Poppler, for example /usr/bin/pdftotext. PDF files are only checked if this is set. Scanned PDFs without a text layer cannot be read. If your site forbids setting program paths in the web interface, set it in config.php: $CFG->forced_plugin_settings[\'plagiarism_sduembed\'][\'pdftotext\'] = \'/usr/bin/pdftotext\';';
$string['plagiarismoff'] = 'Plagiarism plugins are switched off for this site. Switch them on under Site administration, Advanced features, "Enable plagiarism plugins".';
$string['pluginname'] = 'SDU NLP similarity check';
$string['privacy:metadata:api'] = 'The text of submissions is sent to the similarity API server that the site administrator has configured. The reference server does not store it.';
$string['privacy:metadata:api:submissionid'] = 'The numeric id of each submission. No names or user ids are sent.';
$string['privacy:metadata:api:submissiontext'] = 'The text of the submission and of the other submissions it is compared with.';
$string['privacy:metadata:sub'] = 'Result of the similarity check for each submitted file or online text.';
$string['privacy:metadata:sub:report'] = 'The matched sentence pairs.';
$string['privacy:metadata:sub:score'] = 'The share of sentences with a close match in another submission.';
$string['privacy:metadata:sub:submitter'] = 'The user who uploaded the work.';
$string['privacy:metadata:sub:timechecked'] = 'When the check ran.';
$string['privacy:metadata:sub:userid'] = 'The user the submission belongs to.';
$string['privacy:path'] = 'Similarity check';
$string['queuesummary'] = 'Queue: {$a->queued} waiting, {$a->done} checked, {$a->failed} failed. File types that can be read with the current settings: {$a->types}.';
$string['recheckhours'] = 'Check again after, hours';
$string['recheckhours_help'] = 'A submission can only be compared with the work that exists when it is checked. When classmates submit later, an earlier submission is checked again once this many hours have passed since its last check. Set to 0 to check each submission only once.';
$string['reportchecked'] = 'Checked';
$string['reportcompared'] = 'Other submissions compared';
$string['reportcosine'] = 'Cosine';
$string['reportexplain'] = 'A sentence is flagged when its meaning is close to a sentence in another submission, as measured by the cosine of their contextual embeddings. Matched text and Jaccard are the word-for-word measures: the share of this submission inside three-word sequences that also occur in the other one, and the overlap of the two sets of sequences.';
$string['reportitem'] = 'Checked item';
$string['reportmodel'] = 'Model';
$string['reportnomatches'] = 'No sentence reached the threshold and no word-for-word overlap was found.';
$string['reportnotready'] = 'There is no report for this item yet.';
$string['reportscore'] = 'Sentences with a close match';
$string['reportscorevalue'] = '{$a->percent}% ({$a->flagged} of {$a->sentences} sentences)';
$string['reportsource'] = 'Other submission {$a}';
$string['reportsourcestats'] = '{$a->flagged} flagged sentences ({$a->share}% of this submission). Matched text {$a->containment}%, Jaccard {$a->jaccard}, mean best cosine {$a->cosine}.';
$string['reportsrcsentence'] = 'Closest sentence in the other submission';
$string['reportsubsentence'] = 'Sentence in this submission';
$string['reportthreshold'] = 'Threshold (cosine)';
$string['reporttitle'] = 'Similarity report';
$string['reviewnotice'] = 'This report is a prompt for review. Correctly quoted and cited text, common phrases and assignment wording can all be flagged. Read the passages and talk to the student before drawing a conclusion.';
$string['scorelink'] = '{$a->percent}% of sentences have a close match';
$string['scorelinktitle'] = 'Open the report with the matched sentences';
$string['sduembed'] = 'SDU NLP similarity check';
$string['sduembed:enable'] = 'Switch the similarity check on or off in an activity';
$string['sduembed:viewreport'] = 'View similarity scores and reports';
$string['statusfailed'] = 'the check failed; ask the site administrator';
$string['statusnosources'] = 'no other submissions to compare with yet';
$string['statusnotqueued'] = 'not checked (submitted before the check was switched on)';
$string['statusqueued'] = 'waiting to be checked';
$string['statustooshort'] = 'too short to check';
$string['statusunsupported'] = 'this file type cannot be read';
$string['students'] = 'Students';
$string['studentview'] = 'Students can see their own result';
$string['studentview_help'] = 'If set to Yes, a student sees the share of flagged sentences for their own work and which of their sentences were flagged. Students never see text from a classmate\'s work.';
$string['taskprocessqueue'] = 'Send queued submissions to the similarity API';
$string['testconnection'] = 'Save and test the connection';
$string['testfailed'] = 'The API server could not be reached ({$a}). Check the address, and that this Moodle server is allowed to connect to it.';
$string['testkeybad'] = 'The API server refused the key ({$a}).';
$string['testkeyok'] = 'The API key was accepted.';
$string['testkeyunknown'] = 'This server version cannot confirm the key in advance. It will be checked with the first submission.';
$string['testok'] = 'The API server answered. Server version {$a->version}, model {$a->model}, threshold {$a->threshold}.';
$string['threshold'] = 'Similarity threshold';
$string['threshold_help'] = 'Cosine similarity, from 0 to 1, at or above which two sentences count as a match. Leave empty to use the value the API server was calibrated with (recommended).';
$string['timeout'] = 'Time limit for one request, seconds';
