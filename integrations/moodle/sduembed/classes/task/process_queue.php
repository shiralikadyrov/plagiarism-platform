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
 * Scheduled task: send queued submissions to the API.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed\task;

use plagiarism_sduembed\api_exception;
use plagiarism_sduembed\checker;

defined('MOODLE_INTERNAL') || die();

/**
 * Works through the queue for a few minutes at a time.
 */
class process_queue extends \core\task\scheduled_task {

    /** @var int Stop starting new checks after this many seconds. */
    const TIME_BUDGET = 240;

    /**
     * Name shown in the list of scheduled tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskprocessqueue', 'plagiarism_sduembed');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        if (empty($CFG->enableplagiarism) || !get_config('plagiarism_sduembed', 'enabled')) {
            return;
        }
        $checker = new checker();
        $requeued = $checker->requeue_stale();
        if ($requeued) {
            mtrace("sduembed: $requeued earlier submissions queued again because classmates have submitted since.");
        }
        $limit = (int) get_config('plagiarism_sduembed', 'batchsize') ?: 20;
        $start = time();
        $counts = [];
        foreach ($checker->due($limit) as $record) {
            if (time() - $start > self::TIME_BUDGET) {
                break;
            }
            try {
                $status = $checker->process($record);
            } catch (api_exception $e) {
                // Wrong key or address. Leave the queue as it is and tell the administrator.
                mtrace('sduembed: stopped, the API refused the request (' . $e->getMessage() .
                    '). Check the address and key in the plugin settings.');
                break;
            } catch (\Throwable $e) {
                mtrace("sduembed: record {$record->id} failed: " . $e->getMessage());
                $status = 'error';
                $DB->update_record('plagiarism_sduembed_sub', (object) ['id' => $record->id,
                    'attempts' => $record->attempts + 1,
                    'status' => ($record->attempts + 1 >= checker::MAX_ATTEMPTS) ? checker::FAILED : checker::QUEUED,
                    'timenext' => time() + HOURSECS,
                    'errormsg' => \core_text::substr($e->getMessage(), 0, 250),
                    'timemodified' => time()]);
            }
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        if ($counts) {
            $parts = [];
            foreach ($counts as $status => $n) {
                $parts[] = "status $status: $n";
            }
            mtrace('sduembed: processed ' . array_sum($counts) . ' (' . implode(', ', $parts) . ').');
        }
    }
}
