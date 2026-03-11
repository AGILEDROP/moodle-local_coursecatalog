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
 * Hook callbacks for local_coursecatalog.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add enabled catalog pages to the site primary navigation.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $DB;

        $primaryview = $hook->get_primaryview();

        $pages = local_coursecatalog_get_primary_navigation_pages();

        foreach ($pages as $page) {
            $categoryid = (int)$page->course_category;
            $context = \context_coursecat::instance($categoryid, IGNORE_MISSING);

            // Skip orphaned rows or inaccessible pages.
            if (!$context || !has_capability('local/coursecatalog:view', $context)) {
                continue;
            }

            // Skip pages that are not guest-accessible for unauthenticated or guest users.
            if ((!isloggedin() || isguestuser()) && empty($page->guestaccessible)) {
                continue;
            }

            $url = new \moodle_url('/local/coursecatalog/view.php', [
                'slug' => $page->slug,
            ]);

            $primaryview->add(
                format_string($page->name, true, ['context' => $context]),
                $url,
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_coursecatalog_' . $page->id
            );
        }
    }
}
