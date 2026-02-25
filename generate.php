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
 * Generate (or regenerate) the HTML for a course‐category page.
 *
 * @package    local_coursecatalog
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT);

// 1) Page setup before any output:
$PAGE->set_url(new moodle_url('/local/coursecatalog/generate.php', ['id' => $id]));
$syscontext = context_system::instance();
$PAGE->set_context($syscontext);

// 2) Access checks:
require_login();
require_capability('local/coursecatalog:manage', $syscontext);

// 3) Load the page record
global $DB;
$page = $DB->get_record('local_coursecatalog', ['id' => $id], '*', MUST_EXIST);

// 4) Delegate to your locallib helper
local_course_category_page_generate_and_save($page);

// 5) Redirect back with a notice
redirect(
        new moodle_url('/local/coursecatalog/pages.php'),
        get_string('generatedsuccess', 'local_coursecatalog')
);
