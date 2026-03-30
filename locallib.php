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
 * Local course catalog plugin - Core library functions
 *
 * @package   local_coursecatalog
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_course\external\course_summary_exporter;

/** @var int Default number of courses per page. */
define('LOCAL_COURSECATALOG_PERPAGE_DEFAULT', 6);

/**
 * Display the cards for one category-page record with pagination.
 *
 * @param stdClass $page A record from {local_coursecatalog}, with at least id, course_category, name, slug.
 * @return string Rendered HTML.
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 */
function local_coursecatalog_display_cards(stdClass $page): string {
    global $OUTPUT;

    $sort = optional_param('sort', 'name_asc', PARAM_ALPHANUMEXT);
    $view = optional_param('view', 'grid', PARAM_ALPHA);
    $currentpage = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', LOCAL_COURSECATALOG_PERPAGE_DEFAULT, PARAM_INT);

    if (!in_array($view, ['grid', 'list'], true)) {
        $view = 'grid';
    }
    $currentpage = max(0, $currentpage);
    $perpage = max(1, min($perpage, 100));

    $descriptioncontext = \context_coursecat::instance(
        (int)$page->course_category,
        IGNORE_MISSING
    ) ?: \context_system::instance();
    $formatteddescription = format_text(
        (string)($page->pagedescription ?? ''),
        (int)($page->pagedescriptionformat ?? FORMAT_HTML),
        ['context' => $descriptioncontext]
    );

    $categoryid = (int)$page->course_category;
    $category = \core_course_category::get($categoryid, IGNORE_MISSING);

    $baseparams = ['slug' => $page->slug, 'sort' => $sort, 'view' => $view];

    if (!$category) {
        $ctx = (object)[
            'courses' => [],
            'coursecount' => local_coursecatalog_get_course_count_string(0),
            'pagedescription' => $formatteddescription,
            'sort' => local_coursecatalog_build_sort_context($page->slug, $sort, $view),
            'view' => $view,
            'isgrid' => ($view === 'grid'),
            'islist' => ($view === 'list'),
            'gridurl' => (new moodle_url(
                '/local/coursecatalog/view.php',
                array_merge($baseparams, ['view' => 'grid'])
            ))->out(false),
            'listurl' => (new moodle_url(
                '/local/coursecatalog/view.php',
                array_merge($baseparams, ['view' => 'list'])
            ))->out(false),
            'pagingbar' => '',
            'missingcategory' => true,
        ];
        return $OUTPUT->render_from_template('local_coursecatalog/coursecatalog', $ctx);
    }

    // 1) Get cached course card data.
    $allcards = local_coursecatalog_get_cached_cards($categoryid, $category);

    // 2) Sort the cards.
    $allcards = local_coursecatalog_sort_courses($allcards, $sort);

    // 3) Total count before pagination.
    $totalcount = count($allcards);

    // 4) Slice for current page.
    $pagecards = array_slice($allcards, $currentpage * $perpage, $perpage);

    // 5) Add user-specific enrollment data to the sliced set only.
    $pagecards = local_coursecatalog_add_enrollment_data($pagecards);

    // 6) Build paging bar.
    $pagingbarurl = new moodle_url('/local/coursecatalog/view.php', $baseparams);
    $pagingbar = $OUTPUT->paging_bar($totalcount, $currentpage, $perpage, $pagingbarurl);

    // 7) Build the Mustache context.
    $ctx = (object)[
        'courses' => $pagecards,
        'coursecount' => local_coursecatalog_get_course_count_string($totalcount),
        'pagedescription' => $formatteddescription,
        'sort' => local_coursecatalog_build_sort_context($page->slug, $sort, $view),
        'view' => $view,
        'isgrid' => ($view === 'grid'),
        'islist' => ($view === 'list'),
        'gridurl' => (new moodle_url(
            '/local/coursecatalog/view.php',
            array_merge($baseparams, ['view' => 'grid'])
        ))->out(false),
        'listurl' => (new moodle_url(
            '/local/coursecatalog/view.php',
            array_merge($baseparams, ['view' => 'list'])
        ))->out(false),
        'pagingbar' => $pagingbar,
    ];

    return $OUTPUT->render_from_template('local_coursecatalog/coursecatalog', $ctx);
}

/**
 * Get course card data from cache, or build and cache it.
 *
 * Returns user-independent card data (no enrollment info). The cache is keyed
 * by category ID and shared across all users (APPLICATION mode).
 *
 * @param int $categoryid The course category ID.
 * @param \core_course_category $category The category object.
 * @return array Array of course card objects.
 */
function local_coursecatalog_get_cached_cards(int $categoryid, \core_course_category $category): array {
    $cache = cache::make('local_coursecatalog', 'coursecards');
    $cards = $cache->get($categoryid);

    if ($cards !== false) {
        return $cards;
    }

    $cards = local_coursecatalog_build_cards($category);
    $cache->set($categoryid, $cards);

    return $cards;
}

/**
 * Build course card data for all visible courses in a category.
 *
 * Produces user-independent card objects suitable for caching. Enrollment
 * status (buttontext, buttonurl) is NOT included here — it is added per
 * request by {@see local_coursecatalog_add_enrollment_data()}.
 *
 * @param \core_course_category $category The category to build cards for.
 * @return array Array of course card objects.
 */
function local_coursecatalog_build_cards(\core_course_category $category): array {
    global $DB;

    // Fetch only visible courses at the DB level.
    $sql = "SELECT c.*
              FROM {course} c
             WHERE c.category = :catid
               AND c.visible = 1
          ORDER BY c.fullname ASC";
    $courses = $DB->get_records_sql($sql, ['catid' => $category->id]);

    $cards = [];
    foreach ($courses as $c) {
        $courseimageurl = course_summary_exporter::get_course_image($c);
        $modulescount = local_coursecatalog_count_modules($c->id);
        $activitycount = local_coursecatalog_count_main_activities($c->id);

        $cards[] = (object)[
            'courseid' => (int)$c->id,
            'fullname' => format_string($c->fullname),
            'courseimage' => $courseimageurl,
            'summary' => shorten_text(strip_tags(format_text($c->summary, $c->summaryformat)), 500),
            'modulescount' => local_coursecatalog_modules_label($modulescount),
            'modulescount_int' => $modulescount,
            'activitycount' => local_coursecatalog_modules_label($activitycount, 'activity'),
        ];
    }

    return $cards;
}

/**
 * Add user-specific enrollment data to course cards.
 *
 * Adds buttontext and buttonurl to each card based on the current user's
 * enrollment status.
 *
 * @param array $cards Array of course card objects (must have courseid).
 * @return array The same array with enrollment fields added.
 */
function local_coursecatalog_add_enrollment_data(array $cards): array {
    foreach ($cards as $card) {
        $coursectx = context_course::instance($card->courseid, IGNORE_MISSING);
        $isenrolled = $coursectx ? is_enrolled($coursectx) : false;

        $card->buttontext = $isenrolled
            ? get_string('start', 'local_coursecatalog')
            : get_string('enrolme', 'local_coursecatalog');
        $card->buttonurl = (
            $isenrolled
                ? new moodle_url('/course/view.php', ['id' => $card->courseid])
                : new moodle_url('/enrol/index.php', ['id' => $card->courseid])
        )->out(false);
    }

    return $cards;
}

/**
 * Return all configured category pages in display order.
 *
 * @return stdClass[] keyed by id
 */
function local_coursecatalog_get_all_pages() {
    global $DB;
    return $DB->get_records('local_coursecatalog', null, 'sortorder ASC, id ASC');
}

/**
 * Return the next available sort order value.
 *
 * @return int
 */
function local_coursecatalog_get_next_sortorder(): int {
    global $DB;

    $maxsortorder = $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {local_coursecatalog}');
    return (int)$maxsortorder + 1;
}

/**
 * Normalise sort order values into a contiguous sequence.
 *
 * @return void
 */
function local_coursecatalog_normalise_sortorder(): void {
    global $DB;

    $pages = $DB->get_records('local_coursecatalog', null, 'sortorder ASC, id ASC', 'id, sortorder');
    $expected = 1;

    foreach ($pages as $page) {
        if ((int)$page->sortorder !== $expected) {
            $DB->set_field('local_coursecatalog', 'sortorder', $expected, ['id' => $page->id]);
        }
        $expected++;
    }
}

/**
 * Move a catalog page one position up or down.
 *
 * @param int $pageid
 * @param string $direction Either "up" or "down".
 * @return bool True if the page was moved, false if no move was possible.
 */
function local_coursecatalog_move_page(int $pageid, string $direction): bool {
    global $DB;

    if (!in_array($direction, ['up', 'down'], true)) {
        throw new invalid_parameter_exception('Invalid move direction');
    }

    if (!$DB->record_exists('local_coursecatalog', ['id' => $pageid])) {
        return false;
    }

    $transaction = $DB->start_delegated_transaction();

    local_coursecatalog_normalise_sortorder();

    $pages = array_values($DB->get_records('local_coursecatalog', null, 'sortorder ASC, id ASC', 'id, sortorder'));
    $currentindex = null;

    foreach ($pages as $index => $page) {
        if ((int)$page->id === $pageid) {
            $currentindex = $index;
            break;
        }
    }

    if ($currentindex === null) {
        $transaction->allow_commit();
        return false;
    }

    $targetindex = $direction === 'up' ? $currentindex - 1 : $currentindex + 1;
    if (!array_key_exists($targetindex, $pages)) {
        $transaction->allow_commit();
        return false;
    }

    $currentpage = $pages[$currentindex];
    $targetpage = $pages[$targetindex];
    $now = time();

    $DB->set_field('local_coursecatalog', 'sortorder', (int)$targetpage->sortorder, ['id' => $currentpage->id]);
    $DB->set_field('local_coursecatalog', 'sortorder', (int)$currentpage->sortorder, ['id' => $targetpage->id]);
    $DB->set_field('local_coursecatalog', 'timeupdated', $now, ['id' => $currentpage->id]);
    $DB->set_field('local_coursecatalog', 'timeupdated', $now, ['id' => $targetpage->id]);

    $transaction->allow_commit();
    return true;
}

/**
 * Delete all pages for a given course_category id.
 * (Useful for your event observer when a category is removed.)
 *
 * @param int $categoryid
 * @return void
 */
function local_coursecatalog_delete_by_category(int $categoryid) {
    global $DB;
    $DB->delete_records('local_coursecatalog', ['course_category' => $categoryid]);
}

/**
 * Delete one category‐page record.
 *
 * @param int $id
 * @return void
 */
function local_coursecatalog_delete_page(int $id) {
    global $DB;
    $DB->delete_records('local_coursecatalog', ['id' => $id]);
}


/**
 * Count the number of user-visible sections ("modules/sections") in a course.
 *
 * A section is counted if:
 *  - It is uservisible for the current user, and
 *  - It has at least one of: activities/resources, a non-empty name, or a non-empty summary.
 *
 * Note: By default, section 0 ("General") IS included; flip $includegeneral inside
 * the function to exclude it.
 *
 * @param int $courseid Moodle course id.
 * @return int Number of sections meeting the criteria.
 */
function local_coursecatalog_count_modules(int $courseid): int {
    try {
        $modinfo = get_fast_modinfo($courseid);
    } catch (\Exception $e) {
        $msg = 'local_coursecatalog: get_fast_modinfo failed for course ' . $courseid . ': ' . $e->getMessage();
        debugging($msg, DEBUG_DEVELOPER);
        return 0;
    }
    $sections = $modinfo->get_section_info_all();

    $includegeneral = true; // Set to false to exclude section 0 ("General").

    $modulescount = 0;
    foreach ($sections as $secnum => $sec) {
        if (!$sec->visible) {
            continue; // Hidden or not available to the current user.
        }

        if (!$includegeneral && $secnum === 0) {
            continue;
        }

        // Skip subsections (delegated sections with a component).
        if (!empty($sec->component)) {
            continue;
        }

        // Count only sections that actually have content (name/summary or modules).
        $hasmods = !empty($modinfo->sections[$secnum] ?? []);
        $hastitle = trim((string)$sec->name) !== '';
        $hassummary = trim(strip_tags((string)$sec->summary ?? '')) !== '';

        if ($hasmods || $hastitle || $hassummary) {
            // Check if the section exclusively contains quiz, feedback, or customcert activities.
            if ($hasmods) {
                $hasotheractivities = false;
                $cmids = $modinfo->sections[$secnum];

                foreach ($cmids as $cmid) {
                    $cm = $modinfo->get_cm($cmid);

                    if (!$cm->visible) {
                        continue;
                    }

                    // Check if this is an activity other than quiz, feedback, or customcert.
                    if (!in_array($cm->modname, ['quiz', 'feedback', 'customcert'])) {
                        $hasotheractivities = true;
                        break;
                    }
                }

                // Skip this section if it only has quiz, feedback, or customcert activities.
                if (!$hasotheractivities) {
                    continue;
                }
            }

            $modulescount++;
        }
    }

    return $modulescount;
}

/**
 * Build a human-readable course count label for the page header.
 *
 * @param int $coursescount Number of visible courses.
 * @return string
 */
function local_coursecatalog_get_course_count_string(int $coursescount): string {
    return get_string('coursescount', 'local_coursecatalog', $coursescount);
}

/**
 * Build Mustache context for the "Sort by" UI.
 *
 * @param string $slug Current page slug (kept in a hidden field).
 * @param string $current Current sort token (e.g. "name_asc").
 * @param string $view Current view mode ('grid' or 'list').
 * @return array
 */
function local_coursecatalog_build_sort_context(string $slug, string $current, string $view = 'grid'): array {
    $baseurl = new moodle_url('/local/coursecatalog/view.php', ['slug' => $slug]);
    $options = [
        'name_asc' => get_string('sort_name_asc', 'local_coursecatalog'),
        'name_desc' => get_string('sort_name_desc', 'local_coursecatalog'),
        'modules_asc' => get_string('sort_modules_asc', 'local_coursecatalog'),
        'modules_desc' => get_string('sort_modules_desc', 'local_coursecatalog'),
    ];

    $ctx = [
        'action' => $baseurl->out(false),
        'slug' => $slug,
        'view' => $view,
        'options' => [],
    ];
    foreach ($options as $value => $label) {
        $ctx['options'][] = [
            'value' => $value,
            'label' => $label,
            'selected' => ($value === $current),
        ];
    }
    return $ctx;
}

/**
 * Sort the prepared course items according to a sort token.
 *
 * Secondary tie-break is always by name ASC for stability.
 *
 * Expects each item to be an object with:
 *  - ->fullname (string) for name sorting and tie-break
 *  - ->modulescount_int (int) for modules sorting
 *
 * @param stdClass[] $items A list of course view-models to sort.
 * @param string $sort Sort token.
 * @return stdClass[] Sorted list.
 */
function local_coursecatalog_sort_courses(array $items, string $sort): array {
    $allowed = ['name_asc', 'name_desc', 'modules_asc', 'modules_desc'];
    if (!in_array($sort, $allowed, true)) {
        $sort = 'name_asc';
    }
    [$field, $dir] = array_pad(explode('_', $sort, 2), 2, 'asc');
    $mult = ($dir === 'desc') ? -1 : 1;

    usort($items, function ($a, $b) use ($field, $mult) {
        switch ($field) {
            case 'name':
                $va = core_text::strtolower($a->fullname ?? '');
                $vb = core_text::strtolower($b->fullname ?? '');
                break;
            case 'modules':
                $va = (int)($a->modulescount_int ?? 0);
                $vb = (int)($b->modulescount_int ?? 0);
                break;
            default:
                $va = 0;
                $vb = 0;
        }

        if ($va === $vb) {
            // Secondary sort by name ASC for stability.
            $na = core_text::strtolower($a->fullname ?? '');
            $nb = core_text::strtolower($b->fullname ?? '');
            return $na <=> $nb;
        }
        return ($va < $vb ? -1 : 1) * $mult;
    });

    return $items;
}

/**
 * Build a localised label for the module/section or activity count.
 *
 * For modules, uses the language strings:
 *  - local_coursecatalog:modulecount_singular
 *  - local_coursecatalog:modulecount_plural
 *
 * For activities, uses the language strings:
 *  - local_coursecatalog:activitycount_singular
 *  - local_coursecatalog:activitycount_plural
 *
 * @param int $count Number of modules/sections or activities.
 * @param string $type Type of count: 'module' (default) or 'activity'.
 * @return string Localised label including the count.
 */
function local_coursecatalog_modules_label(int $count, string $type = 'module'): string {
    $singular = $type === 'activity' ? 'activitycount_singular' : 'modulecount_singular';
    $plural = $type === 'activity' ? 'activitycount_plural' : 'modulecount_plural';

    return $count . ' ' . ($count === 1 ?
                    get_string($singular, 'local_coursecatalog') :
                    get_string($plural, 'local_coursecatalog')
            );
}

/**
 * Count the number of main activities in a course.
 *
 * Counts all user-visible activities in main sections (excluding subsections),
 * excluding non-activity placeholders/resources defined in $excludedactivities.
 *
 * @param int $courseid Moodle course id.
 * @return int Number of main activities.
 */
function local_coursecatalog_count_main_activities(int $courseid): int {
    try {
        $modinfo = get_fast_modinfo($courseid);
    } catch (\Exception $e) {
        $msg = 'local_coursecatalog: get_fast_modinfo failed for course ' . $courseid . ': ' . $e->getMessage();
        debugging($msg, DEBUG_DEVELOPER);
        return 0;
    }
    $sections = $modinfo->get_section_info_all();

    // Keep real activities (including quiz/feedback/customcert), exclude only placeholders/resources.
    $excludedactivities = ['label', 'page', 'subsection'];

    $activitycount = 0;

    foreach ($sections as $secnum => $sec) {
        // Skip subsections (delegated sections with a component).
        if ($sec->component === 'mod_subsection') {
            continue;
        }

        // Skip hidden/unavailable sections for the current user.
        if (empty($sec->uservisible) || empty($sec->visible)) {
            continue;
        }

        // Check if section has modules.
        if (!empty($modinfo->sections[$secnum])) {
            $cmids = $modinfo->sections[$secnum];

            foreach ($cmids as $cmid) {
                $cm = $modinfo->get_cm($cmid);

                if (empty($cm->visible) || empty($cm->uservisible)) {
                    continue;
                }

                // Count only activities that are NOT in the excluded list.
                if (!in_array($cm->modname, $excludedactivities)) {
                    $activitycount++;
                }
            }
        }
    }

    return $activitycount;
}

/**
 * Return pages eligible for primary navigation.
 *
 * @return array
 */
function local_coursecatalog_get_primary_navigation_pages(): array {
    global $DB;

    return $DB->get_records('local_coursecatalog', [
        'isenabled' => 1,
        'showinprimarynavigation' => 1,
    ], 'sortorder ASC, id ASC');
}
