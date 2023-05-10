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
        // TODO - real url here please.
        $template->allfeedbackurl = new moodle_url('/report/myfeedback/index.php');
        $template->feedback = $this->fetch_feedback();
        if (!$template->feedback) {
            $template->nofeedback = true;
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

        // Query altered from assign messaging system.
        // TODO - Can probably be optimised.
        $sql = "SELECT g.id as gradeid, a.course, a.name, a.blindmarking, a.revealidentities, a.hidegrader, a.grade as maxgrade,
                       g.*, g.timemodified as lastmodified, cm.id as cmid, um.id as recordid
                 FROM {assign} a
                 JOIN {assign_grades} g ON g.assignment = a.id
            LEFT JOIN {assign_user_flags} uf ON uf.assignment = a.id AND uf.userid = g.userid
                 JOIN {course_modules} cm ON cm.course = a.course AND cm.instance = a.id
                 JOIN {modules} md ON md.id = cm.module AND md.name = 'assign'
                 JOIN {grade_items} gri ON gri.iteminstance = a.id AND gri.courseid = a.course AND gri.itemmodule = md.name
            LEFT JOIN {assign_user_mapping} um ON g.id = um.userid AND um.assignment = a.id
                 WHERE (a.markingworkflow = 0 OR (a.markingworkflow = 1 AND uf.workflowstate = :wfreleased)) AND
                       g.grader > 0 AND gri.hidden = 0 AND g.userid = :userid AND
                       g.timemodified >= :since AND g.timemodified <= :today
              ORDER BY g.timemodified
                 LIMIT 5";

        $params = array(
            'since' => $since,
            'today' => time(),
            'wfreleased' => 'released',
            'userid' => $USER->id,
        );
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
            $feedback->link = new moodle_url('/mod/assign/view.php', ['id' => $f->cmid]);

            // Course.
            $course = $DB->get_record('course', array('id' => $f->course));
            $feedback->coursename = $course->fullname;

            // Marker.
            if ($f->hidegrader) {
                // Hide grader, so use course image.
                // Course image.
                $course = new \core_course_list_element($course);
                foreach ($course->get_course_overviewfiles() as $file) {
                    $feedback->tutoricon = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename());
                }
            }

            else {
                // Marker details.
                $user = $DB->get_record('user', array('id' => $f->grader));
                $userpicture = new user_picture($user);
                $userpicture->size = 100;
                $icon = $userpicture->get_url($this->page)->out(false);
                $feedback->tutorname = $user->firstname.' '.$user->lastname;
                $feedback->tutoricon = $icon;
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

