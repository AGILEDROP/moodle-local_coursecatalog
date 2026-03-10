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
 * Delete a Course Category Page.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);

require_login();
$syscontext = context_system::instance();
require_capability('local/coursecatalog:manage', $syscontext);
require_sesskey();

// 1) Call your library function
local_coursecatalog_delete_page($id);

// 2) Redirect back with a notice
redirect(
    new moodle_url('/local/coursecatalog/pages.php'),
    get_string('deletedsuccess', 'local_coursecatalog'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
