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
 * Tests for the text helpers.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed;

use plagiarism_sduembed\local\text;

/**
 * Splitting, batching and merging need no database.
 *
 * @covers \plagiarism_sduembed\local\text
 */
final class text_test extends \basic_testcase {

    public function test_to_utf8_reads_windows_1251(): void {
        $utf8 = 'Привет, әлем';
        $this->assertSame($utf8, text::to_utf8($utf8));
        $this->assertSame('Привет', text::to_utf8(mb_convert_encoding('Привет', 'Windows-1251', 'UTF-8')));
        $this->assertSame('abc', text::to_utf8("\xEF\xBB\xBFabc"));
    }

    public function test_has_sentence(): void {
        $this->assertTrue(text::has_sentence('Бұл үш сөз.'));
        $this->assertFalse(text::has_sentence('Too short. No.'));
        $this->assertFalse(text::has_sentence(''));
    }

    public function test_office_xml_to_text(): void {
        $xml = '<w:document><w:body><w:p><w:r><w:t>First &amp; second</w:t></w:r><w:r><w:tab/><w:t>part.</w:t></w:r></w:p>'
            . '<w:p><w:r><w:delText>removed</w:delText><w:t xml:space="preserve">Next </w:t><w:br/><w:t>line.</w:t></w:r></w:p>'
            . '</w:body></w:document>';
        $this->assertSame("First & second part.\nNext\nline.", text::office_xml_to_text($xml));
    }

    public function test_split_keeps_all_text_and_respects_limit(): void {
        $text = trim(str_repeat('сөз word ', 500));
        $parts = text::split($text, 300);
        $this->assertGreaterThan(1, count($parts));
        foreach ($parts as $p) {
            $this->assertLessThanOrEqual(300, mb_strlen($p));
        }
        $this->assertSame($text, implode(' ', $parts));
        $this->assertSame(['short text'], text::split('short text', 300));
        $this->assertSame([], text::split('', 300));
        // No spaces at all: cut hard at the limit.
        $this->assertSame([str_repeat('x', 10), str_repeat('x', 5)], text::split(str_repeat('x', 15), 10));
    }

    public function test_batch_respects_count_and_size(): void {
        $src = function($n, $len = 10) {
            return ['id' => "s$n", 'text' => str_repeat('a', $len)];
        };
        $batches = text::batch(array_map($src, range(1, 7)), 3, 1000);
        $this->assertSame([3, 3, 1], array_map('count', $batches));
        $batches = text::batch([$src(1, 60), $src(2, 60), $src(3, 30)], 50, 100);
        $this->assertSame([1, 2], array_map('count', $batches));
        // One source over the size limit still goes out, alone.
        $this->assertSame([1, 1], array_map('count', text::batch([$src(1, 500), $src(2, 5)], 50, 100)));
        $this->assertSame([], text::batch([]));
    }

    /**
     * Build a /v1/compare response.
     *
     * @param int $sentences
     * @param array $sources id => list of [submission sentence, source sentence, cosine]
     * @return array
     */
    private function response(int $sentences, array $sources): array {
        $out = ['model' => 'm', 'threshold' => 0.8, 'submission_sentences' => $sentences, 'sources' => []];
        foreach ($sources as $id => $matches) {
            $out['sources'][] = [
                'source_id' => $id,
                'lexical' => ['jaccard' => 0.1, 'containment' => count($matches) ? 0.2 : 0.0],
                'semantic' => ['mean_best_cosine' => 0.5, 'flagged_share' => count($matches) / $sentences],
                'matches' => array_map(function($m) {
                    return ['submission_sentence' => $m[0], 'source_sentence' => $m[1], 'cosine' => $m[2]];
                }, $matches),
            ];
        }
        return $out;
    }

    public function test_merge_counts_a_sentence_once_across_sources_and_batches(): void {
        // Four sentences. A matches in s1 and s3 (different batches), B in s2. s4 matches nothing.
        $batch1 = $this->response(4, ['s1' => [['A', 'a1', 0.9]], 's2' => [['B', 'b2', 0.85]]]);
        $batch2 = $this->response(4, ['s3' => [['A', 'a3', 0.95]], 's4' => []]);
        $r = text::merge([[$batch1, $batch2]]);
        $this->assertSame(4, $r['sentences']);
        $this->assertSame(2, $r['flagged']);
        $this->assertSame(0.5, $r['score']);
        $this->assertSame(['s1', 's2', 's3'], array_column($r['sources'], 'id'));      // The source s4 is dropped.
        $this->assertSame(0.25, $r['sources'][0]['flagged_share']);
    }

    public function test_merge_counts_repeated_identical_sentences(): void {
        // The submission contains the same sentence twice, and both copies match s1.
        $r = text::merge([[$this->response(3, ['s1' => [['A', 'a', 0.9], ['A', 'a', 0.9]], 's2' => [['A', 'x', 0.8]]])]]);
        $this->assertSame(2, $r['flagged']);
    }

    public function test_merge_adds_up_parts_of_a_long_submission(): void {
        $part1 = [$this->response(10, ['s1' => [['A', 'a', 0.9]]])];
        $part2 = [$this->response(30, ['s1' => [['B', 'b', 0.8], ['C', 'c', 0.99]]])];
        $r = text::merge([$part1, $part2]);
        $this->assertSame(40, $r['sentences']);
        $this->assertSame(3, $r['flagged']);
        $this->assertSame(0.075, $r['score']);
        $this->assertSame('C', $r['sources'][0]['matches'][0][0]);                       // Highest cosine first.
    }

    public function test_merge_of_nothing(): void {
        $r = text::merge([]);
        $this->assertSame(0.0, $r['score']);
        $this->assertSame([], $r['sources']);
    }
}
