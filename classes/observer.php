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
        self::purge_coursecards_cache();
    }

    /**
     * Purge all course card caches when a category is updated (e.g. moved).
     *
     * When a category moves, the old and new parent trees both change.
     * The event does not carry the old parent ID, so we purge the entire
     * course cards cache to guarantee correctness.
     *
     * @param \core\event\course_category_updated $event
     * @return void
     */
    public static function course_category_updated(\core\event\course_category_updated $event): void {
        self::purge_coursecards_cache();
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

        // Purge entire cache because custom subcategory selections (_sel_ keys)
        // cannot be enumerated for targeted deletion.
        self::purge_coursecards_cache();
    }

    /**
     * Purge the entire course cards cache.
     *
     * Used when changes could affect any page's cached data, e.g. when courses
     * or categories change and custom subcategory selections make targeted
     * invalidation impractical.
     *
     * @return void
     */
    private static function purge_coursecards_cache(): void {
        $cache = \cache::make('local_coursecatalog', 'coursecards');
        $cache->purge();
    }
}
