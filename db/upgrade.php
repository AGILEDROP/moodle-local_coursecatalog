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

/**
 * Upgrade steps for local_coursecatalog.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_coursecatalog upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_coursecatalog_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026030300) {
        $table = new xmldb_table('local_coursecatalog');

        // 1) Normalise legacy values before changing the field type.
        // Keep strictly numeric category ids only and drop rows pointing to deleted categories.
        $validcategoryids = $DB->get_records_menu('course_categories', null, '', 'id, id');
        $recordset = $DB->get_recordset('local_coursecatalog', null, '', 'id, course_category');
        foreach ($recordset as $record) {
            $rawvalue = trim((string)$record->course_category);
            $cleanvalue = clean_param($rawvalue, PARAM_INT);

            if ($rawvalue === '' || $cleanvalue === '' || !preg_match('/^\d+$/', $rawvalue) || (int)$cleanvalue <= 0) {
                $DB->delete_records('local_coursecatalog', ['id' => $record->id]);
                continue;
            }

            if (!isset($validcategoryids[(int)$cleanvalue])) {
                $DB->delete_records('local_coursecatalog', ['id' => $record->id]);
                continue;
            }

            if ((string)$record->course_category !== (string)(int)$cleanvalue) {
                $DB->set_field('local_coursecatalog', 'course_category', (int)$cleanvalue, ['id' => $record->id]);
            }
        }
        $recordset->close();

        // 2) Fail fast if duplicate slugs exist. Resolve these manually before upgrade continues.
        $duplicatesql = "SELECT slug
                           FROM {local_coursecatalog}
                       GROUP BY slug
                         HAVING COUNT(*) > 1";
        $duplicates = $DB->get_fieldset_sql($duplicatesql);

        if (!empty($duplicates)) {
            $preview = implode(', ', array_slice($duplicates, 0, 10));
            throw new upgrade_exception(
                'local_coursecatalog',
                2026030300,
                "Duplicate slugs found in local_coursecatalog: {$preview}"
            );
        }

        // 3) Change course_category from char to int.
        $field = new xmldb_field('course_category', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'slug');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        // 4) Add a unique key for slug.
        $slugkey = new xmldb_key('slug_uniq', XMLDB_KEY_UNIQUE, ['slug']);
        if (!$dbman->find_key_name($table, $slugkey)) {
            $dbman->add_key($table, $slugkey);
        }

        // 5) Add an index for course_category.
        $categoryindex = new xmldb_index('course_category_ix', XMLDB_INDEX_NOTUNIQUE, ['course_category']);
        if (!$dbman->index_exists($table, $categoryindex)) {
            $dbman->add_index($table, $categoryindex);
        }

        upgrade_plugin_savepoint(true, 2026030300, 'local', 'coursecatalog');
    }

    if ($oldversion < 2026030900) {
        $table = new xmldb_table('local_coursecatalog');
        $field = new xmldb_field('usecleanurls');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026030900, 'local', 'coursecatalog');
    }

    return true;
}
