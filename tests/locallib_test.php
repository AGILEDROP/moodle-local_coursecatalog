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

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\CoversMethod;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../locallib.php');

/**
 * Tests for local_coursecatalog locallib helpers.
 *
 * @package   local_coursecatalog
 * @category  test
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_coursecatalog_build_sort_context')]
#[CoversFunction('local_coursecatalog_delete_by_category')]
#[CoversFunction('local_coursecatalog_display_cards')]
#[CoversFunction('local_coursecatalog_get_all_pages')]
#[CoversFunction('local_coursecatalog_get_display_options')]
#[CoversFunction('local_coursecatalog_get_page_url')]
#[CoversFunction('local_coursecatalog_get_primary_navigation_pages')]
#[CoversFunction('local_coursecatalog_move_page')]
#[CoversFunction('local_coursecatalog_normalise_sortorder')]
#[CoversMethod(catalog_page::class, 'render')]
#[CoversMethod(hook_callbacks::class, 'extend_primary_navigation')]
#[CoversMethod(observer::class, 'course_category_deleted')]
final class locallib_test extends \advanced_testcase {
    /**
     * Catalog links use the short route only where Moodle supports and enables it.
     */
    public function test_catalog_page_urls_follow_router_configuration(): void {
        global $CFG;

        $this->resetAfterTest();
        $slug = 'test-page_1';
        $params = ['sort' => 'name_desc', 'view' => 'list', 'page' => 1, 'perpage' => 3];

        foreach ([false, true] as $routerconfigured) {
            $CFG->routerconfigured = $routerconfigured;
            $short = $CFG->version >= 2025041400 && $routerconfigured;
            $expectedpath = $short ? '/local_coursecatalog/' . $slug : '/local/coursecatalog/view.php';
            $url = \local_coursecatalog_get_page_url($slug, $params);

            $this->assertSame($CFG->wwwroot . $expectedpath, $url->out_omit_querystring());
            $this->assertSame($short ? null : $slug, $url->get_param('slug'));
            foreach ($params as $name => $value) {
                $this->assertSame((string)$value, (string)$url->get_param($name));
            }

            $sort = \local_coursecatalog_build_sort_context($slug, 'name_desc', 'list', 3);
            $this->assertSame($CFG->wwwroot . $expectedpath, $sort['action']);
            $this->assertSame(!$short, $sort['hasqueryslug']);
            $this->assertSame($short ? null : $slug, $sort['slug']);
            $this->assertSame(3, $sort['perpage']);
        }
    }

    /**
     * A guest can render a guest-accessible catalog using legacy links.
     */
    public function test_render_guest_catalog_without_router(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->routerconfigured = false;
        $this->setGuestUser();

        $category = self::getDataGenerator()->create_category();
        $page = $this->build_catalog_page_record($category->id, 'Guest catalog', 'guest-catalog', guestaccessible: 1);
        $DB->insert_record('local_coursecatalog', $page);

        $html = catalog_page::render($page->slug);
        $this->assertStringContainsString('Guest catalog', $html);
        $this->assertStringContainsString('/local/coursecatalog/view.php?slug=guest-catalog', $html);
        $this->assertStringContainsString('name="slug" value="guest-catalog"', $html);
    }

    /**
     * A guest cannot render a catalog that requires a registered user.
     */
    public function test_render_rejects_guest_for_private_catalog(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setGuestUser();

        $category = self::getDataGenerator()->create_category();
        $page = $this->build_catalog_page_record($category->id, 'Private catalog', 'private-catalog');
        $DB->insert_record('local_coursecatalog', $page);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('noguest', 'error'));
        catalog_page::render($page->slug);
    }

    /**
     * A disabled catalog is unavailable to a regular user.
     */
    public function test_render_disabled_catalog_for_regular_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $category = self::getDataGenerator()->create_category();
        $page = $this->build_catalog_page_record($category->id, 'Disabled catalog', 'disabled-catalog', isenabled: 0);
        $DB->insert_record('local_coursecatalog', $page);

        $html = catalog_page::render($page->slug);
        $this->assertStringContainsString(get_string('pagenotenableduser', 'local_coursecatalog'), $html);
        $this->assertStringNotContainsString('id="sortby-select"', $html);
    }

    /**
     * A manager can preview a disabled catalog and navigate its results.
     */
    public function test_render_disabled_catalog_preview(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->routerconfigured = true;
        $this->setAdminUser();

        $category = self::getDataGenerator()->create_category();
        $page = $this->build_catalog_page_record($category->id, 'Disabled catalog', 'disabled-catalog', isenabled: 0);
        $DB->insert_record('local_coursecatalog', $page);
        $firstcourse = self::getDataGenerator()->create_course(['category' => $category->id, 'fullname' => 'Alpha course']);
        $secondcourse = self::getDataGenerator()->create_course(['category' => $category->id, 'fullname' => 'Beta course']);

        $originalget = $_GET;
        $_GET = ['sort' => 'name_desc', 'view' => 'list', 'page' => '1', 'perpage' => '1'];
        try {
            $html = catalog_page::render($page->slug);
        } finally {
            $_GET = $originalget;
        }

        $this->assertStringContainsString(get_string('previewdisablednotice', 'local_coursecatalog'), $html);
        $this->assertStringContainsString('id="sortby-select"', $html);
        $this->assertStringContainsString($firstcourse->fullname, $html);
        $this->assertStringNotContainsString($secondcourse->fullname, $html);
        $this->assertStringContainsString('view=grid', $html);
        $this->assertStringContainsString('name="perpage" value="1"', $html);
        if ($CFG->version >= 2025041400) {
            $this->assertStringContainsString('/local_coursecatalog/disabled-catalog', $html);
            $this->assertStringNotContainsString('name="slug" value="disabled-catalog"', $html);
        }
    }

    /**
     * The page URL and rendered controls use the same normalised display options.
     */
    public function test_render_normalises_display_options(): void {
        global $CFG, $DB, $PAGE;

        $this->resetAfterTest();
        $CFG->routerconfigured = false;
        $this->setAdminUser();

        $category = self::getDataGenerator()->create_category();
        $page = $this->build_catalog_page_record($category->id, 'Display options catalog', 'display-options');
        $DB->insert_record('local_coursecatalog', $page);
        $course = self::getDataGenerator()->create_course(['category' => $category->id, 'fullname' => 'Catalog course']);

        $originalget = $_GET;
        $_GET = ['perpage' => '1000', 'page' => '-2', 'view' => 'foo', 'sort' => 'foo'];
        try {
            $html = catalog_page::render($page->slug);
        } finally {
            $_GET = $originalget;
        }

        $this->assertSame('name_asc', $PAGE->url->get_param('sort'));
        $this->assertSame('grid', $PAGE->url->get_param('view'));
        $this->assertSame('0', (string)$PAGE->url->get_param('page'));
        $this->assertSame('100', (string)$PAGE->url->get_param('perpage'));
        $this->assertStringContainsString($course->fullname, $html);
        $this->assertStringContainsString('name="view" value="grid"', $html);
        $this->assertStringContainsString('name="perpage" value="100"', $html);
        $this->assertStringContainsString('<option value="name_asc" selected>', $html);
        $listurl = new \moodle_url('/local/coursecatalog/view.php', [
            'slug' => $page->slug,
            'view' => 'list',
            'sort' => 'name_asc',
            'perpage' => 100,
        ]);
        $this->assertStringContainsString('href="' . $listurl->out() . '"', $html);
    }

    /**
     * Build a catalog page DB record fixture.
     *
     * @param int $categoryid
     * @param string $name
     * @param string $slug
     * @param int $isenabled
     * @param int $showinnav
     * @param int $sortorder
     * @param int $guestaccessible
     * @return \stdClass
     */
    private function build_catalog_page_record(
        int $categoryid,
        string $name,
        string $slug,
        int $isenabled = 1,
        int $showinnav = 0,
        int $sortorder = 0,
        int $guestaccessible = 0
    ): \stdClass {
        $now = time();
        return (object)[
            'name' => $name,
            'slug' => $slug,
            'course_category' => $categoryid,
            'pagedescription' => '',
            'pagedescriptionformat' => FORMAT_HTML,
            'isenabled' => $isenabled,
            'timecreated' => $now,
            'timeupdated' => $now,
            'sortorder' => $sortorder,
            'showinprimarynavigation' => $showinnav,
            'guestaccessible' => $guestaccessible,
        ];
    }

    /**
     * Verify category cleanup helper removes only the target category rows.
     */
    public function test_local_coursecatalog_delete_by_category(): void {
        global $DB;

        $this->resetAfterTest(true);

        $category1 = \core_course_category::create(['name' => 'Catalog category 1']);
        $category2 = \core_course_category::create(['name' => 'Catalog category 2']);

        $id1 = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category1->id,
            'Page 1',
            'catalog-page-1'
        ));
        $id2 = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category2->id,
            'Page 2',
            'catalog-page-2'
        ));

        \local_coursecatalog_delete_by_category((int)$category1->id);

        $this->assertFalse($DB->record_exists('local_coursecatalog', ['id' => $id1]));
        $this->assertTrue($DB->record_exists('local_coursecatalog', ['id' => $id2]));
    }

    /**
     * Verify pages are returned in explicit sort order.
     */
    public function test_local_coursecatalog_get_all_pages_orders_by_sortorder(): void {
        global $DB;

        $this->resetAfterTest(true);

        $category = \core_course_category::create(['name' => 'Ordered category']);

        $thirdid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Third page',
            'third-page',
            1,
            0,
            30
        ));
        $firstid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'First page',
            'first-page',
            1,
            0,
            10
        ));
        $secondid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Second page',
            'second-page',
            1,
            0,
            20
        ));

        $pages = array_values(\local_coursecatalog_get_all_pages());
        $ids = array_map(static fn($page) => (int)$page->id, $pages);

        $this->assertSame([$firstid, $secondid, $thirdid], $ids);
    }

    /**
     * Verify move helper swaps a page with its adjacent neighbour.
     */
    public function test_local_coursecatalog_move_page_swaps_adjacent_sortorder(): void {
        global $DB;

        $this->resetAfterTest(true);

        $category = \core_course_category::create(['name' => 'Move category']);

        $firstid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Move first',
            'move-first',
            1,
            0,
            1
        ));
        $secondid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Move second',
            'move-second',
            1,
            0,
            2
        ));
        $thirdid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Move third',
            'move-third',
            1,
            0,
            3
        ));

        $this->assertTrue(\local_coursecatalog_move_page((int)$secondid, 'up'));
        $pages = array_values(\local_coursecatalog_get_all_pages());
        $ids = array_map(static fn($page) => (int)$page->id, $pages);
        $this->assertSame([$secondid, $firstid, $thirdid], $ids);

        $this->assertFalse(\local_coursecatalog_move_page((int)$secondid, 'up'));
    }

    /**
     * Verify catalog rendering includes visible courses and skips hidden ones.
     */
    public function test_local_coursecatalog_display_cards_honours_course_visibility(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $category = \core_course_category::create(['name' => 'Catalog render category']);

        $generator = $this->getDataGenerator();
        $visiblecourse = $generator->create_course([
            'category' => $category->id,
            'fullname' => 'Visible course title',
            'shortname' => 'visiblecourse',
            'visible' => 1,
        ]);
        $generator->create_course([
            'category' => $category->id,
            'fullname' => 'Hidden course title',
            'shortname' => 'hiddencourse',
            'visible' => 0,
        ]);

        $page = $this->build_catalog_page_record(
            $category->id,
            'Render page',
            'render-page'
        );
        $page->id = $DB->insert_record('local_coursecatalog', $page);

        $html = \local_coursecatalog_display_cards($page);

        $this->assertStringContainsString($visiblecourse->fullname, $html);
        $this->assertStringNotContainsString('Hidden course title', $html);
    }

    /**
     * Verify observer callback removes only rows for the deleted category id.
     */
    public function test_observer_course_category_deleted_removes_orphans(): void {
        global $DB;

        $this->resetAfterTest(true);

        $category1 = \core_course_category::create(['name' => 'Observer category 1']);
        $category2 = \core_course_category::create(['name' => 'Observer category 2']);

        $id1 = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category1->id,
            'Observer page 1',
            'observer-page-1'
        ));
        $id2 = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category2->id,
            'Observer page 2',
            'observer-page-2'
        ));

        $event = \core\event\course_category_deleted::create([
            'objectid' => $category1->id,
            'context' => \context_coursecat::instance($category1->id),
            'other' => ['name' => $category1->name],
        ]);

        \local_coursecatalog\observer::course_category_deleted($event);

        $this->assertFalse($DB->record_exists('local_coursecatalog', ['id' => $id1]));
        $this->assertTrue($DB->record_exists('local_coursecatalog', ['id' => $id2]));
    }

    /**
     * Verify primary nav callback adds only eligible pages.
     */
    public function test_extend_primary_navigation_adds_only_eligible_pages(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $category = \core_course_category::create(['name' => 'Navigation category']);

        $eligibleid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Nav eligible',
            'nav-eligible',
            1,
            1,
            20
        ));
        $firsteligibleid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Nav eligible first',
            'nav-eligible-first',
            1,
            1,
            10
        ));
        $disabledid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Nav disabled',
            'nav-disabled',
            0,
            1,
            30
        ));
        $hiddenid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            $category->id,
            'Nav hidden',
            'nav-hidden',
            1,
            0,
            40
        ));
        $orphanid = $DB->insert_record('local_coursecatalog', $this->build_catalog_page_record(
            99999999,
            'Nav orphan',
            'nav-orphan',
            1,
            1,
            50
        ));

        $moodlepage = new \moodle_page();
        $moodlepage->set_url('/');
        $primaryview = new \core\navigation\views\primary($moodlepage);
        $hook = new \core\hook\navigation\primary_extend($primaryview);

        \local_coursecatalog\hook_callbacks::extend_primary_navigation($hook);
        $keys = $primaryview->get_children_key_list();

        $this->assertSame([
            'local_coursecatalog_' . $firsteligibleid,
            'local_coursecatalog_' . $eligibleid,
        ], array_values($keys));
        $this->assertContains('local_coursecatalog_' . $eligibleid, $keys);
        $this->assertContains('local_coursecatalog_' . $firsteligibleid, $keys);
        $this->assertNotContains('local_coursecatalog_' . $disabledid, $keys);
        $this->assertNotContains('local_coursecatalog_' . $hiddenid, $keys);
        $this->assertNotContains('local_coursecatalog_' . $orphanid, $keys);
    }
}
