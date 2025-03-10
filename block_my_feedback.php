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

use core\context\user;
use core_course\external\course_summary_exporter;
use local_assess_type\assess_type; // UCL plugin.
use mod_quiz\question\display_options;
use report_feedback_tracker\local\admin as feedback_tracker; // UCL plugin.

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
    private static array $markerroles;

    /**
     * @var array array of roles a student may have.
     */
    private static array $studentroles;

    /**
     * @var bool marker status.
     */
    private static bool $ismarker;

    /**
     * @var bool student status.
     */
    private static bool $isstudent;

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        self::$markerroles = self::get_marker_role_ids();
        self::$studentroles = self::get_student_role_ids();
        self::$ismarker = self::is_marker();
        self::$isstudent = self::is_student();

        // No title for the block as each section will have one.
        $this->title = '';
    }

    /**
     * Get the marker role IDs.
     *
     * @return array
     */
    private static function get_marker_role_ids(): array {
        global $DB;

        return $DB->get_fieldset_select('role', 'id',
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
    private static function get_student_role_ids(): array {
        global $DB;

        return $DB->get_fieldset_select('role', 'id',
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
        if (self::$ismarker && $template->markingmods = self::fetch_marking($USER)) {
            $template->showmarkings = true;
            $template->markingheader = get_string('markingfor', 'block_my_feedback', $USER->firstname);
        }

        // Student content.
        if (self::$isstudent && $template->assessmentmods = $this->fetch_feedback($USER)) {
            $template->showfeedbacktrackerlink = true;
            $template->showassessments = true;
            $template->assessmentheader = get_string('feedbackfor', 'block_my_feedback', $USER->firstname);
        }

        if (isset($template->markingmods) || isset($template->assessmentmods)) {
            $this->content->text = $OUTPUT->render_from_template('block_my_feedback/content', $template);
        }

        return $this->content;
    }

    /**
     * Return if user has required marker role at all.
     *
     * @return bool
     */
    private static function is_marker(): bool {
        global $DB, $USER;

        if ($roles = self::$markerroles) {
            // Check if user has editingteacher role on any courses.
            list($roles, $params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
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
    private static function is_course_marker(stdClass $course): bool {
        global $USER;

        // Check if user has a merker role in the given course.
        foreach (self::$markerroles as $role) {
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
    private static function is_student(): bool {
        global $DB, $USER;

        if ($roles = self::$studentroles) {
            // Check if user has a student role on any courses.
            list($roles, $params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
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
     */
    public static function fetch_marking(stdClass $user): ?array {
        global $DB;
        // User courses.
        $courses = enrol_get_all_users_courses($user->id, false, ['enddate']);
        // Marking.
        $marking = [];

        foreach ($courses as $course) {
            // Skip hidden or non-current courses.
            if (!$course->visible || !self::is_course_current($course)) {
                continue;
            }

            // Skip if user has no teacher role in the course.
            if (!self::is_course_marker($course)) {
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

                // Skip hidden mods.
                if (!$mod->visible) {
                    continue;
                }

                // Template.
                $assess = new stdClass;
                $assess->cmid = $cmid;
                $assess->modname = $mod->modname;
                $assess->name = $mod->name;
                $assess->coursename = $course->fullname;
                $assess->url = new moodle_url('/mod/'. $mod->modname. '/view.php', ['id' => $cmid]);
                // TODO - is this expensive?
                // If so should we only do it once we know we want to display it?
                $assess->icon = course_summary_exporter::get_course_image($course);

                // Turnitin.
                if ($assess->modname === 'turnitintooltwo') {
                    // Fetch parts.
                    $turnitinparts = \report_feedback_tracker\local\helper::get_turnitin_parts($mod->instance);
                    foreach ($turnitinparts as $turnitinpart) {
                        $turnitin = clone $assess;
                        $turnitin->partid = $turnitinpart->id;
                        // Check mod has duedate and require marking.
                        if (self::add_mod_data($mod, $turnitin)) {
                            $turnitin->name = $mod->name . ' ' . $turnitinpart->partname;
                            $marking[] = $turnitin;
                        }
                    }
                } else {
                    // Check mod has duedate and require marking.
                    if (\report_feedback_tracker\local\helper::is_supported_module($mod->modname) &&
                            self::add_mod_data($mod, $assess)) {
                        $marking[] = $assess;
                    }
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
     * Return mod data - due date & require marking.
     *
     * @param cm_info $mod
     * @param stdClass $assess
     * @return bool
     */
    public static function add_mod_data(cm_info $mod, stdClass $assess): bool {
        global $CFG, $DB;

        // Get duedate.
        if ($mod->modname === 'turnitintooltwo') {
            $duedate = $DB->get_field('turnitintooltwo_parts', 'dtdue', ['id' => $assess->partid], );
        } else {
            $duedate = feedback_tracker::get_duedate($mod);
        }

        // Check mod has due date, and due date is in range.
        if (($duedate === 0) || !self::duedate_in_range($duedate)) {
            return false;
        }

        // Return null if no duedate or no marking.
        if (!$assess->requiremarking = feedback_tracker::count_missing_grades($mod)) {
            return false;
        }

        // Add date for sorting and human-readable output.
        $assess->unixtimestamp = $duedate;
        $assess->duedate = date('jS M', $duedate);

        $assess->markingurl = feedback_tracker::get_markingurl($mod);

        // Return template data.
        return true;
    }

    /**
     * Return if course has started (startdate) and has not ended (enddate).
     *
     * @param stdClass $course
     */
    public static function is_course_current(stdClass $course): bool {
        // Check if the course has started.
        if ($course->startdate > time()) {
            return false;
        }

        // Check if the course has ended (with a 3-month grace period).
        if (isset($course->enddate) &&
            $course->enddate != 0 && time() > strtotime('+3 month', $course->enddate)) {
            return false;
        }

        // Course is within the valid date range.
        return true;
    }

    /**
     * Return if a due date in the date range.
     *
     * @param int $duedate
     */
    public static function duedate_in_range(int $duedate): ?int {
        $startdate = strtotime('-2 month');
        $cutoffdate = strtotime('+1 month');

        if ($duedate < $startdate || $duedate > $cutoffdate) {
            return null;
        }

        return $duedate;
    }

    /**
     *  Get my feedback call for a user.
     *
     * Return users 5 most recent feedbacks.
     * @param stdClass $user
     * @return array feedback items.
     */
    public function fetch_feedback($user): ?array {
        global $DB;

        $submissions = $this->get_submissions($user);

        // No feedback.
        if (!$submissions) {
            return null;
        }

        // Template data for mustache.
        $template = new stdClass();
        $template->feedback = [];
        $i = 0; // We only want to show up to 5 grades - so count the output.

        foreach ($submissions as $f) {
            // Check if a quiz feedback should be shown.
            if ($f->modname == 'quiz' && !$this->show_quiz_submission($f)) {
                continue;
            }

            // Check if we have enough grades to show.
            if ($i++ >= 5) {
                break;
            }

            $feedback = new stdClass();
            $feedback->id = $f->gradeid;
            $feedback->releaseddate = date('jS M', $f->lastmodified);
            $feedback->name = $f->name;
            $feedback->url = new moodle_url('/mod/'.$f->modname.'/view.php', ['id' => $f->cmid]);

            // Course.
            $course = $DB->get_record('course', ['id' => $f->course]);
            $feedback->coursename = $course->fullname;

            // UCL want to always hide grader for quiz and turnitintooltwo.
            if ($f->modname == 'quiz' || $f->modname == 'turnitintooltwo') {
                $f->hidegrader = true;
            }

            // Marker.
            if ($f->hidegrader) {
                // Hide grader, so use course image.
                // Course image.
                $feedback->icon = course_summary_exporter::get_course_image($course);
            } else {
                // Marker details.
                $grader = core_user::get_user($f->grader);
                $userpicture = new user_picture($grader);
                $userpicture->size = 100;
                $icon = $userpicture->get_url($this->page)->out(false);
                $feedback->tutorname = fullname($grader);
                $feedback->icon = $icon;
            }

            $template->feedback[] = $feedback;
        }

        return $template->feedback ?: null;
    }

    /**
     * Get all assign, quiz and turnitintooltwo submissions for a user that are no older than 3 month.
     *
     * @param stdClass $user
     * @return array
     * @throws coding_exception
     */
    public function get_submissions($user) {
        global $DB;
        // Limit to last 3 months.
        $since = strtotime('-3 month');
        // Construct the IN clause.
        list($insql, $params) = $DB->get_in_or_equal(['assign', 'quiz', 'turnitintooltwo'], SQL_PARAMS_NAMED);

        // Add other params.
        $params['userid'] = $user->id;
        $params['since'] = $since;
        $params['wfreleased'] = 'released'; // Has the grade been released?

        $unixtimestamp = time();

        // Query the modified grades / feedbacks for assignments, quizzes and turnitin.
        $sql = "SELECT
                    gg.id AS gradeid,
                    gi.courseid AS course,
                    a.hidegrader AS hidegrader,
                    gi.itemname AS name,
                    gg.userid AS userid,
                    gg.usermodified AS grader,
                    gg.timemodified AS lastmodified,
                    cm.id AS cmid,
                    gi.itemmodule AS modname,
                    cm.instance as instance
                FROM
                    {grade_grades} gg
                        JOIN
                    {grade_items} gi ON gg.itemid = gi.id
                        JOIN
                    {modules} m ON gi.itemmodule = m.name
                        JOIN
                    {course_modules} cm ON gi.courseid = cm.course AND m.id = cm.module AND gi.iteminstance = cm.instance
                        JOIN
                    {user} u ON gg.usermodified = u.id
                        LEFT JOIN
                    {assign} a ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
                        LEFT JOIN
                    {assign_user_flags} uf ON gg.userid = uf.userid AND a.id = uf.assignment AND a.markingworkflow = 1
                WHERE
                    (gg.finalgrade IS NOT NULL OR gg.feedback IS NOT NULL)
                        AND gi.itemmodule $insql
                        AND (COALESCE(a.markingworkflow, 0) = 0 OR (a.markingworkflow = 1 AND uf.workflowstate = :wfreleased))
                        AND gi.hidden < $unixtimestamp
                        AND gg.timemodified >= :since AND gg.timemodified <= $unixtimestamp
                        AND gg.userid = :userid
                ORDER BY gg.timemodified DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Checking Review options for showing quiz submissions.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function show_quiz_submission(stdClass $submission): bool {
        global $DB;

        $quizobj = $DB->get_record('quiz', ['id' => $submission->instance]);

        if ($quizobj->timeclose > 0 && $quizobj->timeclose < time()) { // The quiz is closed.
            $reviewoptions = display_options::make_from_quiz($quizobj, display_options::AFTER_CLOSE);
        } else {
            $reviewoptions = display_options::make_from_quiz($quizobj, display_options::LATER_WHILE_OPEN);
        }

        // Only when these options are all set the submission should be shown.
        // NB: when maxmarks and marks are both set $reviewoptions->marks == 2.
        if ($reviewoptions->attempt + $reviewoptions->correctness + $reviewoptions->marks == 4) {
            return true;
        }

        return false;
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
