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
 * Version details
 *
 * @package   block_my_feedback
 * @copyright 2023 Stuart Lamour
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024112800;            // The current plugin version (Date: YYYYMMDDXX).
$plugin->release   = '2.0';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->requires  = 2024100700;            // Requires at least Moodle version 4.2.
$plugin->component = 'block_my_feedback';   // Full name of the plugin (used for diagnostics).
$plugin->dependencies = [
    'local_assess_type' => 2024091300,
    'report_feedback_tracker' => 2025010600,
];

