<?php
// This file is part of Moodle - https://moodle.org/
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
 * Site-wide settings for the vplquestion type.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/locallib.php');


$settings->add(new admin_setting_heading(
        QVPL.'/generalsettings',
        get_string('cfg:generalsettings', QVPL),
        get_string('cfg:generalsettings_help', QVPL)
        ));

$settings->add(new admin_setting_configcheckbox(
        QVPL.'/deletevplsubmissions',
        get_string('cfg:deletevplsubmissions', QVPL),
        get_string('cfg:deletevplsubmissions_help', QVPL),
        '1'
        ));
