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
     * Allocate an assignment submission using the schema for the current Moodle version.
     *
     * Moodle 5.2 moved allocations from assign_user_flags to assign_allocated_marker.
     *
     * @param int $assignmentid Assignment ID.
     * @param int $studentid Student ID.
     * @param int $markerid Marker ID.
     * @return void
     */
    private function allocate_assignment_marker(int $assignmentid, int $studentid, int $markerid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        if (method_exists('assign', 'get_allocated_markers')) {
            $DB->insert_record('assign_allocated_marker', (object)[
                'assignment' => $assignmentid,
                'student' => $studentid,
                'marker' => $markerid,
            ]);
            return;
        }

        $DB->insert_record('assign_user_flags', (object)[
            'assignment' => $assignmentid,
            'userid' => $studentid,
            'allocatedmarker' => $markerid,
        ]);
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
     * User-hidden grades are not returned as feedback until their hide time has passed.
     *
     * @return void
     * @covers ::get_submissions
     */
    public function test_get_submissions_excludes_user_hidden_grades(): void {
        global $DB;

        $item = $DB->get_record('grade_items', [
            'courseid' => $this->course->id,
            'itemmodule' => 'assign',
            'itemname' => 'Grade assign item 1',
        ], '*', MUST_EXIST);
        $grade = $DB->get_record('grade_grades', [
            'itemid' => $item->id,
            'userid' => $this->student1->id,
        ], '*', MUST_EXIST);
        $grade->hidden = time() + DAYSECS;
        $DB->update_record('grade_grades', $grade);

        $submissions = $this->block->get_submissions($this->student1);
        $gradeids = array_column($submissions, 'gradeid');

        $this->assertNotContains((int)$grade->id, array_map('intval', $gradeids));
    }

    /**
     * Assignment marking counts use the requested marker and bulk-load grades as submission volume grows.
     *
     * @return void
     * @covers ::add_mod_data
     * @covers ::count_assign_submissions_to_mark
     */
    public function test_assignment_marking_uses_requested_marker_and_bulk_grade_lookup(): void {
        global $DB;

        $assignment = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'name' => 'Counting assignment',
            'duedate' => time() + DAYSECS,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignfeedback_comments_enabled' => 1,
            'submissiondrafts' => 0,
        ]);
        $cmrecord = get_coursemodule_from_instance('assign', $assignment->id, $this->course->id);
        $cm = get_fast_modinfo($this->course)->get_cm($cmrecord->id);
        $submitters = [];
        for ($i = 0; $i < 12; $i++) {
            $submitter = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($submitter->id, $this->course->id, 'student');
            $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_submission([
                'cmid' => $cm->id,
                'userid' => $submitter->id,
                'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
                'onlinetext' => 'Submitted work',
            ]);
            $submitters[] = $submitter;
        }

        // Assign one submission to the requested marker; leave the rest unallocated.
        $this->allocate_assignment_marker($assignment->id, $submitters[0]->id, $this->teacher->id);

        // Saving an ungraded attempt can still set grader; it must remain in the pending count.
        require_once($GLOBALS['CFG']->dirroot . '/mod/assign/locallib.php');
        $assignmentapi = new \assign($cm->context, $cm, $cm->course);
        $attemptedungraded = $assignmentapi->get_user_grade($submitters[0]->id, true);
        $attemptedungraded->grader = $this->teacher->id;
        $DB->update_record('assign_grades', $attemptedungraded);

        // The current session user differs from the marker passed to fetch_marking/add_mod_data.
        $this->setUser($this->student2);
        $submissions = $DB->get_records('assign_submission', [
            'assignment' => $assignment->id,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'latest' => 1,
        ], '', 'id, userid, groupid');
        $modulehelper = new class ($cm, $submissions) extends \report_feedback_tracker\local\module_helper {
            /** @var array */
            private array $submissions;

            /**
             * Create a helper returning the prepared submitted records.
             *
             * @param \cm_info $module
             * @param array $submissions
             */
            public function __construct(\cm_info $module, array $submissions) {
                parent::__construct($module);
                $this->submissions = $submissions;
            }

            /**
             * Return a dummy marking URL.
             *
             * @return ?\moodle_url
             */
            public function get_markingurl(): ?\moodle_url {
                return new \moodle_url('/mod/assign/view.php', ['id' => $this->module->id]);
            }

            /**
             * Return a due date within the visible window.
             *
             * @return int
             */
            public function get_duedate(): int {
                return time() + DAYSECS;
            }

            /**
             * Return prepared assignment submissions.
             *
             * @return array
             */
            public function get_module_submissions(): array {
                return $this->submissions;
            }

            /**
             * Replace the prepared records to compare query scaling.
             *
             * @param array $submissions
             */
            public function set_submissions(array $submissions): void {
                $this->submissions = $submissions;
            }

            /**
             * Return no separate submission date.
             *
             * @param int $userid
             * @param ?int $part
             * @return int
             */
            public function get_submissiondate(int $userid, ?int $part = null): int {
                return 0;
            }
        };
        $assess = new \stdClass();
        $queriesbefore = $DB->perf_get_queries();
        $modulehelper->set_submissions(array_slice(array_values($submissions), 0, 1));
        $smallresult = $this->block->add_mod_data($modulehelper, $assess, time() + DAYSECS, $cm, $this->teacher->id);
        $smallquerycount = $DB->perf_get_queries() - $queriesbefore;

        $modulehelper->set_submissions(array_values($submissions));
        $assess = new \stdClass();
        $queriesbefore = $DB->perf_get_queries();
        $largeresult = $this->block->add_mod_data($modulehelper, $assess, time() + DAYSECS, $cm, $this->teacher->id);
        $largequerycount = $DB->perf_get_queries() - $queriesbefore;

        $this->assertTrue($smallresult);
        $this->assertTrue($largeresult);
        $this->assertSame(12, $assess->requiremarking);
        $this->assertLessThanOrEqual(
            $smallquerycount + 1,
            $largequerycount,
            'Assignment grading query count should remain constant as submitters increase.'
        );
    }

    /**
     * Team allocations are checked against group members, and their grades are read in bulk.
     *
     * @return void
     * @covers ::add_mod_data
     * @covers ::count_assign_submissions_to_mark
     */
    public function test_team_assignment_counts_allocations_and_grades_for_all_members(): void {
        global $DB;

        $groupid = groups_create_group((object)[
            'courseid' => $this->course->id,
            'name' => 'Submission team',
        ]);
        groups_add_member($groupid, $this->student1->id);

        $assignment = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'name' => 'Team assignment',
            'duedate' => time() + DAYSECS,
            'teamsubmission' => 1,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignfeedback_comments_enabled' => 1,
            'submissiondrafts' => 0,
        ]);
        $cmrecord = get_coursemodule_from_instance('assign', $assignment->id, $this->course->id);
        $cm = get_fast_modinfo($this->course)->get_cm($cmrecord->id);
        require_once($GLOBALS['CFG']->dirroot . '/mod/assign/locallib.php');
        $assignmentapi = new \assign($cm->context, $cm, $cm->course);
        $groupsubmission = $assignmentapi->get_group_submission($this->student1->id, $groupid, true);
        $groupsubmission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $groupsubmission->latest = 1;
        $DB->update_record('assign_submission', $groupsubmission);

        // The group member is assigned to another marker; a submission has userid=0 in core Moodle.
        $this->allocate_assignment_marker($assignment->id, $this->student1->id, $this->student2->id);

        $fakehelper = new class ($cm, [$groupsubmission]) extends \report_feedback_tracker\local\module_helper {
            /** @var array */
            private array $submissions;

            /**
             * Create the fake helper with a group submission.
             *
             * @param \cm_info $module
             * @param array $submissions
             */
            public function __construct(\cm_info $module, array $submissions) {
                parent::__construct($module);
                $this->submissions = $submissions;
            }

            /**
             * Return a dummy marking URL.
             *
             * @return \moodle_url
             */
            public function get_markingurl(): ?\moodle_url {
                return new \moodle_url('/mod/assign/view.php', ['id' => $this->module->id]);
            }

            /**
             * Return a due date within the block's display window.
             *
             * @return int
             */
            public function get_duedate(): int {
                return time() + DAYSECS;
            }

            /**
             * Return prepared group submissions.
             *
             * @return array
             */
            public function get_module_submissions(): array {
                return $this->submissions;
            }

            /**
             * Replace the prepared group submissions.
             *
             * @param array $submissions
             * @return void
             */
            public function set_submissions(array $submissions): void {
                $this->submissions = $submissions;
            }

            /**
             * Return no separate submission date.
             *
             * @param int $userid
             * @param ?int $part
             * @return int
             */
            public function get_submissiondate(int $userid, ?int $part = null): int {
                return 0;
            }
        };

        $this->setUser($this->teacher);
        $assess = new \stdClass();
        $queriesbefore = $DB->perf_get_queries();
        $smallresult = $this->block->add_mod_data($fakehelper, $assess, time() + DAYSECS, $cm, $this->teacher->id);
        $smallquerycount = $DB->perf_get_queries() - $queriesbefore;
        $this->assertFalse($smallresult);

        // Adding more teams must not add one group-membership or grade query per team.
        $groupsubmissions = [$groupsubmission];
        for ($i = 0; $i < 11; $i++) {
            $extragroupid = groups_create_group((object)[
                'courseid' => $this->course->id,
                'name' => 'Additional team ' . $i,
            ]);
            $member = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($member->id, $this->course->id, 'student');
            groups_add_member($extragroupid, $member->id);
            $this->allocate_assignment_marker($assignment->id, $member->id, $this->student2->id);
            $extragroupsubmission = $assignmentapi->get_group_submission($member->id, $extragroupid, true);
            $extragroupsubmission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            $extragroupsubmission->latest = 1;
            $DB->update_record('assign_submission', $extragroupsubmission);
            $groupsubmissions[] = $extragroupsubmission;
        }
        $fakehelper->set_submissions($groupsubmissions);
        $assess = new \stdClass();
        $queriesbefore = $DB->perf_get_queries();
        $largeresult = $this->block->add_mod_data($fakehelper, $assess, time() + DAYSECS, $cm, $this->teacher->id);
        $largequerycount = $DB->perf_get_queries() - $queriesbefore;

        $this->assertFalse($largeresult);
        $this->assertLessThanOrEqual(
            $smallquerycount + 1,
            $largequerycount,
            'Adding group members should not add a grade lookup per member.'
        );
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
