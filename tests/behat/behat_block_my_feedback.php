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

/**
 * Steps definitions.
 *
 * @package    block_my_feedback
 * @copyright  2025 onwards UCL <m.opitz@ucl.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_my_feedback extends behat_base {
    /**
     * Allocates markers to submissions
     *
     * @Given /^I allocate the following markers for assignment "(?P<namesstring>[^"]*)":$/
     * @param string $assignname
     * @param TableNode $table
     * @return void
     */
    public function allocate_assignment_markers(string $assignname, TableNode $table): void {
        global $CFG, $DB;

        $assignid = $DB->get_field('assign', 'id', ['name' => $assignname], MUST_EXIST);
        $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
        $allocations = $table->getHash();

        foreach ($allocations as $allocation) {
            $studentid = $DB->get_field('user', 'id', ['username' => $allocation['Student']], MUST_EXIST);
            $allocatedmarker = $DB->get_field('user', 'id', ['username' => $allocation['Marker']], MUST_EXIST);

            // From MOODLE_502_STABLE on allocated markers have their own database table.
            if ($CFG->branch >= 502) {
                if ($record = $DB->get_record('assign_allocated_marker', ['assignment' => $assignid, 'student' => $studentid])) {
                    $record->marker = $allocatedmarker;
                    $DB->update_record('assign_allocated_marker', $record);
                } else {
                    $record = new \stdClass();
                    $record->assignment = $assignid;
                    $record->student = $studentid;
                    $record->marker = $allocatedmarker;
                    $DB->insert_record('assign_allocated_marker', $record);
                }
            } else {
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
        }

        rebuild_course_cache($cm->course, true);
    }

    /**
     * Create finished quiz attempts for users.
     *
     * @Given /^the following quiz attempts exist:$/
     * @param TableNode $table
     * @return void
     */
    public function the_following_quiz_attempts_exist(TableNode $table): void {
        global $DB;

        foreach ($table->getHash() as $row) {
            $activity = $this->get_activity_by_name($row['quiz']);
            if ($activity->modname !== 'quiz') {
                throw new \coding_exception('Activity is not a quiz: ' . $row['quiz']);
            }

            $userid = $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $attempt = new \stdClass();
            $attempt->quiz = $activity->instanceid;
            $attempt->userid = $userid;
            $attempt->attempt = 1;
            $attempt->uniqueid = 0;
            $attempt->layout = '';
            $attempt->currentpage = 0;
            $attempt->preview = 0;
            $attempt->state = 'finished';
            $attempt->timestart = time() - HOURSECS;
            $attempt->timefinish = time() - MINSECS;
            $attempt->timemodified = time() - MINSECS;
            $attempt->timecheckstate = 0;
            $attempt->sumgrades = 0;
            $DB->insert_record('quiz_attempts', $attempt);

            $cm = get_coursemodule_from_id('quiz', $activity->cmid, 0, false, MUST_EXIST);
            rebuild_course_cache($cm->course, true);
        }
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
