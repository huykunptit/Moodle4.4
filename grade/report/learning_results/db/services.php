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
 * Grader grade report external functions and service definitions.
 *
 * @package    gradereport_learning_results_sort_table
 * @copyright  2022 Mathew May <Mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'gradereport_learning_results_sort_table' => [
        'classname' => 'gradereport_learning_results\\external\\gradereport_learning_results_external',
        'methodname' => 'sort_table',
        'description' => '',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'moodle/site:config',
    ],
];
