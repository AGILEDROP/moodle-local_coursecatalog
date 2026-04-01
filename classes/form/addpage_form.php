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
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_coursecatalog\form;

use context_system;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Class addpage_form.
 *
 * This class defines the form used for adding pages in the local_coursecatalog plugin.
 *
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
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

        // 1) Page name.
        $mform->addElement('text', 'name', get_string('pagename', 'local_coursecatalog'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'pagename', 'local_coursecatalog');

        // 2) Slug.
        $mform->addElement('text', 'slug', get_string('pageslug', 'local_coursecatalog'));
        $mform->setType('slug', PARAM_ALPHANUMEXT);
        $mform->addRule('slug', null, 'required', null, 'client');
        $mform->addHelpButton('slug', 'pageslug', 'local_coursecatalog');

        // 3) Page description.
        $editoroptions = ['maxfiles' => 0, 'maxbytes' => 0, 'context' => context_system::instance()];
        $mform->addElement(
            'editor',
            'pagedescription_editor',
            get_string('pagedescription', 'local_coursecatalog'),
            null,
            $editoroptions
        );
        $mform->setType('pagedescription_editor', PARAM_RAW);
        $mform->addHelpButton('pagedescription_editor', 'pagedescription', 'local_coursecatalog');

        // 4) Category dropdown with empty default.
        $categories = ['' => get_string('choosedots')] + \core_course_category::make_categories_list();
        $mform->addElement(
            'select',
            'course_category',
            get_string('coursecategory', 'local_coursecatalog'),
            $categories,
            ['onchange' => 'document.getElementById("id_updatesubcategories").click();']
        );
        $mform->addRule('course_category', null, 'required', null, 'client');
        $mform->addHelpButton('course_category', 'coursecategory', 'local_coursecatalog');

        // Hidden no-submit button — triggered by onchange on the category dropdown.
        $mform->registerNoSubmitButton('updatesubcategories');
        $mform->addElement(
            'submit',
            'updatesubcategories',
            get_string('updatesubcategories', 'local_coursecatalog'),
            ['class' => 'd-none']
        );

        // 5) Include subcategories checkbox.
        $mform->addElement(
            'advcheckbox',
            'includesubcategories',
            get_string('includesubcategories', 'local_coursecatalog'),
            get_string('includesubcategories_label', 'local_coursecatalog')
        );
        $mform->addHelpButton('includesubcategories', 'includesubcategories', 'local_coursecatalog');

        // 6) Subcategory multi-select — options populated in definition_after_data().
        $mform->addElement(
            'autocomplete',
            'selectedsubcategories',
            get_string('selectedsubcategories', 'local_coursecatalog'),
            [],
            ['multiple' => true]
        );
        $mform->addHelpButton('selectedsubcategories', 'selectedsubcategories', 'local_coursecatalog');
        $mform->hideIf('selectedsubcategories', 'includesubcategories', 'notchecked');

        // Submit button.
        $label = $isupdate ? get_string('savechanges') : get_string('addnewpage', 'local_coursecatalog');
        $this->add_action_buttons(true, $label);
    }

    /**
     * Populate the subcategory multi-select with children of the selected root category.
     */
    public function definition_after_data() {
        $mform = $this->_form;

        // The getElementValue returns an array for select elements.
        $rawvalue = $mform->getElementValue('course_category');
        $rootcategoryid = (int)(is_array($rawvalue) ? reset($rawvalue) : $rawvalue);
        if ($rootcategoryid <= 0) {
            return;
        }

        $rootcategory = \core_course_category::get($rootcategoryid, IGNORE_MISSING);
        if (!$rootcategory) {
            return;
        }

        // Build a flat list of all descendant categories.
        $allcategories = \core_course_category::make_categories_list();
        $childrenids = $rootcategory->get_all_children_ids();
        $subcatoptions = [];
        foreach ($childrenids as $childid) {
            if (isset($allcategories[$childid])) {
                $subcatoptions[$childid] = $allcategories[$childid];
            }
        }

        // Populate options directly on the existing element.
        $element = $mform->getElement('selectedsubcategories');
        $element->_options = [];
        foreach ($subcatoptions as $value => $label) {
            $element->addOption($label, $value);
        }
    }

    /**
     * Validate the form data.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        if (empty($data['id'])) {
            if ($DB->record_exists('local_coursecatalog', ['slug' => $data['slug']])) {
                $errors['slug'] = get_string('error:sluginuse', 'local_coursecatalog');
            }
        } else {
            if (
                $DB->record_exists_select(
                    'local_coursecatalog',
                    'slug = :slug AND id <> :id',
                    ['slug' => $data['slug'], 'id' => $data['id']]
                )
            ) {
                $errors['slug'] = get_string('error:sluginuse', 'local_coursecatalog');
            }
        }

        return $errors;
    }
}
