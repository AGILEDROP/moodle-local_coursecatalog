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

namespace local_coursecatalog;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Event observer callbacks for local_coursecatalog.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Remove orphaned catalog pages when a linked category is deleted.
     *
     * @param \core\event\course_category_deleted $event
     * @return void
     */
    public static function course_category_deleted(\core\event\course_category_deleted $event): void {
        $categoryid = (int)$event->objectid;

        if ($categoryid <= 0) {
            return;
        }

        local_coursecatalog_delete_by_category($categoryid);
        self::invalidate_cache_for_category($categoryid);
    }

    /**
     * Invalidate the course cards cache when a course or its content changes.
     *
     * Handles: course_created, course_updated, course_deleted, course_content_updated,
     * course_section_created, course_section_updated, course_section_deleted,
     * course_module_created, course_module_updated, course_module_deleted.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function course_changed(\core\event\base $event): void {
        $courseid = (int)$event->courseid;
        if ($courseid <= 0) {
            return;
        }

        global $DB;
        $categoryid = $DB->get_field('course', 'category', ['id' => $courseid]);
        if ($categoryid) {
            self::invalidate_cache_for_category((int)$categoryid);
        }
    }

    /**
     * Purge the course cards cache for a specific category.
     *
     * @param int $categoryid
     * @return void
     */
    private static function invalidate_cache_for_category(int $categoryid): void {
        $cache = \cache::make('local_coursecatalog', 'coursecards');
        $cache->delete($categoryid);
    }
}
