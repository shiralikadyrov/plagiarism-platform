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
 * Plain text from submitted files and online text.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_sduembed;

use plagiarism_sduembed\local\text;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns what a student submitted into plain text.
 */
class extractor {

    /** @var int Files larger than this are not read (bytes). */
    const MAX_FILE_BYTES = 52428800;

    /**
     * File types that can be read with the current settings.
     *
     * @return string[] lower-case extensions
     */
    public static function supported_extensions(): array {
        $ext = ['txt', 'md', 'html', 'htm', 'docx', 'odt'];
        if (self::pdftotext_path() !== '') {
            $ext[] = 'pdf';
        }
        return $ext;
    }

    /**
     * Path of the pdftotext program, or '' if it is not set or cannot be run.
     *
     * @return string
     */
    public static function pdftotext_path(): string {
        $path = trim((string) get_config('plagiarism_sduembed', 'pdftotext'));
        return ($path !== '' && is_executable($path)) ? $path : '';
    }

    /**
     * Plain text of an online-text submission.
     *
     * @param string $html
     * @return string
     */
    public static function from_html(string $html): string {
        return text::clean(html_to_text($html, 0, false));
    }

    /**
     * Plain text of a stored file.
     *
     * @param \stored_file $file
     * @return string|null null if this kind of file cannot be read
     */
    public static function from_file(\stored_file $file): ?string {
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (!in_array($ext, self::supported_extensions()) || $file->get_filesize() > self::MAX_FILE_BYTES) {
            return null;
        }
        if ($ext === 'txt' || $ext === 'md') {
            return text::clean(text::to_utf8($file->get_content()));
        }
        if ($ext === 'html' || $ext === 'htm') {
            return self::from_html(text::to_utf8($file->get_content()));
        }
        $path = $file->copy_content_to_temp('plagiarism_sduembed');
        if (!$path) {
            return null;
        }
        try {
            if ($ext === 'docx') {
                return self::from_zip($path, ['word/document.xml', 'word/footnotes.xml', 'word/endnotes.xml']);
            }
            if ($ext === 'odt') {
                return self::from_zip($path, ['content.xml']);
            }
            return self::from_pdf($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Text of the XML parts of a docx or odt file.
     *
     * @param string $path
     * @param string[] $members first one is required, the others are optional
     * @return string|null
     */
    public static function from_zip(string $path, array $members): ?string {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $out = [];
        foreach ($members as $i => $member) {
            $xml = $zip->getFromName($member);
            if ($xml === false) {
                if ($i === 0) {
                    $zip->close();
                    return null;
                }
                continue;
            }
            $out[] = text::office_xml_to_text($xml);
        }
        $zip->close();
        return text::clean(implode("\n\n", $out));
    }

    /**
     * Text layer of a PDF, read with pdftotext.
     *
     * @param string $path
     * @return string|null
     */
    protected static function from_pdf(string $path): ?string {
        $bin = self::pdftotext_path();
        if ($bin === '') {
            return null;
        }
        $cmd = escapeshellarg($bin) . ' -q -enc UTF-8 -nopgbrk ' . escapeshellarg($path) . ' -';
        $lines = [];
        $code = 0;
        exec($cmd, $lines, $code);
        if ($code !== 0) {
            return null;
        }
        $out = implode("\n", $lines);
        // Join words hyphenated across a line end.
        $out = preg_replace('/(\p{L})-\n(\p{L})/u', '$1$2', $out) ?? $out;
        return text::clean($out);
    }
}
