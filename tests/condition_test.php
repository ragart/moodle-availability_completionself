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

namespace availability_completionself;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for the completion condition.
 *
 * @package   availability_completionself
 * @copyright 2025 Salvador Banderas Rovira <info@salvadorbanderas.eu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class condition_test extends \advanced_testcase {

    /**
     * Setup to ensure that fixtures are loaded.
     */
    public static function setupBeforeClass(): void {
        global $CFG;
        // Load the mock info class so that it can be used.
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info_module.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info_section.php');
    }

    /**
     * Load required classes.
     */
    public function setUp(): void {
        condition::wipe_static_cache();
    }

    /**
     * Tests constructing and using condition as part of tree.
     */
    public function test_in_tree() {
        global $USER, $CFG;
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create course with completion turned on and a Page.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $page = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $selfpage = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);

        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);
        $info = new \core_availability\mock_info($course, $USER->id);

        $structure = (object)[
            'op' => '|',
            'show' => true,
            'c' => [
                (object)[
                    'type' => 'completionself',
                    'e' => COMPLETION_COMPLETE
                ]
            ]
        ];
        $tree = new \core_availability\tree($structure);

        // Initial check (user has not completed activity).
        $result = $tree->check_available(false, $info, true, $USER->id);
        $this->assertFalse($result->is_available());

        // Mark activity complete.
        $completion = new \completion_info($course);
        $completion->update_state($cm, COMPLETION_COMPLETE);

        // Now it's true!
        $result = $tree->check_available(false, $info, true, $USER->id);
        $this->assertTrue($result->is_available());
    }

    /**
     * Tests the constructor including error conditions. Also tests the
     * string conversion feature (intended for debugging only).
     */
    public function test_constructor() {
        // Successful construct & display with all different expected values.
        $structure->e = COMPLETION_COMPLETE;
        $cond = new condition($structure);
        $this->assertEquals('{completionself:COMPLETE}', (string)$cond);

        $structure->e = COMPLETION_COMPLETE_PASS;
        $cond = new condition($structure);
        $this->assertEquals('{completionself:COMPLETE_PASS}', (string)$cond);

        $structure->e = COMPLETION_COMPLETE_FAIL;
        $cond = new condition($structure);
        $this->assertEquals('{completionself:COMPLETE_FAIL}', (string)$cond);

        $structure->e = COMPLETION_INCOMPLETE;
        $cond = new condition($structure);
        $this->assertEquals('{completionself:INCOMPLETE}', (string)$cond);

    }

    /**
     * Tests the save() function.
     */
    public function test_save() {
        $structure = (object)['e' => COMPLETION_COMPLETE];
        $cond = new condition($structure);
        $structure->type = 'completionself';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the is_available and get_description functions.
     */
    public function test_usage() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        // Create course with completion turned on.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user);

        // Create a Page with manual completion for basic checks.
        $page = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'name' => 'Page!',
                'completion' => COMPLETION_TRACKING_MANUAL]);

        // Create an assignment - we need to have something that can be graded
        // so as to test the PASS/FAIL states. Set it up to be completed based
        // on its grade item.
        $assignrow = $this->getDataGenerator()->create_module('assign', [
                        'course' => $course->id, 'name' => 'Assign!',
                        'completion' => COMPLETION_TRACKING_AUTOMATIC]);
        $DB->set_field('course_modules', 'completiongradeitemnumber', 0,
                ['id' => $assignrow->cmid]);
        // As we manually set the field here, we are going to need to reset the modinfo cache.
        rebuild_course_cache($course->id, true);
        $assign = new \assign(\context_module::instance($assignrow->cmid), false, false);

        // Get basic details.
        $modinfo = get_fast_modinfo($course);
        $pagecm = $modinfo->get_cm($page->cmid);
        $assigncm = $assign->get_course_module();
        $info = new \core_availability\mock_info($course, $user->id);

        // COMPLETE state (false), positive and NOT.
        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is marked complete~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        // INCOMPLETE state (true).
        $cond = new condition((object)[
            'e' => COMPLETION_INCOMPLETE
        ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $information = $cond->get_description(false, true, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is marked complete~', $information);

        // Mark page complete.
        $completion = new \completion_info($course);
        $completion->update_state($pagecm, COMPLETION_COMPLETE);

        // COMPLETE state (true).
        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE
        ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $information = $cond->get_description(false, true, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is incomplete~', $information);

        // INCOMPLETE state (false).
        $cond = new condition((object)[
            'e' => COMPLETION_INCOMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is incomplete~', $information);
        $this->assertTrue($cond->is_available(true, $info,
                true, $user->id));

        // We are going to need the grade item so that we can get pass/fails.
        $gradeitem = $assign->get_grade_item();
        \grade_object::set_properties($gradeitem, ['gradepass' => 50.0]);
        $gradeitem->update();

        // With no grade, it should return true for INCOMPLETE and false for
        // the other three.
        $cond = new condition((object)[
            'e' => COMPLETION_INCOMPLETE
        ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        // Check $information for COMPLETE_PASS and _FAIL as we haven't yet.
        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE_PASS
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is complete and passed~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE_FAIL
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is complete and failed~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        // Change the grade to be complete and failed.
        self::set_grade($assignrow, $user->id, 40);

        $cond = new condition((object)[
            'e' => COMPLETION_INCOMPLETE
        ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE_PASS
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is complete and passed~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE_FAIL
        ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $information = $cond->get_description(false, true, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is not complete and failed~', $information);

        // Now change it to pass.
        self::set_grade($assignrow, $user->id, 60);

        $cond = new condition((object)[
            'e' => COMPLETION_INCOMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE
        ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));

        $cond = new condition((object)[
                        'e' => COMPLETION_COMPLETE_PASS
                    ]);
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $information = $cond->get_description(false, true, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is not complete and passed~', $information);

        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE_FAIL
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~is complete and failed~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        // Simulate deletion of an activity by using an invalid cmid. These
        // conditions always fail, regardless of NOT flag or INCOMPLETE.
        $cond = new condition((object)[
            'cm' => ($assigncm->id + 100), 'e' => COMPLETION_COMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertMatchesRegularExpression('~(Missing activity).*is marked complete~', $information);
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $cond = new condition((object)[
            'cm' => ($assigncm->id + 100), 'e' => COMPLETION_INCOMPLETE
        ]);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
    }

    

    /**
     * Tests completion_value_used static function.
     */
    public function test_completion_value_used() {
        global $CFG, $DB;
        $this->resetAfterTest();
        $prevvalue = condition::OPTION_PREVIOUS;

        // Create course with completion turned on and some sections.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
                ['numsections' => 1, 'enablecompletion' => 1],
                ['createsections' => true]);

        // Create six pages with manual completion.
        $page1 = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $page2 = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $page3 = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $page4 = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $page5 = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $page6 = $generator->get_plugin_generator('mod_page')->create_instance(
                ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);

        // Set up page3 to depend on page1, and section1 to depend on page2.
        $DB->set_field('course_modules', 'availability',
                '{"op":"|","show":true,"c":[' .
                '{"type":"completionself","e":1}]}',
                ['id' => $page3->cmid]);
        $DB->set_field('course_sections', 'availability',
                '{"op":"|","show":true,"c":[' .
                '{"type":"completionself","e":1}]}',
                ['course' => $course->id, 'section' => 1]);
        // Set up page5 and page6 to depend on previous activity.
        $DB->set_field('course_modules', 'availability',
                '{"op":"|","show":true,"c":[' .
                '{"type":"completionself","e":1}]}',
                ['id' => $page5->cmid]);
        $DB->set_field('course_modules', 'availability',
                '{"op":"|","show":true,"c":[' .
                '{"type":"completionself","e":1}]}',
                ['id' => $page6->cmid]);

        // Check 1: nothing depends on page3 and page6 but something does on the others.
        $this->assertTrue(condition::completion_value_used(
                $course, $page1->cmid));
        $this->assertTrue(condition::completion_value_used(
                $course, $page2->cmid));
        $this->assertFalse(condition::completion_value_used(
                $course, $page3->cmid));
        $this->assertTrue(condition::completion_value_used(
                $course, $page4->cmid));
        $this->assertTrue(condition::completion_value_used(
                $course, $page5->cmid));
        $this->assertFalse(condition::completion_value_used(
                $course, $page6->cmid));
    }

    /**
     * Updates the grade of a user in the given assign module instance.
     *
     * @param \stdClass $assignrow Assignment row from database
     * @param int $userid User id
     * @param float $grade Grade
     */
    protected static function set_grade($assignrow, $userid, $grade) {
        $grades = [];
        $grades[$userid] = (object)[
            'rawgrade' => $grade, 'userid' => $userid
        ];
        $assignrow->cmidnumber = null;
        assign_grade_item_update($assignrow, $grades);
    }

    /**
     * Tests the update_dependency_id() function.
     */
    public function test_update_dependency_id() {
        $cond = new condition((object)[
            'e' => COMPLETION_COMPLETE
        ]);
        $this->assertFalse($cond->update_dependency_id('frogs', 42, 540));
        $this->assertFalse($cond->update_dependency_id('course_modules', 12, 34));
        $after = $cond->save();
        $this->assertEquals(COMPLETION_COMPLETE, $after->e);
    }
}
