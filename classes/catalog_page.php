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

namespace local_coursecatalog;

use core\output\notification;
use context_coursecat;
use context_system;
use moodle_exception;

/**
 * Display a catalog page through either public entry point.
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog_page {
    /**
     * Render the catalog identified by a validated slug.
     *
     * @param string $slug Catalog page slug.
     * @return string|null Full HTML page, or null if the catalog does not exist.
     */
    public static function render(string $slug): ?string {
        global $DB, $OUTPUT, $PAGE;

        require_once(__DIR__ . '/../locallib.php');

        // Guest access depends on the catalog settings.
        $page = $DB->get_record(
            'local_coursecatalog',
            ['slug' => $slug],
            '*',
            IGNORE_MISSING
        );
        if (!$page) {
            return null;
        }

        if (!empty($page->guestaccessible)) {
            require_login(null, true);
        } else {
            require_login();
            if (isguestuser()) {
                throw new moodle_exception('noguest');
            }
        }

        $categoryid = (int)$page->course_category;
        $catcontext = context_coursecat::instance($categoryid, IGNORE_MISSING);

        if (!$catcontext) {
            throw new moodle_exception('missingcategorypage', 'local_coursecatalog');
        }

        $systemcontext = context_system::instance();
        require_capability('local/coursecatalog:view', $systemcontext);

        $showpreviewbanner = empty($page->isenabled);
        if ($showpreviewbanner && !has_capability('local/coursecatalog:manage', $systemcontext)) {
            $PAGE->set_url(local_coursecatalog_get_page_url($slug));
            $PAGE->set_context($catcontext);
            $PAGE->set_primary_active_tab('local_coursecatalog_' . $page->id);
            $PAGE->set_title(get_string('pluginname', 'local_coursecatalog'));
            $PAGE->set_heading(get_string('pluginname', 'local_coursecatalog'));

            return $OUTPUT->header() . $OUTPUT->notification(
                get_string('pagenotenableduser', 'local_coursecatalog'),
                notification::NOTIFY_INFO
            ) . $OUTPUT->footer();
        }

        $displayoptions = local_coursecatalog_get_display_options();
        $urlparams = $displayoptions;
        if ($urlparams['perpage'] === LOCAL_COURSECATALOG_PERPAGE_DEFAULT) {
            unset($urlparams['perpage']);
        }
        $url = local_coursecatalog_get_page_url($slug, $urlparams);
        $PAGE->set_url($url);
        $PAGE->set_secondary_navigation(false);
        $PAGE->set_context($catcontext);
        $PAGE->set_primary_active_tab('local_coursecatalog_' . $page->id);
        $name = format_string($page->name ?? $page->slug);
        $PAGE->set_title($name);
        $PAGE->set_heading($name);
        $PAGE->navbar->add($name, $url);
        $PAGE->navbar->make_active();

        $html = $OUTPUT->header();

        if ($showpreviewbanner) {
            $html .= $OUTPUT->notification(
                get_string('previewdisablednotice', 'local_coursecatalog'),
                notification::NOTIFY_WARNING
            );
        }

        try {
            $html .= local_coursecatalog_display_cards($page, $displayoptions);
        } catch (\Throwable $e) {
            $html .= $OUTPUT->notification(
                get_string('pagerenderfailed', 'local_coursecatalog'),
                notification::NOTIFY_ERROR
            );
            debugging('local_coursecatalog: failed to render page slug=' . $slug . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $html . $OUTPUT->footer();
    }

    /**
     * Render the error page for a catalog that does not exist.
     *
     * @param string $slug Requested catalog page slug.
     * @return string Full HTML page.
     */
    public static function render_not_found(string $slug): string {
        global $OUTPUT, $PAGE;

        require_once(__DIR__ . '/../locallib.php');

        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(local_coursecatalog_get_page_url($slug));
        $PAGE->set_cacheable(false);
        $name = get_string('pluginname', 'local_coursecatalog');
        $PAGE->set_title($name);
        $PAGE->set_heading($name);

        return $OUTPUT->header() . $OUTPUT->notification(
            get_string('pagenotenabled', 'local_coursecatalog'),
            notification::NOTIFY_ERROR
        ) . $OUTPUT->footer();
    }
}
