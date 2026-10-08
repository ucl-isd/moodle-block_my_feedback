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

    /** @var int|null User whose marker role assignments are cached. */
    private ?int $markerassignmentuserid = null;

    /** @var array<int, bool> Context IDs where the cached user has a marker role. */
    private array $markerassignmentcontexts = [];

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
        global $USER;

        return !empty($this->get_marker_assignment_contexts((int)$USER->id));
    }

    /**
     * Return if user has required marker role in given course.
     *
     * @param stdClass $course
     * @param int $userid
     * @return bool
     */
    private function is_course_marker(stdClass $course, int $userid): bool {
        $assignments = $this->get_marker_assignment_contexts($userid);
        if (!$assignments) {
            return false;
        }

        // As with user_has_role_assignment(), roles inherited from parent contexts count.
        $context = context::instance_by_id($course->ctxid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        foreach ($context->get_parent_context_ids(true) as $contextid) {
            if (isset($assignments[$contextid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Load marker role assignment contexts once per user and block instance.
     *
     * @param int $userid
     * @return array<int, bool>
     */
    private function get_marker_assignment_contexts(int $userid): array {
        global $DB;

        if ($this->markerassignmentuserid === $userid) {
            return $this->markerassignmentcontexts;
        }

        $this->markerassignmentuserid = $userid;
        $this->markerassignmentcontexts = [];
        if (!$this->markerroles) {
            return [];
        }

        [$rolesql, $params] = $DB->get_in_or_equal($this->markerroles, SQL_PARAMS_NAMED, 'role');
        $params['userid'] = $userid;
        $contextids = $DB->get_fieldset_select(
            'role_assignments',
            'contextid',
            "userid = :userid AND roleid $rolesql",
            $params
        );
        $this->markerassignmentcontexts = array_fill_keys(array_map('intval', $contextids), true);
        return $this->markerassignmentcontexts;
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
        $courses = enrol_get_all_users_courses($user->id, true, ['enddate']);
        $markercourses = [];
        foreach ($courses as $course) {
            if (!$course->visible || !$this->is_course_current($course)) {
                continue;
            }
            if (!$this->is_course_marker($course, (int)$user->id)) {
                continue;
            }
            $markercourses[(int)$course->id] = $course;
        }
        if (!$markercourses) {
            return null;
        }

        $summativesbycourse = assess_type::get_assess_type_records_by_courseids(
            array_keys($markercourses),
            assess_type::ASSESS_TYPE_SUMMATIVE
        );
        $candidates = [];
        $order = 0;
        foreach ($markercourses as $courseid => $course) {
            if (empty($summativesbycourse[$courseid])) {
                continue;
            }

            $modinfo = get_fast_modinfo($courseid);
            $mods = $modinfo->get_cms();
            $modulehelpers = [];
            foreach ($summativesbycourse[$courseid] as $summative) {
                $cmid = (int)$summative->cmid;
                $mod = $mods[$cmid] ?? null;
                if (!$mod) {
                    continue;
                }

                if (!$mod->visible || !feedback_tracker_helper::is_supported_module($mod->modname)) {
                    continue;
                }

                $modulehelpers[$cmid] ??= module_helper::create($mod);
                $modulehelper = $modulehelpers[$cmid];
                foreach ($modulehelper->get_marking_targets() as $target) {
                    if (!$target->duedate || !$this->duedate_in_range($target->duedate)) {
                        continue;
                    }
                    $candidates[] = (object)[
                        'course' => $course,
                        'mod' => $mod,
                        'modulehelper' => $modulehelper,
                        'target' => $target,
                        'order' => $order++,
                    ];
                }
            }
        }

        // Expensive missing-grade counts are only needed until five matching targets are found.
        usort($candidates, static fn($a, $b): int => $a->target->duedate <=> $b->target->duedate
            ?: $a->order <=> $b->order);
        $marking = [];
        $courseimages = [];
        foreach ($candidates as $candidate) {
            $course = $candidate->course;
            $mod = $candidate->mod;
            $target = $candidate->target;
            $assess = new stdClass();
            $assess->cmid = $mod->id;
            $assess->modname = $mod->modname;
            $assess->name = $mod->name;
            $assess->coursename = $course->fullname;
            $assess->partid = $target->partid;
            if (!$this->add_mod_data($candidate->modulehelper, $assess, $target->duedate)) {
                continue;
            }

            if (!empty($target->partname)) {
                $assess->name .= ' ' . $target->partname;
            }
            $assess->url = new moodle_url('/mod/' . $mod->modname . '/view.php', ['id' => $mod->id]);
            $courseimages[$course->id] ??= course_summary_exporter::get_course_image($course);
            $assess->icon = $courseimages[$course->id];
            $marking[] = $assess;
            if (count($marking) === 5) {
                break;
            }
        }

        return $marking ?: null;
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
        $modinfos = [];
        $modulehelpers = [];
        $courserecords = [];
        $courseimages = [];

        foreach ($submissions as $f) {
            $modinfos[$f->course] ??= get_fast_modinfo($f->course);
            $cms = $modinfos[$f->course]->get_instances_of($f->modname);
            $cm = $cms[$f->instance] ?? null;

            if (!$cm) {
                continue;
            }

            $modulehelpers[$cm->id] ??= module_helper::create($cm);
            $modulehelper = $modulehelpers[$cm->id];
            $courserecords[$f->course] ??= $DB->get_record('course', ['id' => $f->course], '*', MUST_EXIST);
            $course = $courserecords[$f->course];
            $feedbackdata = $modulehelper->build_student_feedback_data($f, $course);

            if (!$feedbackdata) {
                continue;
            }

            $feedback = new stdClass();
            $feedback->releaseddate = date('jS M', $f->lastmodified);
            $feedback->name = $f->name;
            $feedback->url = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
            $feedback->coursename = $course->fullname;

            if (!$feedbackdata->hidegrader && ($grader = core_user::get_user($f->grader))) {
                // If a grader can be found return tutor name and picture.
                $userpicture = new user_picture($grader);
                $userpicture->size = 100;
                $icon = $userpicture->get_url($this->page)->out(false);
                $feedback->tutorname = fullname($grader);
                $feedback->icon = $icon;
            } else {
                // Otherwise return course image.
                $courseimages[$f->course] ??= course_summary_exporter::get_course_image($course);
                $feedback->icon = $courseimages[$f->course];
            }

            $feedbacks[] = $feedback;
            if (count($feedbacks) === 5) {
                break;
            }
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
        global $DB;

        $since = strtotime('-3 month');
        $supported = $this->get_supported_types();
        $courses = enrol_get_all_users_courses($user->id, true, ['enddate']);
        $currentcourses = [];
        foreach ($courses as $course) {
            if ($course->visible && $this->is_course_current($course)) {
                $currentcourses[(int)$course->id] = true;
            }
        }
        if (!$currentcourses || !$supported) {
            return [];
        }

        $now = \core\di::get(\core\clock::class)->time();
        [$modulesql, $moduleparams] = $DB->get_in_or_equal($supported, SQL_PARAMS_NAMED, 'module');
        $submissions = [];
        $modinfos = [];
        foreach (array_chunk(array_keys($currentcourses), 500) as $courseids) {
            [$coursesql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
            $params = $moduleparams + $courseparams + [
                'userid' => $user->id,
                'since' => $since,
                'now1' => $now,
                'now2' => $now,
                'now3' => $now,
            ];
            // Match module_helper::get_student_feedback_grade_records() in one query for all candidate modules.
            $sql = "SELECT gg.id AS gradeid,
                           gi.courseid AS course,
                           gi.itemmodule AS modname,
                           gi.iteminstance AS instance,
                           gi.itemname AS name,
                           gg.userid AS userid,
                           gg.usermodified AS grader,
                           gg.timemodified AS lastmodified
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gg.itemid = gi.id
                     WHERE gg.userid = :userid
                       AND (gg.finalgrade IS NOT NULL OR gg.feedback IS NOT NULL)
                       AND gg.timemodified BETWEEN :since AND :now1
                       AND gg.hidden < :now3
                       AND gg.hidden <> 1
                       AND gi.courseid $coursesql
                       AND gi.itemmodule $modulesql
                       AND gi.hidden < :now2
                       AND gi.hidden <> 1
                  ORDER BY gg.timemodified DESC";
            foreach ($DB->get_records_sql($sql, $params) as $record) {
                $courseid = (int)$record->course;
                $modinfos[$courseid] ??= get_fast_modinfo($courseid);
                $cms = $modinfos[$courseid]->get_instances_of($record->modname);
                $cm = $cms[$record->instance] ?? null;
                if (!$cm || !$cm->uservisible) {
                    continue;
                }
                $submissions[$record->gradeid] = $record;
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
