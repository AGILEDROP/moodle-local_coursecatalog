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
 * Toggle the enabled/disabled and/or primary-nav visibility of a Course Category Page.
 *
 * Depending on which parameter is passed (isenabled or showinprimarynavigation),
 * this script will flip that flag in the DB and return the appropriate success message.
 *
 * @package    local_course_category_page
 * @copyright  2025 Matej Pal <matej.pal@agiledrop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);
// Use optional_param so that passing 0 is distinct from not passing at all.
$isenabled = optional_param('isenabled', null, PARAM_INT);
$showinnav = optional_param('showinprimarynavigation', null, PARAM_INT);
$usecleanurls = optional_param('usecleanurls', null, PARAM_INT);

require_login();
$syscontext = context_system::instance();
require_capability('local/course_category_page:manage', $syscontext);

global $DB;

// Build an object to update only the fields actually passed.
$record = (object)['id' => $id];
$messages = [];
if ($isenabled !== null) {
    $record->isenabled = $isenabled;
    $messages[] = $isenabled
            ? get_string('enabledsuccess', 'local_course_category_page')
            : get_string('disabledsuccess', 'local_course_category_page');
}
if ($showinnav !== null) {
    $record->showinprimarynavigation = $showinnav;
    $messages[] = $showinnav
            ? get_string('navenabledsuccess', 'local_course_category_page')
            : get_string('navdisabledsuccess', 'local_course_category_page');
}

if ($usecleanurls !== null) {
    $record->usecleanurls = $usecleanurls;
    $messages[] = $usecleanurls
            ? get_string('cleanurlsenabled', 'local_course_category_page')
            : get_string('cleanurlsdisabled', 'local_course_category_page');
}

// Do the update (will update only the properties you set above).
$DB->update_record('local_course_category_page', $record);

// Redirect back with all messages joined.
redirect(
        new moodle_url('/local/course_category_page/pages.php'),
        implode(' ', $messages)
);
