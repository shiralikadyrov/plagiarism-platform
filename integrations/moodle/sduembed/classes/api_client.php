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
 * Client for the plagiarism API server.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed;

defined('MOODLE_INTERNAL') || die();

/**
 * Talks to the reference API server (server.py in the project repository).
 */
class api_client {

    /** @var string Base address without a trailing slash. */
    protected $url;

    /** @var string API key, may be empty. */
    protected $key;

    /**
     * Constructor. Without arguments, the plugin settings are used.
     *
     * @param string|null $url
     * @param string|null $key
     */
    public function __construct(?string $url = null, ?string $key = null) {
        $this->url = rtrim(trim($url ?? (string) get_config('plagiarism_sduembed', 'apiurl')), '/');
        $this->key = trim($key ?? (string) get_config('plagiarism_sduembed', 'apikey'));
    }

    /**
     * True if an address is set.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->url !== '';
    }

    /**
     * Server status: model names, threshold, limits.
     *
     * @return array
     */
    public function health(): array {
        return $this->request('GET', '/health', null, 20);
    }

    /**
     * Check that the server accepts the key. Early copies of the server have no /v1/ping; that is not an error.
     *
     * @return bool true if the key was checked, false if the server cannot check it this way
     */
    public function ping(): bool {
        try {
            $this->request('GET', '/v1/ping', null, 20);
            return true;
        } catch (api_exception $e) {
            if ($e->httpcode === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Compare one submission with up to 50 sources.
     *
     * @param string $submission
     * @param array $sources list of ['id' => string, 'text' => string]
     * @param float|null $threshold
     * @return array decoded response
     */
    public function compare(string $submission, array $sources, ?float $threshold = null): array {
        $body = ['submission' => $submission, 'sources' => array_values($sources)];
        if ($threshold !== null) {
            $body['threshold'] = $threshold;
        }
        $timeout = (int) get_config('plagiarism_sduembed', 'timeout') ?: 300;
        $response = $this->request('POST', '/v1/compare', $body, $timeout);
        if (!isset($response['sources']) || !isset($response['submission_sentences'])) {
            throw new api_exception('Unexpected response from the API', 502);
        }
        return $response;
    }

    /**
     * Send one request and decode the JSON answer.
     *
     * @param string $method GET or POST
     * @param string $path
     * @param array|null $body
     * @param int $timeout seconds
     * @return array
     */
    protected function request(string $method, string $path, ?array $body, int $timeout): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (!$this->is_configured()) {
            throw new api_exception('The API address is not set', 404);
        }
        // The address is set by an administrator and is usually on the institution's own network, which the
        // default cURL security rules would block.
        $curl = new \curl(['ignoresecurity' => true]);
        $headers = ['Accept: application/json'];
        if ($this->key !== '') {
            $headers[] = 'X-API-Key: ' . $this->key;
        }
        $options = ['CURLOPT_TIMEOUT' => $timeout, 'CURLOPT_CONNECTTIMEOUT' => 10];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $curl->setHeader($headers);
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $raw = $curl->post($this->url . $path, $json, $options);
        } else {
            $curl->setHeader($headers);
            $raw = $curl->get($this->url . $path, [], $options);
        }
        if ($curl->get_errno()) {
            throw new api_exception('Could not reach the API: ' . $curl->error, 0);
        }
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300) {
            $detail = is_array($data) && isset($data['detail']) && is_string($data['detail']) ? $data['detail'] : '';
            throw new api_exception(trim("HTTP $code $detail"), $code);
        }
        if (!is_array($data)) {
            throw new api_exception('The API answer is not JSON', 502);
        }
        return $data;
    }
}
