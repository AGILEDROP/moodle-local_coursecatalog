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
 * Page for managing custom pages
 *
 * @package local_course_category_page
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__.'/classes/form/addpage_form.php');
require_once(__DIR__.'/locallib.php');

require_login();
$syscontext = context_system::instance();
require_capability('local/course_category_page:manage', $syscontext);

$id = required_param('id', PARAM_INT);

$PAGE->set_url(new moodle_url('/local/course_category_page/edit.php', ['id' => $id]));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('editpage', 'local_course_category_page'));
$PAGE->set_heading(get_string('editpage', 'local_course_category_page'));

global $DB;

if (!$page = $DB->get_record('local_course_category_page', ['id' => $id], '*', IGNORE_MISSING)) {
    print_error('invalidrecord', 'error');
}

$form = new \local_course_category_page\form\addpage_form(
        null,
        ['isupdate' => true] // tells the form to change the submit label...
);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/course_category_page/pages.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();

    // Ensure slug uniqueness excluding current record.
    if ($exists = $DB->record_exists_select(
            'local_course_category_page',
            'slug = :slug AND id <> :id',
            ['slug' => $data->slug, 'id' => $id]
    )) {
        \core\notification::error(get_string('error:sluginuse', 'local_course_category_page'));
        // Re-display form with posted values.
        $form->set_data($data);
    } else {
        $desc = $data->description ?? ['text' => '', 'format' => FORMAT_HTML];

        $update = (object)[
                'id' => $id,
                'name' => $data->name,
                'slug' => $data->slug,
                'course_category' => $data->course_category,
                'pagedescription' => $desc['text'],
                'pagedescriptionformat' => $desc['format'] ?? FORMAT_HTML,
                'timeupdated' => time(),
        ];
        $DB->update_record('local_course_category_page', $update);
        \core\notification::success(get_string('changessaved'));
        redirect(new moodle_url('/local/course_category_page/pages.php'));
    }
}

// Prefill form.
$defaults = new stdClass();
$defaults->id = $page->id;
$defaults->name = $page->name;
$defaults->slug = $page->slug;
$defaults->description = [
        'text'   => $page->pagedescription ?? '',
        'format' => $page->pagedescriptionformat ?? FORMAT_HTML,
];
$defaults->course_category = $page->course_category;

echo $OUTPUT->header();
$form->set_data($defaults);
$form->display();
echo $OUTPUT->footer();
