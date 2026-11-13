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
 * Text helpers with no dependency on Moodle, so they can be tested on their own.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Splitting, batching and merging for the calls to the API.
 */
class text {

    /** @var int Longest text the API accepts in one field, in characters. */
    const MAX_CHARS = 200000;

    /** @var int Largest number of sources the API accepts in one request. */
    const MAX_SOURCES = 50;

    /** @var int Total characters of sources sent in one request. Keeps request size and server memory bounded. */
    const MAX_REQUEST_CHARS = 1000000;

    /**
     * Make sure a string is valid UTF-8. Text files written on Windows in Kazakhstan are often in Windows-1251.
     *
     * @param string $raw
     * @return string
     */
    public static function to_utf8(string $raw): string {
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        } else if (strncmp($raw, "\xFF\xFE", 2) === 0 || strncmp($raw, "\xFE\xFF", 2) === 0) {
            return (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        }
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }
        return (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
    }

    /**
     * Tidy whitespace and remove control characters that JSON or the model would choke on.
     *
     * @param string $text
     * @return string
     */
    public static function clean(string $text): string {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $text) ?? '';
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? '';
        $text = preg_replace('/ ?\n ?/u', "\n", $text) ?? '';
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';
        return trim($text);
    }

    /**
     * True if the text has at least one sentence of three or more words. The API refuses anything shorter.
     *
     * @param string $text
     * @return bool
     */
    public static function has_sentence(string $text): bool {
        foreach (preg_split('/(?<=[.!?…])\s+|\n+/u', $text) ?: [] as $s) {
            if (count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []) >= 3) {
                return true;
            }
        }
        return false;
    }

    /**
     * Plain text of a WordprocessingML (docx) document.xml or an OpenDocument content.xml.
     *
     * @param string $xml
     * @return string
     */
    public static function office_xml_to_text(string $xml): string {
        // Paragraph and line ends become new lines, tabs become spaces.
        $xml = preg_replace('~</(w:p|text:p|text:h)>~', "\n", $xml) ?? '';
        $xml = preg_replace('~<(w:br|w:cr|text:line-break)\b[^>]*/?>~', "\n", $xml) ?? '';
        $xml = preg_replace('~<(w:tab|text:tab|text:s)\b[^>]*/?>~', ' ', $xml) ?? '';
        // Drop text that Word keeps for deleted tracked changes and for field codes.
        $xml = preg_replace('~<w:delText\b[^>]*>.*?</w:delText>~s', '', $xml) ?? '';
        $xml = preg_replace('~<w:instrText\b[^>]*>.*?</w:instrText>~s', '', $xml) ?? '';
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return self::clean($text);
    }

    /**
     * Cut a text into parts of at most $max characters, breaking at the end of a line or a word where possible.
     *
     * @param string $text
     * @param int $max
     * @return string[]
     */
    public static function split(string $text, int $max = self::MAX_CHARS): array {
        $parts = [];
        while (mb_strlen($text, 'UTF-8') > $max) {
            $head = mb_substr($text, 0, $max, 'UTF-8');
            $cut = mb_strrpos($head, "\n", 0, 'UTF-8');
            if ($cut === false || $cut < $max / 2) {
                $cut = mb_strrpos($head, ' ', 0, 'UTF-8');
            }
            if ($cut === false || $cut < $max / 2) {
                $cut = $max;
            }
            $parts[] = trim(mb_substr($text, 0, $cut, 'UTF-8'));
            $text = ltrim(mb_substr($text, $cut, null, 'UTF-8'));
        }
        if ($text !== '') {
            $parts[] = $text;
        }
        return $parts;
    }

    /**
     * Group sources into requests the API will accept.
     *
     * @param array $sources list of ['id' => string, 'text' => string]
     * @param int $maxcount
     * @param int $maxchars
     * @return array list of lists of sources
     */
    public static function batch(array $sources, int $maxcount = self::MAX_SOURCES,
            int $maxchars = self::MAX_REQUEST_CHARS): array {
        $batches = [];
        $current = [];
        $chars = 0;
        foreach ($sources as $source) {
            $len = mb_strlen($source['text'], 'UTF-8');
            if ($current && (count($current) >= $maxcount || $chars + $len > $maxchars)) {
                $batches[] = $current;
                $current = [];
                $chars = 0;
            }
            $current[] = $source;
            $chars += $len;
        }
        if ($current) {
            $batches[] = $current;
        }
        return $batches;
    }

    /**
     * Combine the API responses for one submission into one report.
     *
     * $parts has one entry per part of the submission (one, unless the submission is longer than the API limit).
     * Each entry is the list of /v1/compare responses for that part, one per batch of sources.
     *
     * A submission sentence may match in several sources, so shares cannot simply be added up. The number of
     * flagged sentences in a part is counted as: for each distinct sentence text, the largest number of times
     * it was matched within any single source.
     *
     * @param array $parts
     * @param int $maxsources keep at most this many sources in the report
     * @param int $maxmatches keep at most this many sentence pairs per source
     * @return array ['model', 'threshold', 'sentences', 'flagged', 'score', 'sources' => [...]]
     */
    public static function merge(array $parts, int $maxsources = 20, int $maxmatches = 100): array {
        $model = '';
        $threshold = null;
        $sentences = 0;
        $flagged = 0;
        $sources = [];
        foreach ($parts as $responses) {
            $partsentences = 0;
            $best = [];
            foreach ($responses as $response) {
                $model = (string) ($response['model'] ?? $model);
                $threshold = $response['threshold'] ?? $threshold;
                $partsentences = max($partsentences, (int) ($response['submission_sentences'] ?? 0));
                foreach ($response['sources'] ?? [] as $src) {
                    $id = (string) $src['source_id'];
                    $n = (int) ($response['submission_sentences'] ?? 0);
                    if (!isset($sources[$id])) {
                        $sources[$id] = ['id' => $id, 'weight' => 0, 'flagged' => 0, 'jaccard' => 0.0,
                            'containment' => 0.0, 'mean_best_cosine' => 0.0, 'matches' => []];
                    }
                    $sources[$id]['weight'] += $n;
                    $sources[$id]['jaccard'] += $n * (float) ($src['lexical']['jaccard'] ?? 0);
                    $sources[$id]['containment'] += $n * (float) ($src['lexical']['containment'] ?? 0);
                    $sources[$id]['mean_best_cosine'] += $n * (float) ($src['semantic']['mean_best_cosine'] ?? 0);
                    $counts = [];
                    foreach ($src['matches'] ?? [] as $m) {
                        $s = (string) $m['submission_sentence'];
                        $counts[$s] = ($counts[$s] ?? 0) + 1;
                        $sources[$id]['flagged']++;
                        $sources[$id]['matches'][] = [$s, (string) $m['source_sentence'], round((float) $m['cosine'], 4)];
                    }
                    foreach ($counts as $s => $c) {
                        $best[$s] = max($best[$s] ?? 0, $c);
                    }
                }
            }
            $sentences += $partsentences;
            $flagged += min($partsentences, array_sum($best));
        }

        $out = [];
        foreach ($sources as $src) {
            $w = max(1, $src['weight']);
            usort($src['matches'], function($a, $b) {
                return $b[2] <=> $a[2];
            });
            $out[] = [
                'id' => $src['id'],
                'flagged' => $src['flagged'],
                'flagged_share' => $sentences ? round($src['flagged'] / $sentences, 4) : 0.0,
                'jaccard' => round($src['jaccard'] / $w, 4),
                'containment' => round($src['containment'] / $w, 4),
                'mean_best_cosine' => round($src['mean_best_cosine'] / $w, 4),
                'matches' => array_slice($src['matches'], 0, $maxmatches),
            ];
        }
        // Only sources with something to show, strongest first.
        $out = array_values(array_filter($out, function($s) {
            return $s['flagged'] > 0 || $s['containment'] >= 0.05;
        }));
        usort($out, function($a, $b) {
            return [$b['flagged'], $b['containment']] <=> [$a['flagged'], $a['containment']];
        });

        return [
            'model' => $model,
            'threshold' => $threshold,
            'sentences' => $sentences,
            'flagged' => $flagged,
            'score' => $sentences ? round($flagged / $sentences, 4) : 0.0,
            'sources' => array_slice($out, 0, $maxsources),
        ];
    }
}
