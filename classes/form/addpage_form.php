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
 * Form for adding Moodle pages.
 *
 * @package   local_course_category_page
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_course_category_page\form;

use context_system;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Class addpage_form.
 *
 * This class defines the form used for adding pages in the local_course_category_page plugin.
 *
 * @copyright 2025, Matej <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addpage_form extends \moodleform {

    /**
     * Define the form elements and structure.
     */
    public function definition() {
        $mform = $this->_form;
        $isupdate = !empty($this->_customdata['isupdate']);

        // Hidden id (used on edit).
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // 1) Page name
        $mform->addElement('text', 'name', get_string('pagename', 'local_course_category_page'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // 2) URL slug
        $mform->addElement('text', 'slug', get_string('pageslug', 'local_course_category_page'));
        $mform->setType('slug', PARAM_ALPHANUMEXT);
        $mform->addRule('slug', null, 'required', null, 'client');

        // 3) Page description
        $editoroptions = ['maxfiles' => 0, 'maxbytes' => 0, 'context' => context_system::instance()];
        $mform->addElement('editor', 'description', get_string('pagedescription', 'local_course_category_page'), null, $editoroptions);
        $mform->setType('description', PARAM_RAW);

        // 4) Category dropdown
        $categories = \core_course_category::make_categories_list();
        $mform->addElement('select', 'course_category',
                get_string('coursecategory', 'local_course_category_page'), $categories);
        $mform->addRule('course_category', null, 'required', null, 'client');

        // Submit
        $label = $isupdate ? get_string('savechanges') : get_string('addnewpage', 'local_course_category_page');
        $this->add_action_buttons(true, $label);
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        if (empty($data['id'])) {
            if ($DB->record_exists('local_course_category_page', ['slug' => $data['slug']])) {
                $errors['slug'] = get_string('error:sluginuse', 'local_course_category_page');
            }
        } else {
            if ($DB->record_exists_select(
                    'local_course_category_page',
                    'slug = :slug AND id <> :id',
                    ['slug' => $data['slug'], 'id' => $data['id']]
            )) {
                $errors['slug'] = get_string('error:sluginuse', 'local_course_category_page');
            }
        }

        return $errors;
    }

}
