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
        $this->title = get_string('feedbackfor', 'block_my_feedback').' '.$USER->firstname;
    }

    /**
     * Gets the block contents.
     *
     * @return stdClass The block content.
     */
    public function get_content() : stdClass {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $template = new stdClass();
        $template->allfeedbackurl = new moodle_url('/report/myfeedback/index.php');
        $template->feedback = $this->fetch_feedback();

        // Hide the block when no content.
        if (!$template->feedback) {
            return $this->content;
        }

        $this->content->text = $OUTPUT->render_from_template('block_my_feedback/content', $template);

        return $this->content;
    }

    /**
     *  Get my feedback call.
     *
     * @return array feedback items.
     */
    public function fetch_feedback() : array {
        global $CFG, $DB, $USER, $OUTPUT;
        // Return users 5 most recent feedbacks.

        // Limit to last 3 months.
        $since = time() - (2160 * 3600); // 2160 = 90 days from today as hours.

        // Construct the IN clause.
        list($insql, $params) = $DB->get_in_or_equal(array('assign', 'turnitintooltwo'), SQL_PARAMS_NAMED);

        // Add other params.
        $params['userid'] = $USER->id;
        $params['since'] = $since;
        $params['wfreleased'] = 'released'; // Has the grade been released?

        // Query the latest 5 modified grades / feedbacks for assignments and turnitin.
        $sql = "SELECT
                    gg.id AS gradeid,
                    gi.courseid AS course,
                    a.hidegrader AS hidegrader,
                    gi.itemname AS name,
                    gg.usermodified AS grader,
                    gg.timemodified AS lastmodified,
                    cm.id AS cmid,
                    gi.itemmodule AS modname
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
                        AND (IFNULL(a.markingworkflow, 0) = 0 OR (a.markingworkflow = 1 AND uf.workflowstate = :wfreleased))
                        AND gi.hidden < UNIX_TIMESTAMP()
                        AND gg.timemodified >= :since AND gg.timemodified <= UNIX_TIMESTAMP()
                        AND gg.userid = :userid
                ORDER BY gg.timemodified DESC
                LIMIT 5";

        $submissions = $DB->get_records_sql($sql, $params);

        // No feedback.
        if (!$submissions) {
            return array();
        }

        // Template data for mustache.
        $template = new stdClass();
        foreach ($submissions as $f) {
            $feedback = new stdClass();
            $feedback->id = $f->gradeid;
            $feedback->date = date('jS F', $f->lastmodified);
            $feedback->activityname = $f->name;
            $feedback->link = new moodle_url('/mod/'.$f->modname.'/view.php', ['id' => $f->cmid]);

            // Course.
            $course = $DB->get_record('course', array('id' => $f->course));
            $feedback->coursename = $course->fullname;

            // UCL want to always hide grader for turnitintooltwo.
            if ($f->modname == 'turnitintooltwo') {
                $f->hidegrader = true;
            }

            // Marker.
            if ($f->hidegrader) {
                // Hide grader, so use course image.
                // Course image.
                $feedback->icon = course_summary_exporter::get_course_image($course);
            } else {
                // Marker details.
                $user = core_user::get_user($f->grader);
                $userpicture = new user_picture($user);
                $userpicture->size = 100;
                $icon = $userpicture->get_url($this->page)->out(false);
                $feedback->tutorname = fullname($user);
                $feedback->icon = $icon;
            }

            $template->feedback[] = $feedback;
        }
        return  $template->feedback;
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
