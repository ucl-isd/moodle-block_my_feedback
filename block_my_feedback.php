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

use core_course\external\course_summary_exporter;
use local_assess_type\assess_type; // UCL plugin.
use report_feedback_tracker\local\helper as feedback_tracker_helper; // UCL plugin.
use report_feedback_tracker\local\module_helper;

/**
 * Block definition class for the block_my_feedback plugin.
 *
 * @package   block_my_feedback
 * @copyright 2023 Stuart Lamour
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_my_feedback extends block_base {
    /**
     * @var array array of roles a marker may have.
     */
    private array $markerroles;

    /**
     * @var array array of roles a student may have.
     */
    private array $studentroles;

    /**
     * @var bool marker status.
     */
    private bool $ismarker;

    /**
     * @var bool student status.
     */
    private bool $isstudent;

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        $this->markerroles = $this->get_marker_role_ids();
        $this->studentroles = $this->get_student_role_ids();
        $this->ismarker = $this->is_marker();
        $this->isstudent = $this->is_student();

        // No title for the block as each section will have one.
        $this->title = '';
    }

    /**
     * Get the marker role IDs.
     *
     * @return array
     */
    private function get_marker_role_ids(): array {
        global $DB;

        return $DB->get_fieldset_select(
            'role',
            'id',
            'shortname IN (:role1, :role2, :role3, :role4)',
            [
                'role1' => 'ucltutor',
                'role2' => 'uclnoneditingtutor',
                'role3' => 'uclnoneditingtutor_noemail',
                'role4' => 'uclleader',
            ]
        );
    }

    /**
     * Get the student role IDs.
     *
     * @return array
     */
    private function get_student_role_ids(): array {
        global $DB;

        return $DB->get_fieldset_select(
            'role',
            'id',
            'archetype IN (:role1)',
            [
                'role1' => 'student',
            ]
        );
    }

    /**
     * Gets the block contents.
     *
     * @return stdClass The block content.
     */
    public function get_content(): stdClass {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $template = new stdClass();

        // Marker content.
        if ($this->ismarker && $template->markingmods = $this->fetch_marking($USER)) {
            $template->showmarkings = true;
            $template->markingheader = get_string('markingfor', 'block_my_feedback', $USER->firstname);
        }

        // Student content.
        if ($this->isstudent && $template->assessmentmods = $this->fetch_feedback($USER)) {
            $template->showassessments = true;
            $template->assessmentheader = get_string('feedbackfor', 'block_my_feedback', $USER->firstname);
        }

        if (isset($template->markingmods) || isset($template->assessmentmods)) {
            $template->showfeedbacktrackerlink = true;
            $this->content->text = $OUTPUT->render_from_template('block_my_feedback/content', $template);
        }

        return $this->content;
    }

    /**
     * Return if user has required marker role at all.
     *
     * @return bool
     */
    private function is_marker(): bool {
        global $DB, $USER;

        if ($roles = $this->markerroles) {
            // Check if user has editingteacher role on any courses.
            [$roles, $params] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
            $params['userid'] = $USER->id;
            $sql = "SELECT id
                FROM {role_assignments}
                WHERE userid = :userid
                AND roleid $roles";
            return $DB->record_exists_sql($sql, $params);
        } else {
            return false;
        }
    }

    /**
     * Return if user has required marker role in given course.
     *
     * @param stdClass $course
     * @return bool
     */
    private function is_course_marker(stdClass $course): bool {
        global $USER;

        // Check if user has a marker role in the given course.
        foreach ($this->markerroles as $role) {
            if (user_has_role_assignment($USER->id, (int)$role, $course->ctxid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return if user has required student role at all.
     *
     * @return bool
     */
    private function is_student(): bool {
        global $DB, $USER;

        if ($roles = $this->studentroles) {
            // Check if user has a student role on any courses.
            [$roles, $params] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
            $params['userid'] = $USER->id;
            $sql = "SELECT id
                FROM {role_assignments}
                WHERE userid = :userid
                AND roleid $roles";
            return $DB->record_exists_sql($sql, $params);
        } else {
            return false;
        }
    }

    /**
     * Return marking for a user.
     *
     * @param stdClass $user
     * @return array|null
     */
    public function fetch_marking(stdClass $user): ?array {
        // Active user courses.
        $courses = enrol_get_all_users_courses($user->id, true, ['enddate']);
        // Marking.
        $marking = [];

        foreach ($courses as $course) {
            // Skip hidden or non-current courses.
            if (!$course->visible || !$this->is_course_current($course)) {
                continue;
            }

            // Skip if user has no teacher role in the course.
            if (!$this->is_course_marker($course)) {
                continue;
            }

            // Skip if no summative assessments.
            if (!$summatives = assess_type::get_assess_type_records_by_courseid($course->id, assess_type::ASSESS_TYPE_SUMMATIVE)) {
                continue;
            }

            // Get course mod ids.
            $modinfo = get_fast_modinfo($course->id);
            $mods = $modinfo->get_cms();
            $cmids = array_column($mods, 'id');

            // Loop through assessments for this course.
            foreach ($summatives as $summative) {
                // Skip if not a course module or cmid doesn't exist.
                if ($summative->cmid == 0 || !in_array($summative->cmid, $cmids)) {
                    continue;
                }

                // Begin to build mod data for template.
                $cmid = $summative->cmid;
                $mod = $modinfo->get_cm($cmid);

                // Skip hidden and unsupported mods.
                if (!$mod->visible || !feedback_tracker_helper::is_supported_module($mod->modname)) {
                    continue;
                }

                // Template.
                $assess = new stdClass();
                $assess->cmid = $cmid;
                $assess->modname = $mod->modname;
                $assess->name = $mod->name;
                $assess->coursename = $course->fullname;
                $assess->url = new moodle_url('/mod/' . $mod->modname . '/view.php', ['id' => $cmid]);
                // Todo - is this expensive?
                // If so should we only do it once we know we want to display it?
                $assess->icon = course_summary_exporter::get_course_image($course);

                $modulehelper = module_helper::create($mod);
                foreach ($modulehelper->get_marking_targets() as $target) {
                    $targetassess = clone $assess;
                    $targetassess->partid = $target->partid;

                    // Check mod target has duedate and requires marking.
                    if (!$this->add_mod_data($modulehelper, $targetassess, $target->duedate)) {
                        continue;
                    }

                    if (!empty($target->partname)) {
                        $targetassess->name = $mod->name . ' ' . $target->partname;
                    }

                    $marking[] = $targetassess;
                }
            }
        }

        // Sort and return data.
        if ($marking) {
            usort($marking, function ($a, $b) {
                return $a->unixtimestamp <=> $b->unixtimestamp;
            });

            return array_slice($marking, 0, 5);
        }
        return null;
    }

    /**
     * Return mod target data - due date & require marking.
     *
     * @param module_helper $modulehelper
     * @param stdClass $assess
     * @param int $duedate
     * @return bool
     */
    public function add_mod_data(module_helper $modulehelper, stdClass $assess, int $duedate): bool {
        // Check that mod has a due date, and the due date is in range.
        if (($duedate === 0) || !$this->duedate_in_range($duedate)) {
            return false;
        }

        // Check that mod has missing markings.
        $assess->requiremarking = $modulehelper->count_missing_grades(markeronly: true);
        if ($assess->requiremarking === 0) {
            return false;
        }

        // Add date for sorting and human-readable output.
        $assess->unixtimestamp = $duedate;
        $assess->duedate = date('jS M', $duedate);

        $assess->markingurl = $modulehelper->get_markingurl();

        // Return template data.
        return true;
    }

    /**
     * Return if course has started (startdate) and has not ended (enddate).
     *
     * @param stdClass $course
     * @return bool
     */
    public function is_course_current(stdClass $course): bool {
        // Check if the course has started.
        if ($course->startdate > time()) {
            return false;
        }

        // Check if the course has ended (with a 3-month grace period).
        if (
            isset($course->enddate) &&
            $course->enddate != 0 &&
            time() > strtotime('+3 month', $course->enddate)
        ) {
            return false;
        }

        // Course is within the valid date range.
        return true;
    }

    /**
     * Return if a due date is in the date range.
     *
     * @param int $duedate
     * @return int|null
     */
    public function duedate_in_range(int $duedate): ?int {
        $startdate = strtotime('-2 month');
        $cutoffdate = strtotime('+1 month');

        if ($duedate < $startdate || $duedate > $cutoffdate) {
            return null;
        }

        return $duedate;
    }

    /**
     * Get my feedback for a user.
     *
     * Return users 5 most recent feedbacks.
     *
     * @param stdClass $user
     * @return array|null feedback items.
     */
    public function fetch_feedback($user): ?array {
        global $DB;

        $submissions = $this->get_submissions($user);

        // No feedback.
        if (!$submissions) {
            return null;
        }

        // Template data for mustache.
        $feedbacks = [];
        $i = 0; // We only want to show up to 5 grades - so count the output.

        foreach ($submissions as $f) {
            $modinfo = get_fast_modinfo($f->course);
            $cms = $modinfo->get_instances_of($f->modname);
            $cm = $cms[$f->instance] ?? null;

            if (!$cm) {
                continue;
            }

            $modulehelper = module_helper::create($cm);
            $course = $DB->get_record('course', ['id' => $f->course], '*', MUST_EXIST);
            $feedbackdata = $modulehelper->build_student_feedback_data($f, $course);

            if (!$feedbackdata) {
                continue;
            }

            // Check if we have enough grades to show.
            if ($i++ >= 5) {
                break;
            }

            $feedback = new stdClass();
            $feedback->releaseddate = date('jS M', $f->lastmodified);
            $feedback->name = $f->name;
            $feedback->url = new moodle_url('/mod/' . $f->modname . '/view.php', ['id' => $f->cmid]);
            $feedback->coursename = $course->fullname;

            if ($feedbackdata->hidegrader) {
                $feedback->icon = course_summary_exporter::get_course_image($course);
            } else {
                $grader = core_user::get_user($f->grader);
                $userpicture = new user_picture($grader);
                $userpicture->size = 100;
                $icon = $userpicture->get_url($this->page)->out(false);
                $feedback->tutorname = fullname($grader);
                $feedback->icon = $icon;
            }

            $feedbacks[] = $feedback;
        }

        return $feedbacks ?: null;
    }

    /**
     * Get all submissions from supported module types for a user that are no older than 3 months.
     *
     * @param stdClass $user
     * @return array
     * @throws coding_exception
     */
    public function get_submissions($user) {
        $since = strtotime('-3 month');
        $supported = $this->get_supported_types();
        $courses = enrol_get_all_users_courses($user->id, true, ['enddate']);

        $submissions = [];

        foreach ($courses as $course) {
            if (!$course->visible || !$this->is_course_current($course)) {
                continue;
            }

            $modinfo = get_fast_modinfo($course->id);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || !in_array($cm->modname, $supported)) {
                    continue;
                }

                $modulehelper = module_helper::create($cm);
                $submissions = $submissions + $modulehelper->get_student_feedback_grade_records($user->id, $since);
            }
        }

        usort($submissions, fn($a, $b) => $b->lastmodified <=> $a->lastmodified);

        return $submissions;
    }

    /**
     * Return an array of supported module types.
     *
     * @return array
     */
    public function get_supported_types(): array {
        $supported = [];

        $types = [
            'assign',
            'coursework',
            'lesson',
            'manual',
            'quiz',
            'turnitintooltwo',
            'workshop',
        ];

        // Only include optional module types if they are supported by feedback tracker.
        foreach ($types as $modname) {
            if (PHPUNIT_TEST || feedback_tracker_helper::is_supported_module($modname)) {
                $supported[] = $modname;
            }
        }

        return $supported;
    }

    /**
     * Defines in which pages this block can be added.
     *
     * @return array of the pages where the block can be added.
     */
    public function applicable_formats() {
        return [
            'admin' => false,
            'site-index' => true,
            'course-view' => false,
            'mod' => false,
            'my' => true,
        ];
    }
}
