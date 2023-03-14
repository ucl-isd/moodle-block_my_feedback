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
 * @copyright Year, You Name <your@email.address>
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
     * @return string The block HTML.
     */
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $template = new stdClass();
        $template->hello = 'hello world';
        // TODO - real url here please.
        $template->allfeedbackurl = new moodle_url('/course/view.php');
        $template->feedback = $this->fetch_feedback();
        if (!$template->feedback) {
            $template->nofeedback = true;
        }
        // TODO - empty state.
        $this->content->text = $OUTPUT->render_from_template('block_my_feedback/content', $template);

        return $this->content;
    }

     /**
     *  Get my feedback call.
     * 
     * @return array feedback items.
     */
    public function fetch_feedback(): array {
        // TODO - Return users 5 most recent feedbacks.
        // Limit to last 3 months.
        // Loop through feedbacks and add to template.
        $template = new stdClass();
        $template->feedback = array();
        $feedbacks = array();
        for ($x = 0; $x <= 5; $x++) {
        
        // foreach ($feedbacks as $f) {
            $feedback = new stdClass();
            /*
            $feedback->date = $f->date;
            $feedback->tutorname = $f->tutorname;
            $feedback->iconurl = $f->iconurl;
            $feedback->coursename = $f->coursename;
            $feedback->activityname = $f->activityname;
            */
            // Dummy data.
            $feedback->date = "24th March";
            $feedback->tutorname = "Stuart Lamour";
            $feedback->iconurl = "https://randomuser.me/api/portraits/women/16.jpg";
            $feedback->coursename = "Course name";
            $feedback->activityname = "Activity name";
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