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
 * Error from the plagiarism API.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed;

/**
 * Carries the HTTP status and whether trying again later can help.
 */
class api_exception extends \Exception {

    /** @var int HTTP status, 0 if the server could not be reached. */
    public $httpcode;

    /**
     * Constructor.
     *
     * @param string $message
     * @param int $httpcode
     */
    public function __construct(string $message, int $httpcode = 0) {
        parent::__construct($message);
        $this->httpcode = $httpcode;
    }

    /**
     * The key or the address is wrong. Nothing will work until an administrator fixes the settings.
     *
     * @return bool
     */
    public function is_config_problem(): bool {
        return in_array($this->httpcode, [401, 403, 404]);
    }

    /**
     * A network error, a time-out or a server error: worth another attempt later.
     *
     * @return bool
     */
    public function is_retryable(): bool {
        return $this->httpcode === 0 || $this->httpcode === 408 || $this->httpcode === 429 || $this->httpcode >= 500;
    }
}
