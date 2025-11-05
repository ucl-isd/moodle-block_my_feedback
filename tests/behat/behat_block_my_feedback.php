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
        global $DB;

        $assignid = $DB->get_field('assign', 'id', ['name' => $assignname]);
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
    }
}
