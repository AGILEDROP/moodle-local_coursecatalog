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
final class locallib_test extends \advanced_testcase {
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
     *
     * @covers ::local_coursecatalog_delete_by_category
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
     *
     * @covers ::local_coursecatalog_get_all_pages
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
     *
     * @covers ::local_coursecatalog_move_page
     * @covers ::local_coursecatalog_normalise_sortorder
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
     *
     * @covers ::local_coursecatalog_display_cards
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
     *
     * @covers \local_coursecatalog\observer::course_category_deleted
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
     *
     * @covers \local_coursecatalog\hook_callbacks::extend_primary_navigation
     * @covers ::local_coursecatalog_get_primary_navigation_pages
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
