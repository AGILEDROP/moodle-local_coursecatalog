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
 * Move a course catalog page up or down in display order.
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
$direction = required_param('direction', PARAM_ALPHA);

require_login();
$syscontext = context_system::instance();
require_capability('local/coursecatalog:manage', $syscontext);
require_sesskey();

if (!in_array($direction, ['up', 'down'], true)) {
    throw new invalid_parameter_exception('Invalid move direction');
}

$message = '';
if (local_coursecatalog_move_page($id, $direction)) {
    $message = get_string('movesuccess', 'local_coursecatalog');
}

redirect(new moodle_url('/local/coursecatalog/pages.php'), $message);
