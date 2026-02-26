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
 * @package local_coursecatalog
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__.'/locallib.php');

$slug = required_param('slug', PARAM_ALPHANUMEXT);
global $DB, $OUTPUT;

// 1) Load the record
$page = $DB->get_record('local_coursecatalog',
        ['slug' => $slug], '*', MUST_EXIST);

// 2) Context & base login/cap checks
$catcontext = context_coursecat::instance($page->course_category);
require_login();
require_capability('local/coursecatalog:view', $catcontext);

// 3) Handle the “disabled” flag
if (empty($page->isenabled)) {
    // 3a) Ordinary users get a friendly landing page
    if (!has_capability('local/coursecatalog:manage', context_system::instance())) {
        $PAGE->set_url(new moodle_url('/local/coursecatalog/view.php', ['slug' => $slug]));
        $PAGE->set_context($catcontext);
        $PAGE->set_title(get_string('pluginname', 'local_coursecatalog'));
        $PAGE->set_heading(get_string('pluginname', 'local_coursecatalog'));

        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('pagenotenableduser', 'local_coursecatalog'),
                \core\output\notification::NOTIFY_INFO);
        echo $OUTPUT->footer();
        exit;
    }
    // 3b) Managers see a banner but can continue
    $showpreviewbanner = true;
} else {
    $showpreviewbanner = false;
}

// 4) Normal page rendering
$url = new moodle_url('/local/coursecatalog/view.php', ['slug' => $slug]);
$PAGE->set_url($url);
$PAGE->set_secondary_navigation(false);
$PAGE->set_context($catcontext);
$PAGE->set_title(format_string($page->name));
$PAGE->set_heading(format_string($page->name));
$PAGE->navbar->add(
        $page->name ?? $page->slug,
        $url,
        'TYPE_COURSE_CATEGORY_PAGE'
);
$PAGE->navbar->make_active();

echo $OUTPUT->header();

// 5) If a preview banner is needed:
if (!empty($showpreviewbanner)) {
    echo $OUTPUT->notification(get_string('previewdisablednotice', 'local_coursecatalog'),
            \core\output\notification::NOTIFY_WARNING);
}

// 6) Output the HTML
$html = local_coursecatalog_display_cards($page);
echo $html;

echo $OUTPUT->footer();
