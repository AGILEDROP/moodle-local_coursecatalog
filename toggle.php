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
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);
// Use optional_param so that passing 0 is distinct from not passing at all.
$isenabled = optional_param('isenabled', null, PARAM_INT);
$showinnav = optional_param('showinprimarynavigation', null, PARAM_INT);
$guestaccessible = optional_param('guestaccessible', null, PARAM_INT);

require_login();
$syscontext = context_system::instance();
require_capability('local/coursecatalog:manage', $syscontext);
require_sesskey();

$messages = \local_coursecatalog\manager::toggle_page($id, $isenabled, $showinnav, $guestaccessible);

redirect(
    new moodle_url('/local/coursecatalog/pages.php'),
    implode(' ', $messages)
);
