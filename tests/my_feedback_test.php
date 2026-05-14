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
use context_course;
use mod_quiz\question\display_options;

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
    /** @var \stdClass The course used in tests. */
    private \stdClass $course;

    /** @var \stdClass The first student used in tests. */
    private \stdClass $student1;

    /** @var \stdClass The second student used in tests. */
    private \stdClass $student2;

    /** @var \stdClass The teacher used in tests. */
    private \stdClass $teacher;

    /** @var \block_my_feedback The block instance used in tests. */
    private \block_my_feedback $block;

    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/../../moodleblock.class.php');
        require_once(__DIR__ . '/../block_my_feedback.php');
        parent::setUpBeforeClass();
    }

    /**
     * Set up common test fixtures.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        $page = new \moodle_page();
        $page->set_context(context_course::instance($this->course->id));
        $page->set_pagelayout('course');

        $this->student1 = $this->getDataGenerator()->create_user();
        $this->student2 = $this->getDataGenerator()->create_user();
        $this->teacher  = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($this->student1->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->student2->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'teacher');

        $this->setup_grade_data($this->course, $this->teacher, $this->student1, $this->student2);

        $this->block = new \block_my_feedback();
        $this->block->page = $page;
    }

    /**
     * Setup some dummy grade data.
     *
     * @param \stdClass $course
     * @param \stdClass $teacher
     * @param \stdClass $student1
     * @param \stdClass $student2
     * @return void
     */
    private function setup_grade_data($course, $teacher, $student1, $student2): void {
        global $CFG, $DB;

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
                    $module = $this->getDataGenerator()->create_module(
                        $dmodule['modulename'],
                        ['course' => $course->id, 'name' => $dmodule['name']]
                    );
                } else {
                    continue;
                }
            } else {
                $moduledata = ['course' => $course->id, 'name' => $dmodule['name']];
                if ($dmodule['modulename'] === 'quiz') {
                    $moduledata = $moduledata + [
                            'reviewattempt' => display_options::VISIBLE,
                            'reviewcorrectness' => display_options::VISIBLE,
                            'reviewmarks' => display_options::MAX_ONLY,
                        ];
                }
                $module = $this->getDataGenerator()->create_module(
                    $dmodule['modulename'],
                    $moduledata
                );
            }
            $coursemodule = get_coursemodule_from_instance($dmodule['modulename'], $module->id, $course->id);

            if ($dmodule['modulename'] === 'assign') {
                $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_submission([
                    'cmid' => $coursemodule->id,
                    'userid' => $dmodule['user'],
                    'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
                    'latest' => 1,
                    'timemodified' => $dmodule['timemodified'],
                ]);
            }

            if ($dmodule['modulename'] === 'quiz') {
                $attempt = (object) [
                    'quiz' => $module->id,
                    'userid' => $dmodule['user'],
                    'attempt' => 1,
                    'uniqueid' => random_int(1, PHP_INT_MAX),
                    'layout' => '',
                    'currentpage' => 0,
                    'preview' => 0,
                    'state' => 'finished',
                    'timestart' => $dmodule['timemodified'] - HOURSECS,
                    'timefinish' => $dmodule['timemodified'],
                    'timemodified' => $dmodule['timemodified'],
                    'timecheckstate' => 0,
                    'sumgrades' => 0,
                ];
                $this->getDataGenerator()->get_plugin_generator('mod_quiz');
                $DB->insert_record('quiz_attempts', $attempt);
            }

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
     * @covers ::get_submissions
     */
    public function test_get_submissions(): void {
        $submissions = $this->block->get_submissions($this->student1);
        foreach ($submissions as $submission) {
            // Assert that all submissions are by the given user.
            $this->assertEquals($this->student1->id, $submission->userid);
            // Assert the result only contains submissions of certain types.
            $this->assertTrue(in_array($submission->modname, ['assign', 'quiz', 'turnitintooltwo']));
            // Assert the result only contains submissions not older than 3 month.
            $this->assertTrue($submission->lastmodified >= strtotime('-3 month'));
        }

        $submissions = $this->block->get_submissions($this->student2);
        foreach ($submissions as $submission) {
            // Assert that all submissions are by the given user.
            $this->assertEquals($this->student2->id, $submission->userid);
            // Assert the result only contains submissions of certain types.
            $this->assertTrue(in_array($submission->modname, ['assign', 'quiz', 'turnitintooltwo']));
            // Assert the result only contains submissions not older than 3 month.
            $this->assertTrue($submission->lastmodified >= strtotime('-3 month'));
        }
    }

    /**
     * Test submissions are returned from multiple enrolled courses.
     *
     * @return void
     * @covers ::get_submissions
     */
    public function test_get_submissions_from_multiple_courses(): void {
        // This test needs its own course pair, so create them independently.
        $course1 = $this->getDataGenerator()->create_course(['shortname' => 'C1']);
        $course2 = $this->getDataGenerator()->create_course(['shortname' => 'C2']);

        $page = new \moodle_page();
        $page->set_context(context_course::instance($course1->id));
        $page->set_pagelayout('course');

        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, 'teacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $course2->id, 'teacher');

        foreach ([[$course1, 'Assign course 1'], [$course2, 'Assign course 2']] as [$course, $name]) {
            $module = $this->getDataGenerator()->create_module('assign', [
                'course' => $course->id,
                'name' => $name,
            ]);
            $cm = get_coursemodule_from_instance('assign', $module->id, $course->id);

            $gradeitem = $this->getDataGenerator()->create_grade_item([
                'courseid' => $course->id,
                'itemmodule' => $cm->modname,
                'iteminstance' => $cm->instance,
                'itemname' => $name,
            ]);

            $this->getDataGenerator()->create_grade_grade([
                'itemid' => $gradeitem->id,
                'userid' => $student->id,
                'teamsubmission' => false,
                'attemptnumber' => 0,
                'grade' => '75',
                'usermodified' => $teacher->id,
                'timemodified' => time() - HOURSECS,
            ]);
        }

        $block = new \block_my_feedback();
        $block->page = $page;

        $submissions = $block->get_submissions($student);
        $courses = array_unique(array_map(fn($submission) => $submission->course, $submissions));
        sort($courses);

        $this->assertCount(2, $submissions);
        $this->assertEqualsCanonicalizing([$course1->id, $course2->id], $courses);
    }

    /**
     * Assert that max 5 feedbacks are shown and only those not older than 3 month.
     *
     * @return void
     * @covers ::fetch_feedback
     */
    public function test_fetch_feedback(): void {
        // Test the feedback as student1.
        $feedback = $this->block->fetch_feedback($this->student1);
        $this->assertNotEmpty($feedback, 'Returning recent visible feedback for student 1.');
        $this->assertLessThanOrEqual(5, count($feedback), 'Returning no more than 5 submissions for student 1.');

        foreach ($feedback as $item) {
            $this->assertSame($this->course->fullname, $item->coursename);
            $this->assertNotEmpty($item->name);
            $this->assertNotEmpty($item->releaseddate);
            $this->assertNotEmpty($item->url);
        }

        // Test the feedback as student2 - there should be at most one visible recent item.
        $feedback = $this->block->fetch_feedback($this->student2);
        if ($feedback !== null) {
            $this->assertCount(1, $feedback, 'Returning only 1 visible recent submission for student 2.');
        }
    }

    /**
     * Assert that feedback falls back to the course image when the grader user cannot be loaded.
     *
     * @return void
     * @covers ::fetch_feedback
     */
    public function test_fetch_feedback_with_missing_grader_uses_course_image(): void {
        global $DB;

        $assignsubmission = $DB->get_record_sql(
            "SELECT gg.id, gi.itemname
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gg.userid = :userid
                AND gi.itemmodule = :itemmodule
                AND gi.itemname = :itemname",
            [
                'userid' => $this->student1->id,
                'itemmodule' => 'assign',
                'itemname' => 'Grade assign item 1',
            ],
            MUST_EXIST
        );

        $DB->set_field('grade_grades', 'usermodified', null, ['id' => $assignsubmission->id]);

        $feedback = $this->block->fetch_feedback($this->student1);

        $this->assertNotNull($feedback);

        $assignfeedback = array_values(array_filter($feedback, fn($item) => $item->name === $assignsubmission->itemname));
        $this->assertCount(1, $assignfeedback, 'The assignment feedback item should still be returned.');

        $assignfeedback = reset($assignfeedback);
        $expectedicon = \core_course\external\course_summary_exporter::get_course_image($this->course);

        $this->assertSame($expectedicon, $assignfeedback->icon);
        $this->assertObjectNotHasProperty('tutorname', $assignfeedback);
    }
}
