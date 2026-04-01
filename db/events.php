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
 * Event observers for local_coursecatalog.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_category_deleted',
        'callback' => '\local_coursecatalog\observer::course_category_deleted',
    ],
    [
        'eventname' => '\core\event\course_category_updated',
        'callback' => '\local_coursecatalog\observer::course_category_updated',
    ],
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_content_updated',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_section_created',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_section_deleted',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_coursecatalog\observer::course_changed',
    ],
];
