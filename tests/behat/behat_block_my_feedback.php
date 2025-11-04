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

use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Steps definitions.
 *
 * @package    block_my_feedback
 * @copyright  2025 onwards UCL <m.opitz@ucl.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_my_feedback extends behat_base {
    /**
     * Select the submissions of the given student(s).
     *
     * Example: I select the submissions of "Student 1, Student 2"
     *
     * @Given /^I select the submissions of "(?P<namesstring>[^"]*)"$/
     * @param string $namesstring the name(s) of the students to select
     * @return void
     */
    public function i_select_the_submissions_of(string $namesstring): void {
        $names = array_map('trim', explode(',', $namesstring));
        foreach ($names as $name) {
            $xpath = "//tr[contains(., '{$name}')]//input[@name='selectedusers']";
            $this->find('xpath', $xpath)->check();
        }
    }
}
