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

namespace block_my_feedback;

use advanced_testcase;
use block_my_feedback;
use context_course;
use core\context\course;


/**
 * PHPUnit block_my_feedback tests
 *
 * @package    block_my_feedback
 * @category   test
 * @copyright  2024 UCL <m.opitz@ucl.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_my_feedback
 */
final class my_feedback_test extends advanced_testcase {

    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/../../moodleblock.class.php');
        require_once(__DIR__ . '/../block_my_feedback.php');
    }

    /**
     * Setup some dummy grade data.
     *
     * @param \stdClass $course
     * @param \stdClass $teacher
     * @param \stdClass $student1
     * @param \stdClass $student2
     * @return void
     * @throws \coding_exception
     */
    private function setup_grade_data($course, $teacher, $student1, $student2): void {
        global $CFG;

        // Create an array of modules and their grades.
        $dummymodules = [
            [
                'modulename' => 'assign',
                'name' => "Assign 1",
                'itemname' => "Grade assign item 1",
                'user' => $student1->id,
                'grade' => "80",
                'timemodified' => strtotime("-1 week", time()),
            ],
            [
                'modulename' => 'quiz',
                'name' => "Quiz 1",
                'itemname' => "Grade quiz item 1",
                'user' => $student1->id,
                'grade' => "50",
                'timemodified' => strtotime("-2 week", time()),
            ],
            [
                'modulename' => 'turnitintooltwo',
                'name' => "TurinitinToolTwo 1",
                'itemname' => "TurinitinToolTwo item 1",
                'user' => $student1->id,
                'grade' => "90",
                'timemodified' => strtotime("-2 week", time()),
            ],
            [
                'modulename' => 'quiz',
                'name' => "Quiz 2",
                'itemname' => "Grade quiz item 2",
                'user' => $student1->id,
                'grade' => "55",
                'timemodified' => strtotime("-15 days", time()),
            ],
            [
                'modulename' => 'assign',
                'name' => "Assign 2",
                'itemname' => "Grade assign item 2",
                'user' => $student1->id,
                'grade' => "69",
                'timemodified' => strtotime("-16 days", time()),
            ],
            [
                'modulename' => 'quiz',
                'name' => "Quiz 3",
                'itemname' => "Grade quiz item 3",
                'user' => $student1->id,
                'grade' => "65",
                'timemodified' => strtotime("-16 days", time()),
            ],
            [
                'modulename' => 'quiz',
                'name' => "Quiz 4",
                'itemname' => "Grade quiz item 4",
                'user' => $student1->id,
                'grade' => "75",
                'timemodified' => strtotime("-17 days", time()),
            ],
            [
                'modulename' => 'quiz',
                'name' => "Quiz 5",
                'itemname' => "Grade quiz item 5",
                'user' => $student1->id,
                'grade' => "75",
                'timemodified' => strtotime("-18 days", time()),
            ],
            // A recent grading from another user.
            [
                'modulename' => 'quiz',
                'name' => "Quiz by another user",
                'itemname' => "Another user quiz item 1",
                'user' => $student2->id,
                'grade' => "77",
                'timemodified' => strtotime("-1 week", time()),
            ],
            // This is too old and should not be shown at all.
            [
                'modulename' => 'quiz',
                'name' => "Old Quiz 1",
                'itemname' => "Old grade quiz item 1",
                'user' => $student2->id,
                'grade' => "70",
                'timemodified' => strtotime("-15 week", time()),
            ],
        ];

        // Create modules, grade items and grades from the dummy data.
        foreach ($dummymodules as $dmodule) {
            // Create the module.
            // Create for turnitintooltwo only if a data generator is present.
            if ($dmodule['modulename'] == 'turnitintooltwo') {
                if (file_exists($CFG->dirroot . '/mod/turnitintooltwo/tests/generator/lib.php')) {
                    $module = $this->getDataGenerator()->create_module($dmodule['modulename'],
                        ['course' => $course->id, 'name' => $dmodule['name']]);
                } else {
                    continue;
                }
            } else {
                $module = $this->getDataGenerator()->create_module($dmodule['modulename'],
                    ['course' => $course->id, 'name' => $dmodule['name']]);
            }
            $coursemodule = get_coursemodule_from_instance($dmodule['modulename'], $module->id, $course->id);

            // Create the grade item.
            $gradeitem = $this->getDataGenerator()->create_grade_item([
                'itemname' => $dmodule['itemname'],
                'courseid' => $course->id,
                'itemmodule' => $coursemodule->modname,
                'iteminstance' => $coursemodule->instance,
            ]);

            // Create the grade_grade.
            $gradegradedata = [
                'itemid' => $gradeitem->id,
                'userid' => $dmodule['user'],
                'teamsubmission' => false,
                'attemptnumber' => 0,
                'grade' => $dmodule['grade'],
                'usermodified' => $teacher->id,
                'timemodified' => $dmodule['timemodified'],
            ];
            $this->getDataGenerator()->create_grade_grade($gradegradedata);
        }
    }

    /**
     * Test the behaviour of get_submissions() method.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @covers ::get_submission
     */
    public function test_get_submissions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and prepare the page where the block will be added.
        $course = $this->getDataGenerator()->create_course();
        $page = new \moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');

        // Create users and enrol them.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        // Setup some dummy grade data.
        $this->setup_grade_data($course, $teacher, $student1, $student2);

        $block = new \block_my_feedback();
        $block->page = $page;

        // Test the submissions returned as student 1.
        $submissions = $block->get_submissions($student1);
        foreach ($submissions as $submission) {
            // Assert that all submissions are by the given user.
            $this->assertEquals($student1->id, $submission->userid);
            // Assert the result only contains submissions of certain types.
            $this->assertTrue(in_array($submission->modname, ['assign', 'quiz', 'turnitintooltwo']));
            // Assert the result only contains submissions not older than 3 month.
            $this->assertTrue($submission->lastmodified >= strtotime('-3 month'));
        }

        // Test the submissions returned as student 2.
        $submissions = $block->get_submissions($student2);
        foreach ($submissions as $submission) {
            // Assert that all submissions are by the given user.
            $this->assertEquals($student2->id, $submission->userid);
            // Assert the result only contains submissions of certain types.
            $this->assertTrue(in_array($submission->modname, ['assign', 'quiz', 'turnitintooltwo']));
            // Assert the result only contains submissions not older than 3 month.
            $this->assertTrue($submission->lastmodified >= strtotime('-3 month'));
        }
    }

    /**
     * Assert that max 5 feedbacks are shown and only those not older than 3 month.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @covers ::fetch_feedback
     */
    public function test_fetch_feedback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and prepare the page where the block will be added.
        $course = $this->getDataGenerator()->create_course();
        $page = new \moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');

        // Create users and enrol them.
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');

        // Setup some dummy grade data.
        $this->setup_grade_data($course, $teacher, $student1, $student2);

        $block = new \block_my_feedback();
        $block->page = $page;

        // Test the feedback as student1.
        $feedback = $block->fetch_feedback($student1);
        $this->assertEquals(5, count($feedback), "Returning no more than 5 submissions for student 1.");

        // Test the feedback as student2 - there should only be one.
        $feedback = $block->fetch_feedback($student2);
        $this->assertEquals(1, count($feedback), "Returning only 1 submission for student 2.");

    }
}

