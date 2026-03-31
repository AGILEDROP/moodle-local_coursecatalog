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
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/form/addpage_form.php');
require_once(__DIR__ . '/locallib.php');

require_login();
$syscontext = context_system::instance();
require_capability('local/coursecatalog:manage', $syscontext);

$id = required_param('id', PARAM_INT);

$PAGE->set_url(new moodle_url('/local/coursecatalog/edit.php', ['id' => $id]));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('editpage', 'local_coursecatalog'));
$PAGE->set_heading(get_string('editpage', 'local_coursecatalog'));

global $DB;

if (!$page = $DB->get_record('local_coursecatalog', ['id' => $id], '*', IGNORE_MISSING)) {
    throw new \moodle_exception('invalidrecord', 'error');
}

$form = new \local_coursecatalog\form\addpage_form(
    null,
    ['isupdate' => true] // Tells the form to change the submit label.
);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/coursecatalog/pages.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();

    $editoroptions = ['maxfiles' => 0, 'maxbytes' => 0, 'context' => $syscontext];
    $data = file_postupdate_standard_editor(
        $data,
        'pagedescription',
        $editoroptions,
        $syscontext,
        'local_coursecatalog',
        'pagedescription',
        $id
    );

    if (!\local_coursecatalog\manager::update_page($id, $data)) {
        \core\notification::error(get_string('error:sluginuse', 'local_coursecatalog'));
        $form->set_data($data);
    } else {
        \core\notification::success(get_string('changessaved'));
        redirect(new moodle_url('/local/coursecatalog/pages.php'));
    }
}

// Prefill form.
$editoroptions = ['maxfiles' => 0, 'maxbytes' => 0, 'context' => $syscontext];
$defaults = new stdClass();
$defaults->id = $page->id;
$defaults->name = $page->name;
$defaults->slug = $page->slug;
$defaults->pagedescription = $page->pagedescription ?? '';
$defaults->pagedescriptionformat = $page->pagedescriptionformat ?? FORMAT_HTML;
$defaults->course_category = $page->course_category;
$defaults->includesubcategories = !empty($page->includesubcategories) ? 1 : 0;

$defaults = file_prepare_standard_editor(
    $defaults,
    'pagedescription',
    $editoroptions,
    $syscontext,
    'local_coursecatalog',
    'pagedescription',
    $defaults->id
);

echo $OUTPUT->header();
$form->set_data($defaults);
$form->display();
echo $OUTPUT->footer();
