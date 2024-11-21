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
use local_assess_type\assess_type; // TODO - add in requires...
use mod_quiz\question\display_options;

/**
 * Block definition class for the block_my_feedback plugin.
 *
 * @package   block_my_feedback
 * @copyright 2023 Stuart Lamour
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_my_feedback extends block_base {

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        global $USER;

        // If $USER->firstname is not set yet do not try to use it.
        if (!isset($USER->firstname)) {
            $this->title = get_string('pluginname', 'block_my_feedback');
        } else {
            $this->title = get_string('feedbackfor', 'block_my_feedback').' '.$USER->firstname;
            if (self::is_teacher()) {
                $this->title = get_string('markingfor', 'block_my_feedback').' '.$USER->firstname;
            }
        }
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

        if (self::is_teacher()) {
            // Teacher content.
            $template->marking = $this->fetch_marking($USER);
        } else {
            // Student content.
            $template->feedback = $this->fetch_feedback($USER);
            $template->showfeedbacktrackerlink = true;
        }

        if (isset($template->feedback) || isset($template->marking)) {
            $this->content->text = $OUTPUT->render_from_template('block_my_feedback/content', $template);
        }

        return $this->content;
    }


    /**
     * Return marking for a user.
     *
     * @param stdClass $user
     */
    public function fetch_marking(stdClass $user): ?array {
        global $DB, $OUTPUT;
        // User courses.
        $courses = enrol_get_all_users_courses($user->id, false, ['enddate']);
        // Marking.
        $marking = [];

        foreach ($courses as $course) {
            // Skip hidden courses.
            if (!$course->visible) {
                continue;
            }
            // Skip none current course.
            if (!self::is_course_current($course)) {
                continue;
            }
            // Skip if no summative assessments.
            if (!$summatives = assess_type::get_assess_type_records_by_courseid($course->id, "1")) {
                continue;
            }

            $modinfo = get_fast_modinfo($course->id);

            foreach ($summatives as $summative) {

                // Check this is a course mod.
                if (isset($summative->cmid)) {
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
                    // Get due date and require marking.
                    $assess = self::get_mod_data($mod, $assess);

                    // Check mod has require marking (only set when there is a due date).
                    if (isset($assess->requiremarking)) {
                        // TODO - what is expensive here that we can do after sort and limit?
                        $assess->name = $mod->name;
                        $assess->coursename = $course->fullname;
                        $assess->url = new moodle_url('/mod/'. $mod->modname. '/view.php', ['id' => $cmid]);
                        $assess->icon = course_summary_exporter::get_course_image($course);
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
     * TODO - turnitin, quiz.
     *
     * @param stdClass $mod
     * @param stdClass $assess
     */
    public function get_mod_data($mod, $assess) {
        global $CFG;
        // Mods have different feilds for due date, and require marking.
        switch ($mod->modname) {
            case 'assign':

                // Check mod due date is relevant.
                $duedate = self::duedate_in_range($mod->customdata['duedate']);
                if (!$duedate) {
                    return false;
                }

                // Add dates.
                $assess->unixtimestamp = $duedate;
                $assess->duedate = date('jS F y', $duedate);

                // Require marking.
                require_once($CFG->dirroot.'/mod/assign/locallib.php');
                $context = context_module::instance($mod->id);
                $assignment = new assign($context, $mod, $mod->course);
                $assess->requiremarking = $assignment->count_submissions_need_grading();
                if (!$assess->requiremarking) {
                    return false;
                }
                $assess->markingurl = new moodle_url('/mod/'. $mod->modname. '/view.php',
                    ['id' => $assess->cmid, 'action' => 'grader']
                );
                // Return template data.
                return $assess;

            // TODO - quiz - 'timeclose' ?.
            case 'quiz':
                return false;
            // TODO - turnitin.
            default:
                return false;
        }
    }

    /**
     * Return if course has started (startdate) and has not ended (enddate).
     *
     * @param stdClass $course
     */
    public function is_course_current(stdClass $course): bool {
        // Start date.
        if ($course->startdate > time()) {
            return false; // Before the start date.
        }

        // End date.
        if (isset($course->enddate)) {
            if ($course->enddate == 0) {
                return true; // Enddate is set to 0 when no end date, show course.
            }
            if (time() > $course->enddate) {
                return false; // After the end date.
            }
        }
        return true; // All good, show course.
    }

    /**
     * Return if a due date in the date range.
     *
     * @param int $duedate
     */
    public function duedate_in_range(int $duedate): ?int {
        // Only show dates within a month.
        $past = strtotime('-1 month');
        $future = strtotime('+1 month');
        // If due date is too far in the future.
        if ($duedate > $future) {
            return false;
        }
        // If due date is too far in the past.
        if ($duedate < $past) {
            return false;
        }
        return $duedate;
    }

    /**
     *  Get my feedback call for a user.
     *
     * Return users 5 most recent feedbacks.
     * @param stdClass $user
     * @return array feedback items.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
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
            $feedback->date = date('jS F', $f->lastmodified);
            $feedback->activityname = $f->name;
            $feedback->link = new moodle_url('/mod/'.$f->modname.'/view.php', ['id' => $f->cmid]);

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

        if ($template->feedback) {
            return $template->feedback;
        }
        return null;
    }

    /**
     * Get all assign, quiz and turnitintooltwo submissions for a user that are no older than 3 month.
     *
     * @param stdClass $user
     * @return array
     * @throws coding_exception
     * @throws dml_exception
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
     * @throws dml_exception
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
     * Return if user has archetype editingteacher.
     *
     */
    public static function is_teacher(): bool {
        global $DB, $USER;

        // Get id's from role where archetype is editingteacher.
        $roles = $DB->get_fieldset('role', 'id', ['archetype' => 'editingteacher']);

        // Check if user has editingteacher role on any courses.
        list($roles, $params) = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);
        $params['userid'] = $USER->id;
        $sql = "SELECT id
                FROM {role_assignments}
                WHERE userid = :userid
                AND roleid $roles";
        return  $DB->record_exists_sql($sql, $params);
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
