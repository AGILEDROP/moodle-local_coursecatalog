<?php
// This file is part of Moodle - http://moodle.org/
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

namespace local_course_category_page;

use core\output\url_rewriter as rewriter;
use moodle_url;
use coding_exception;

/**
 * URL rewriter implementation for course category pages.
 *
 * @package   local_course_category_page
 * @copyright 2024
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url_rewriter implements rewriter {
    /** Table that stores category page settings. */
    private const TABLE = 'local_course_category_page';
    /** Base path from wwwroot (e.g. '' or '/academy/learn'). */
    private const ROOT_PATH_CACHE_KEY = 'local_course_category_page_root_path';

    /**
     * Rewrite moodle_urls into a shorter form when clean URLs are enabled.
     *
     * @param moodle_url $url Original URL.
     * @return moodle_url
     */
    public static function url_rewrite(moodle_url $url): moodle_url {
        if ($url->get_path() === '/local/course_category_page/view.php') {
            $slug = $url->get_param('slug');
            if ($slug && self::use_clean_url($slug)) {
                return new moodle_url(self::get_base_path() . '/' . $slug);
            }
        }

        return $url;
    }

    /**
     * Check if the slug is configured for clean URLs.
     *
     * @param string $slug Slug to test.
     * @return bool
     */
    protected static function use_clean_url(string $slug): bool {
        global $DB;

        try {
            if ($DB->get_manager()->table_exists(self::TABLE)) {
                $record = $DB->get_record(self::TABLE, ['slug' => $slug], 'usecleanurls', IGNORE_MISSING);
                return !empty($record) && !empty($record->usecleanurls);
            }
        } catch (coding_exception $e) {
            // If the table does not exist yet just skip rewriting.
            return false;
        }

        return false;
    }

    /**
     * Adjust the current page URL when visiting a clean URL.
     *
     * @return void
     */
    public static function html_head_setup(): void {
        global $PAGE;

        $path = $PAGE->url->get_path();
        if ($path && !preg_match('#^/local/course_category_page/#', $path)) {
            $base = self::get_base_path();
            if ($base !== '' && str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base));
            }
            $slug = trim($path, '/');
            if ($slug && self::use_clean_url($slug)) {
                $PAGE->set_url('/local/course_category_page/view.php', ['slug' => $slug]);
            }
        }
    }

    /**
     * Get the base path from $CFG->wwwroot (empty for root installs).
     *
     * @return string
     */
    private static function get_base_path(): string {
        global $CFG;
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $path = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '';
        $path = rtrim($path, '/');
        $base = $path === '' ? '' : $path;

        return $base;
    }
}
