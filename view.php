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
 * Front-end script to display a course catalog.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

$slug = required_param('slug', PARAM_ALPHANUMEXT);
$html = \local_coursecatalog\catalog_page::render($slug);
if ($html === null) {
    http_response_code(404);
    $html = \local_coursecatalog\catalog_page::render_not_found($slug);
}
echo $html;
