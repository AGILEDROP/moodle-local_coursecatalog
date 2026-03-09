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
 * @package local_coursecatalog
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__.'/classes/form/addpage_form.php');
require_once(__DIR__.'/locallib.php');

require_login();
$syscontext = context_system::instance();
require_capability('local/coursecatalog:manage', $syscontext);

$PAGE->set_url(new moodle_url('/local/coursecatalog/pages.php'));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('managepages', 'local_coursecatalog'));
$PAGE->set_heading(get_string('managepages', 'local_coursecatalog'));

// 1) Handle the add‐page form.
$form = new \local_coursecatalog\form\addpage_form();
if ($form->is_cancelled()) {
    redirect($PAGE->url);
} else if ($data = $form->get_data()) {
    global $DB;
    $desc = $data->description ?? ['text' => '', 'format' => FORMAT_HTML];
    $descriptiontext = is_array($desc) && array_key_exists('text', $desc) ? $desc['text'] : '';
    $descriptionformat = is_array($desc) && array_key_exists('format', $desc) ? (int)$desc['format'] : FORMAT_HTML;

    $record = (object)[
        'name' => $data->name,
        'slug' => $data->slug,
        'course_category' => $data->course_category,
        'pagedescription' => $descriptiontext,
        'pagedescriptionformat' => $descriptionformat,
        'isenabled' => 0,
        'timecreated' => time(),
        'timeupdated' => time(),
        'showinprimarynavigation' => 0,
    ];
    $DB->insert_record('local_coursecatalog', $record);
    redirect($PAGE->url);
}

echo $OUTPUT->header();

// 2) Display the form.
$form->display();

// 3) Fetch all pages.
$pages  = local_coursecatalog_get_all_pages();
$catlist = \core_course_category::make_categories_list();

echo html_writer::start_div('course-catalog-cards');
foreach ($pages as $page) {
    echo html_writer::start_div('card mb-2 p-3 border');
    $actionsesskey = sesskey();

    // Title + created date.
    echo html_writer::tag('h3', format_string($page->name));
    echo html_writer::tag('small',
            get_string('createdon', 'local_coursecatalog',
                    userdate($page->timecreated)),
            ['class' => 'text-muted']
    );

    echo html_writer::tag('small',
            get_string('lastupdated', 'local_coursecatalog',
                    userdate($page->timeupdated)),
            ['class' => 'text-muted']
    );

    $categoryid = (int)$page->course_category;
    $categoryexists = array_key_exists($categoryid, $catlist);
    $categoryname = $categoryexists
            ? $catlist[$categoryid]
            : get_string('deletedcategorylabel', 'local_coursecatalog', $categoryid);

    // Category & courses count.
    echo html_writer::tag('p',
            get_string('coursecategory', 'local_coursecatalog')
            .': '.$categoryname
    );
    // Count only visible courses.
    $count = $categoryexists ? $DB->count_records('course', [
            'category' => $categoryid,
            'visible' => 1,
    ]) : 0;
    echo html_writer::tag('p',
            get_string('coursescount', 'local_coursecatalog', $count)
    );

    // Slug & status.
    echo html_writer::tag('p',
            get_string('urlslug', 'local_coursecatalog')
            .': /'.$page->slug
    );

    // Status.
    $statusbadge = !empty($page->isenabled)
            ? '<span class="badge badge-success">'.get_string('enablepage','local_coursecatalog').'</span>'
            : '<span class="badge badge-secondary">'.get_string('disablepage','local_coursecatalog').'</span>';

    $navbadge = !empty($page->showinprimarynavigation)
            ? '<span class="badge badge-success">'.get_string('navenabled','local_coursecatalog').'</span>'
            : '<span class="badge badge-secondary">'.get_string('navdisabled','local_coursecatalog').'</span>';

    echo html_writer::div(
            get_string('status', 'local_coursecatalog') . ': ' . $statusbadge . ' ' . $navbadge,
            'mb-2'
    );

    // Action buttons container.
    echo html_writer::start_div('d-flex justify-content-between align-items-center mb-2');

    echo html_writer::start_div();
    // 1) View page.
    $viewurl = new moodle_url('/local/coursecatalog/view.php', ['slug' => $page->slug]);
    echo html_writer::link($viewurl,
            get_string('viewpage', 'local_coursecatalog'),
            ['class' => 'btn btn-primary btn-sm mr-2']
    );

    // 4) Enable/Disable the page.
    $toggleenabledurl = new moodle_url('/local/coursecatalog/toggle.php', [
            'id' => $page->id,
            'isenabled' => empty($page->isenabled) ? 1 : 0,
            'sesskey' => $actionsesskey,
    ]);
    $toggleenabledtext = empty($page->isenabled)
            ? get_string('toggleenablepage', 'local_coursecatalog')
            : get_string('toggledisablepage', 'local_coursecatalog');
    $toggleenabledclasses = ['class' => 'btn btn-warning btn-sm mr-2'];
    echo html_writer::link($toggleenabledurl, $toggleenabledtext, $toggleenabledclasses);

    // 5) Enable/Disable category page link in primary navigation.
    $toggleenablenavigationurl = new moodle_url('/local/coursecatalog/toggle.php', [
            'id' => $page->id,
            'showinprimarynavigation' => empty($page->showinprimarynavigation) ? 1 : 0,
            'sesskey' => $actionsesskey,
    ]);
    $toggleenablenavigationtext = empty($page->showinprimarynavigation)
            ? get_string('toggleenableinnavigation', 'local_coursecatalog')
            : get_string('toggledisableinnavigation', 'local_coursecatalog');

    $toggleenablenavigationclasses = ['class' => 'btn btn-info btn-sm mr-2'];
    if (empty($page->isenabled)) {
        $toggleenablenavigationclasses['class'] .= ' disabled';
    }
    echo html_writer::link($toggleenablenavigationurl, $toggleenablenavigationtext, $toggleenablenavigationclasses);

    if (empty($page->isenabled) && !empty($page->showinprimarynavigation)) {
        echo html_writer::tag(
                'small',
                get_string('navnote_disabled', 'local_coursecatalog'),
                ['class' => 'text-warning d-block mt-1']
        );
    }

    echo html_writer::end_div();

    echo html_writer::start_div();
    // 7) Edit button.
    $editurl = new moodle_url('/local/coursecatalog/edit.php', ['id' => $page->id]);
    echo html_writer::link(
            $editurl,
            get_string('editpage', 'local_coursecatalog'),
            ['class' => 'btn btn-secondary btn-sm mr-2']
    );
    // 8) Delete button.
    $deleteurl = new moodle_url('/local/coursecatalog/delete.php', [
        'id' => $page->id,
        'sesskey' => $actionsesskey,
    ]);

    echo html_writer::link(
        $deleteurl,
        get_string('delete', 'core'),
        [
            'class' => 'btn btn-danger btn-sm ml-auto',
            'data-modal' => 'confirmation',
            'data-modal-type' => 'delete',
            'data-modal-title-str' => json_encode(['delete', 'core']),
            'data-modal-content-str' => json_encode(['deletepageconfirm', 'local_coursecatalog', format_string($page->name)]),
            'data-modal-yes-button-str' => json_encode(['delete', 'core']),
            'data-modal-destination' => $deleteurl->out(false),
        ]
    );
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo $OUTPUT->footer();
