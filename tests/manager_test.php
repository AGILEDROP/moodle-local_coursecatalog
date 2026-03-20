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
 * Tests for the course catalog manager class.
 *
 * @package   local_coursecatalog
 * @category  test
 * @copyright Agiledrop, 2026 <developer@agiledrop.com>
 * @author    Matej Pal <matej.pal@agiledrop.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_coursecatalog\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Insert a catalog page fixture directly into the DB.
     *
     * @param int    $categoryid
     * @param string $name
     * @param string $slug
     * @param int    $isenabled
     * @param int    $showinnav
     * @param int    $guestaccessible
     * @return int   Record id.
     */
    private function insert_page(
        int $categoryid,
        string $name = 'Test page',
        string $slug = 'test-page',
        int $isenabled = 1,
        int $showinnav = 0,
        int $guestaccessible = 0
    ): int {
        global $DB;

        $now = time();
        return $DB->insert_record('local_coursecatalog', (object)[
            'name' => $name,
            'slug' => $slug,
            'course_category' => $categoryid,
            'pagedescription' => '',
            'pagedescriptionformat' => FORMAT_HTML,
            'isenabled' => $isenabled,
            'timecreated' => $now,
            'timeupdated' => $now,
            'sortorder' => \local_coursecatalog_get_next_sortorder(),
            'showinprimarynavigation' => $showinnav,
            'guestaccessible' => $guestaccessible,
        ]);
    }

    // Toggle_page tests.

    /**
     * Toggling isenabled from 0 to 1 updates the record.
     */
    public function test_toggle_page_enables_page(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Toggle cat']);
        $id = $this->insert_page($cat->id, 'Toggle page', 'toggle-page', 0);

        $messages = manager::toggle_page($id, 1, null, null);

        $this->assertCount(1, $messages);
        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals(1, (int)$record->isenabled);
    }

    /**
     * Toggling isenabled from 1 to 0 updates the record.
     */
    public function test_toggle_page_disables_page(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Toggle cat']);
        $id = $this->insert_page($cat->id, 'Toggle page', 'toggle-page', 1);

        $messages = manager::toggle_page($id, 0, null, null);

        $this->assertCount(1, $messages);
        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals(0, (int)$record->isenabled);
    }

    /**
     * Enabling nav on an enabled page succeeds.
     */
    public function test_toggle_page_enables_nav_on_enabled_page(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Toggle cat']);
        $id = $this->insert_page($cat->id, 'Nav page', 'nav-page', 1, 0);

        $messages = manager::toggle_page($id, null, 1, null);

        $this->assertCount(1, $messages);
        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals(1, (int)$record->showinprimarynavigation);
    }

    /**
     * Enabling nav on a disabled page throws.
     */
    public function test_toggle_page_nav_on_disabled_page_throws(): void {
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Toggle cat']);
        $id = $this->insert_page($cat->id, 'Disabled page', 'disabled-page', 0, 0);

        $this->expectException(\moodle_exception::class);
        manager::toggle_page($id, null, 1, null);
    }

    /**
     * Enabling guest access on a disabled page throws.
     */
    public function test_toggle_page_guest_on_disabled_page_throws(): void {
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Toggle cat']);
        $id = $this->insert_page($cat->id, 'Disabled page', 'disabled-guest', 0, 0);

        $this->expectException(\moodle_exception::class);
        manager::toggle_page($id, null, null, 1);
    }

    /**
     * Calling toggle_page with no fields throws.
     */
    public function test_toggle_page_no_fields_throws(): void {
        $this->resetAfterTest(true);

        $this->expectException(\invalid_parameter_exception::class);
        manager::toggle_page(1, null, null, null);
    }

    /**
     * Invalid boolean value for isenabled throws.
     */
    public function test_toggle_page_invalid_isenabled_throws(): void {
        $this->resetAfterTest(true);

        $this->expectException(\invalid_parameter_exception::class);
        manager::toggle_page(1, 5, null, null);
    }

    /**
     * Multiple flags can be toggled at once.
     */
    public function test_toggle_page_multiple_flags(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Toggle cat']);
        $id = $this->insert_page($cat->id, 'Multi page', 'multi-page', 1, 0, 0);

        $messages = manager::toggle_page($id, null, 1, 1);

        $this->assertCount(2, $messages);
        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals(1, (int)$record->showinprimarynavigation);
        $this->assertEquals(1, (int)$record->guestaccessible);
    }

    // Create_page tests.

    /**
     * create_page inserts a record with expected defaults.
     */
    public function test_create_page_inserts_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Create cat']);

        $data = (object)[
            'name' => 'New page',
            'slug' => 'new-page',
            'course_category' => $cat->id,
            'pagedescription' => '<p>Hello</p>',
            'pagedescriptionformat' => FORMAT_HTML,
        ];
        $id = manager::create_page($data);

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals('New page', $record->name);
        $this->assertEquals('new-page', $record->slug);
        $this->assertEquals($cat->id, (int)$record->course_category);
        $this->assertEquals('<p>Hello</p>', $record->pagedescription);
        $this->assertEquals(0, (int)$record->isenabled);
        $this->assertEquals(0, (int)$record->showinprimarynavigation);
    }

    /**
     * create_page assigns the next sortorder.
     */
    public function test_create_page_increments_sortorder(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Sort cat']);

        $id1 = manager::create_page((object)[
            'name' => 'First',
            'slug' => 'first',
            'course_category' => $cat->id,
        ]);
        $id2 = manager::create_page((object)[
            'name' => 'Second',
            'slug' => 'second',
            'course_category' => $cat->id,
        ]);

        $r1 = $DB->get_record('local_coursecatalog', ['id' => $id1]);
        $r2 = $DB->get_record('local_coursecatalog', ['id' => $id2]);
        $this->assertGreaterThan((int)$r1->sortorder, (int)$r2->sortorder);
    }

    /**
     * create_page handles missing description gracefully.
     */
    public function test_create_page_without_description(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'No desc cat']);

        $id = manager::create_page((object)[
            'name' => 'No desc',
            'slug' => 'no-desc',
            'course_category' => $cat->id,
        ]);

        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals('', $record->pagedescription);
    }

    // Update_page tests.

    /**
     * update_page modifies the record and returns true.
     */
    public function test_update_page_succeeds(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Update cat']);
        $id = $this->insert_page($cat->id, 'Old name', 'old-slug');

        $result = manager::update_page($id, (object)[
            'name' => 'New name',
            'slug' => 'new-slug',
            'course_category' => $cat->id,
            'pagedescription' => '<p>Updated</p>',
            'pagedescriptionformat' => FORMAT_HTML,
        ]);

        $this->assertTrue($result);
        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertEquals('New name', $record->name);
        $this->assertEquals('new-slug', $record->slug);
        $this->assertEquals('<p>Updated</p>', $record->pagedescription);
    }

    /**
     * update_page returns false when the slug is already taken by another record.
     */
    public function test_update_page_duplicate_slug_returns_false(): void {
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Dup cat']);
        $this->insert_page($cat->id, 'Existing', 'taken-slug');
        $id2 = $this->insert_page($cat->id, 'Second', 'second-slug');

        $result = manager::update_page($id2, (object)[
            'name' => 'Second',
            'slug' => 'taken-slug',
            'course_category' => $cat->id,
        ]);

        $this->assertFalse($result);
    }

    /**
     * update_page allows keeping the same slug on the same record.
     */
    public function test_update_page_same_slug_succeeds(): void {
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Same cat']);
        $id = $this->insert_page($cat->id, 'Same slug', 'same-slug');

        $result = manager::update_page($id, (object)[
            'name' => 'Renamed',
            'slug' => 'same-slug',
            'course_category' => $cat->id,
        ]);

        $this->assertTrue($result);
    }

    /**
     * update_page sets timeupdated.
     */
    public function test_update_page_updates_timestamp(): void {
        global $DB;
        $this->resetAfterTest(true);

        $cat = \core_course_category::create(['name' => 'Time cat']);
        $id = $this->insert_page($cat->id, 'Timestamp', 'timestamp-page');

        $before = time();
        manager::update_page($id, (object)[
            'name' => 'Timestamp',
            'slug' => 'timestamp-page',
            'course_category' => $cat->id,
        ]);

        $record = $DB->get_record('local_coursecatalog', ['id' => $id]);
        $this->assertGreaterThanOrEqual($before, (int)$record->timeupdated);
    }
}
