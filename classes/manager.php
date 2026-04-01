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

/**
 * Manager class encapsulating business/data logic for course catalog pages.
 *
 * Extracted from toggle.php, pages.php, and edit.php so the logic
 * can be covered by PHPUnit without requiring HTTP entry points.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Toggle one or more boolean flags on a catalog page record.
     *
     * @param int      $id              Record id in {local_coursecatalog}.
     * @param int|null $isenabled       0 or 1, or null to leave unchanged.
     * @param int|null $showinnav       0 or 1, or null to leave unchanged.
     * @param int|null $guestaccessible 0 or 1, or null to leave unchanged.
     * @return string[] Success message strings (one per toggled flag).
     * @throws \invalid_parameter_exception If a value is not 0/1 or no field was provided.
     * @throws \moodle_exception If a constraint is violated (e.g. enabling nav on a disabled page).
     */
    public static function toggle_page(int $id, ?int $isenabled, ?int $showinnav, ?int $guestaccessible): array {
        global $DB;

        $record = (object)['id' => $id];
        $messages = [];

        if ($isenabled !== null) {
            if (!in_array($isenabled, [0, 1], true)) {
                throw new \invalid_parameter_exception('Invalid value for isenabled');
            }
            $record->isenabled = $isenabled;
            $messages[] = $isenabled
                ? get_string('enabledsuccess', 'local_coursecatalog')
                : get_string('disabledsuccess', 'local_coursecatalog');
        }

        if ($showinnav !== null) {
            if (!in_array($showinnav, [0, 1], true)) {
                throw new \invalid_parameter_exception('Invalid value for showinprimarynavigation');
            }
            $existing = $DB->get_record('local_coursecatalog', ['id' => $id], 'id, isenabled', MUST_EXIST);
            if ((int)$showinnav === 1 && empty($existing->isenabled)) {
                throw new \moodle_exception('cannotenablenavwhendisabled', 'local_coursecatalog');
            }
            $record->showinprimarynavigation = $showinnav;
            $messages[] = $showinnav
                ? get_string('navenabledsuccess', 'local_coursecatalog')
                : get_string('navdisabledsuccess', 'local_coursecatalog');
        }

        if ($guestaccessible !== null) {
            if (!in_array($guestaccessible, [0, 1], true)) {
                throw new \invalid_parameter_exception('Invalid value for guestaccessible');
            }
            $existing = $DB->get_record('local_coursecatalog', ['id' => $id], 'id, isenabled', MUST_EXIST);
            if ((int)$guestaccessible === 1 && empty($existing->isenabled)) {
                throw new \moodle_exception('cannotenableguestwhendisabled', 'local_coursecatalog');
            }
            $record->guestaccessible = $guestaccessible;
            $messages[] = $guestaccessible
                ? get_string('guestaccessenabledsuccess', 'local_coursecatalog')
                : get_string('guestaccessdisabledsuccess', 'local_coursecatalog');
        }

        if (empty($messages)) {
            throw new \invalid_parameter_exception('No toggle field was provided');
        }

        $DB->update_record('local_coursecatalog', $record);

        return $messages;
    }

    /**
     * Create a new catalog page record.
     *
     * @param \stdClass $data Form data with name, slug, course_category, and optional description.
     * @return int The id of the newly inserted record.
     */
    public static function create_page(\stdClass $data): int {
        global $DB;

        $record = (object)[
            'name' => $data->name,
            'slug' => $data->slug,
            'course_category' => $data->course_category,
            'pagedescription' => $data->pagedescription ?? '',
            'pagedescriptionformat' => $data->pagedescriptionformat ?? FORMAT_HTML,
            'isenabled' => 0,
            'timecreated' => time(),
            'timeupdated' => time(),
            'sortorder' => \local_coursecatalog_get_next_sortorder(),
            'showinprimarynavigation' => 0,
            'includesubcategories' => !empty($data->includesubcategories) ? 1 : 0,
        ];

        $pageid = $DB->insert_record('local_coursecatalog', $record);

        if (!empty($data->includesubcategories) && !empty($data->selectedsubcategories)) {
            self::save_selected_categories($pageid, $data->selectedsubcategories);
        }

        return $pageid;
    }

    /**
     * Update an existing catalog page record.
     *
     * @param int       $id   Record id.
     * @param \stdClass $data Form data with name, slug, course_category, and optional description.
     * @return bool True on success, false if the slug is already used by another record.
     */
    public static function update_page(int $id, \stdClass $data): bool {
        global $DB;

        if (
            $DB->record_exists_select(
                'local_coursecatalog',
                'slug = :slug AND id <> :id',
                ['slug' => $data->slug, 'id' => $id]
            )
        ) {
            return false;
        }

        $update = (object)[
            'id' => $id,
            'name' => $data->name,
            'slug' => $data->slug,
            'course_category' => $data->course_category,
            'pagedescription' => $data->pagedescription ?? '',
            'pagedescriptionformat' => $data->pagedescriptionformat ?? FORMAT_HTML,
            'includesubcategories' => !empty($data->includesubcategories) ? 1 : 0,
            'timeupdated' => time(),
        ];

        $DB->update_record('local_coursecatalog', $update);

        if (!empty($data->includesubcategories)) {
            self::save_selected_categories($id, $data->selectedsubcategories ?? []);
        } else {
            self::delete_selected_categories($id);
        }

        return true;
    }

    /**
     * Save selected subcategories for a catalog page.
     *
     * Replaces all existing selections with the provided set.
     *
     * @param int $pageid The catalog page ID.
     * @param array $categoryids Array of category IDs to associate.
     * @return void
     */
    public static function save_selected_categories(int $pageid, array $categoryids): void {
        global $DB;

        $DB->delete_records('local_coursecatalog_cats', ['pageid' => $pageid]);

        foreach ($categoryids as $categoryid) {
            $categoryid = (int)$categoryid;
            if ($categoryid <= 0) {
                continue;
            }
            $DB->insert_record('local_coursecatalog_cats', (object)[
                'pageid' => $pageid,
                'categoryid' => $categoryid,
            ]);
        }
    }

    /**
     * Delete all selected subcategories for a catalog page.
     *
     * @param int $pageid The catalog page ID.
     * @return void
     */
    public static function delete_selected_categories(int $pageid): void {
        global $DB;
        $DB->delete_records('local_coursecatalog_cats', ['pageid' => $pageid]);
    }

    /**
     * Get selected subcategory IDs for a catalog page.
     *
     * @param int $pageid The catalog page ID.
     * @return int[] Array of category IDs.
     */
    public static function get_selected_categories(int $pageid): array {
        global $DB;
        return $DB->get_fieldset_select(
            'local_coursecatalog_cats',
            'categoryid',
            'pageid = :pageid',
            ['pageid' => $pageid]
        );
    }

    /**
     * Remove stale subcategory selections from all pages.
     *
     * For each page with includesubcategories enabled and specific selections,
     * checks that every selected category is still a descendant of the page's
     * root category. Removes any that are not.
     *
     * @return void
     */
    public static function clean_stale_selections(): void {
        global $DB;

        $pages = $DB->get_records('local_coursecatalog', ['includesubcategories' => 1]);
        foreach ($pages as $page) {
            $selected = self::get_selected_categories((int)$page->id);
            if (empty($selected)) {
                continue;
            }

            $rootcategory = \core_course_category::get((int)$page->course_category, IGNORE_MISSING);
            if (!$rootcategory) {
                // Root category gone — clear all selections.
                self::delete_selected_categories((int)$page->id);
                continue;
            }

            $validchildren = array_map('intval', $rootcategory->get_all_children_ids());
            foreach ($selected as $catid) {
                if (!in_array((int)$catid, $validchildren, true)) {
                    $DB->delete_records('local_coursecatalog_cats', [
                        'pageid' => (int)$page->id,
                        'categoryid' => (int)$catid,
                    ]);
                }
            }
        }
    }
}
