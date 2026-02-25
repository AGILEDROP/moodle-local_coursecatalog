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
 * Plugin settings for the local_course_category_page plugin.
 *
 * @package local_course_category_page
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
            'local_course_category_page',
            get_string('pluginname', 'local_course_category_page')
    ));

    $ADMIN->add('local_course_category_page', new admin_externalpage(
            'local_course_category_page_managepages',
            get_string('managepages', 'local_course_category_page'),
            new moodle_url('/local/course_category_page/pages.php'),
            'local/course_category_page:manage'
    ));
}
