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
 * Steps definitions.
 *
 * @package    block_my_feedback
 * @copyright  2025 onwards UCL <m.opitz@ucl.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode;
use local_assess_type\assess_type;

/**
 * Steps definitions.
 *
 * @package    block_my_feedback
 * @copyright  2025 onwards UCL <m.opitz@ucl.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_my_feedback extends behat_base
{
    /**
     * Allocates markers to submissions
     *
     * @Given /^I allocate the following markers for assignment "(?P<namesstring>[^"]*)":$/
     * @param string $assignname
     * @param TableNode $table
     * @return void
     */
    public function allocate_assignment_markers(string $assignname, TableNode $table): void {
        global $DB;

        $assignid = $DB->get_field('assign', 'id', ['name' => $assignname]);
        $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
        $allocations = $table->getHash();

        foreach ($allocations as $allocation) {
            $studentid = $DB->get_field('user', 'id', ['username' => $allocation['Student']]);
            $allocatedmarker = $DB->get_field('user', 'id', ['username' => $allocation['Marker']]);

            if ($record = $DB->get_record('assign_user_flags', ['assignment' => $assignid, 'userid' => $studentid])) {
                $record->allocatedmarker = $allocatedmarker;
                $DB->update_record('assign_user_flags', $record);
            } else {
                $record = new \stdClass();
                $record->assignment = $assignid;
                $record->userid = $studentid;
                $record->allocatedmarker = $allocatedmarker;
                $DB->insert_record('assign_user_flags', $record);
            }
        }

        rebuild_course_cache($cm->course, true);
    }

    /**
     * Update assessment type for an activity.
     *
     * @Given /^I set assessment type of activity "(?P<activityname>[^"]*)" to "(?P<assessmenttype>[^"]*)"$/
     * @param string $activityname
     * @param string $assessmenttype
     * @return void
     */
    public function i_set_assessment_type_of_activity_to(string $activityname, string $assessmenttype): void {
        global $DB;

        $mapping = [
            'formative' => assess_type::ASSESS_TYPE_FORMATIVE,
            'summative' => assess_type::ASSESS_TYPE_SUMMATIVE,
            'dummy' => assess_type::ASSESS_TYPE_DUMMY,
        ];

        $assessmenttype = strtolower(trim($assessmenttype));
        if (!array_key_exists($assessmenttype, $mapping)) {
            throw new \coding_exception('Unknown assessment type: ' . $assessmenttype);
        }

        $activity = $this->get_activity_by_name($activityname);

        $record = $DB->get_record('local_assess_type', ['cmid' => $activity->cmid]);
        if ($record) {
            $record->type = $mapping[$assessmenttype];
            $DB->update_record('local_assess_type', $record);
        } else {
            $record = new \stdClass();
            $record->cmid = $activity->cmid;
            $record->type = $mapping[$assessmenttype];
            $record->locked = 0;
            $DB->insert_record('local_assess_type', $record);
        }

        $cm = get_coursemodule_from_id($activity->modname, $activity->cmid, 0, false, MUST_EXIST);
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Set assignment or quiz due date using a strtotime-relative string (e.g. "now", "+2 months", "-3 months").
     *
     * @Given /^I set due date of activity "(?P<activityname>[^"]*)" to "(?P<relative>[^"]*)"$/
     * @param string $activityname
     * @param string $relative
     * @return void
     */
    public function i_set_due_date_of_activity_to(string $activityname, string $relative): void {
        global $DB;

        $activity = $this->get_activity_by_name($activityname);
        $cm = get_coursemodule_from_id($activity->modname, $activity->cmid, 0, false, MUST_EXIST);
        $courseid = $cm->course;

        $timestamp = strtotime($relative);
        if ($timestamp === false) {
            throw new \coding_exception('Invalid relative date string: ' . $relative);
        }

        switch ($activity->modname) {
            case 'assign':
                $DB->set_field('assign', 'duedate', $timestamp, ['id' => $activity->instanceid]);
                break;
            case 'quiz':
                $DB->set_field('quiz', 'timeclose', $timestamp, ['id' => $activity->instanceid]);
                break;
            case 'turnitintooltwo':
                $DB->set_field('turnitintooltwo_parts', 'dtdue', $timestamp, ['turnitintooltwoid' => $activity->instanceid]);
                break;
            default:
                throw new \coding_exception('Unsupported activity type for due date update: ' . $activity->modname);
        }

        rebuild_course_cache($courseid, true);
    }

    /**
     * Set activity visibility.
     *
     * @Given /^I set activity "(?P<activityname>[^"]*)" to "(?P<visibility>hidden|visible)"$/
     * @param string $activityname
     * @param string $visibility
     * @return void
     */
    public function i_set_activity_to_visibility(string $activityname, string $visibility): void {
        global $DB;

        $activity = $this->get_activity_by_name($activityname);
        $DB->set_field('course_modules', 'visible', $visibility === 'visible' ? 1 : 0, ['id' => $activity->cmid]);

        $cm = get_coursemodule_from_id($activity->modname, $activity->cmid, 0, false, MUST_EXIST);
        rebuild_course_cache($cm->course, true);
    }

    /**
     * Set course visibility.
     *
     * @Given /^I set course "(?P<courseshortname>[^"]*)" to "(?P<visibility>hidden|visible)"$/
     * @param string $courseshortname
     * @param string $visibility
     * @return void
     */
    public function i_set_course_to_visibility(string $courseshortname, string $visibility): void {
        global $DB;

        $DB->set_field('course', 'visible', $visibility === 'visible' ? 1 : 0, ['shortname' => $courseshortname]);

        $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Set course start date using a relative strtotime string.
     *
     * @Given /^I set course "(?P<courseshortname>[^"]*)" start date to "(?P<relative>[^"]*)"$/
     * @param string $courseshortname
     * @param string $relative
     * @return void
     */
    public function i_set_course_start_date_to(string $courseshortname, string $relative): void {
        global $DB;

        $timestamp = strtotime($relative);
        if ($timestamp === false) {
            throw new \coding_exception('Invalid relative date string: ' . $relative);
        }

        $DB->set_field('course', 'startdate', $timestamp, ['shortname' => $courseshortname]);

        $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Set course end date using a relative strtotime string.
     *
     * @Given /^I set course "(?P<courseshortname>[^"]*)" end date to "(?P<relative>[^"]*)"$/
     * @param string $courseshortname
     * @param string $relative
     * @return void
     */
    public function i_set_course_end_date_to(string $courseshortname, string $relative): void {
        global $DB;

        $timestamp = strtotime($relative);
        if ($timestamp === false) {
            throw new \coding_exception('Invalid relative date string: ' . $relative);
        }

        $DB->set_field('course', 'enddate', $timestamp, ['shortname' => $courseshortname]);

        $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Get activity data by name.
     *
     * @param string $activityname
     * @return \stdClass
     */
    private function get_activity_by_name(string $activityname): \stdClass {
        global $DB;

        $matches = [];
        $modules = $DB->get_records('modules', null, '', 'name');

        foreach ($modules as $module) {
            $tablename = $module->name;

            if (!$DB->get_manager()->table_exists($tablename)) {
                continue;
            }

            $columns = $DB->get_columns($tablename);
            if (!isset($columns['name'])) {
                continue;
            }

            $sql = "SELECT cm.id AS cmid, m.name AS modname, cm.instance AS instanceid
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {" . $tablename . "} modinstance ON modinstance.id = cm.instance
                     WHERE m.name = :modname AND modinstance.name = :activityname";

            $records = $DB->get_records_sql($sql, [
                'modname' => $module->name,
                'activityname' => $activityname,
            ]);

            foreach ($records as $record) {
                $matches[] = $record;
            }
        }

        if (count($matches) === 0) {
            throw new \coding_exception('Could not find activity with name: ' . $activityname);
        }

        if (count($matches) > 1) {
            throw new \coding_exception('Activity name is ambiguous (multiple modules found): ' . $activityname);
        }

        return reset($matches);
    }
}
