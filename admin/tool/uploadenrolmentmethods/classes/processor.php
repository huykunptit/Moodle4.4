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
 * Library of functions for uploading a course enrolment methods CSV file.
 *
 * @package    tool_uploadenrolmentmethods
 * @copyright  2020 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/lib/enrollib.php');
require_once($CFG->dirroot.'/enrol/meta/locallib.php');
require_once($CFG->dirroot.'/enrol/cohort/lib.php');
require_once($CFG->dirroot.'/enrol/cohort/locallib.php');
require_once($CFG->dirroot.'/admin/tool/uploaduser/locallib.php');


/**
 * Validates and processes files for uploading a course enrolment methods CSV file
 *
 * @copyright  2013 Fr�d�ric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadenrolmentmethods_processor {

    /** @var csv_import_reader */
    protected $cir;

    /** @var array CSV columns. */
    protected $columns = array();

    /** @var int line number. */
    protected $linenb = 0;

    /**
     * Constructor, sets the CSV file reader
     *
     * @param csv_import_reader $cir import reader object
     */
    public function __construct(csv_import_reader $cir) {
        $this->cir = $cir;
        $this->columns = $cir->get_columns();
        $this->validate();
        $this->reset();
        $this->linenb++;
    }

    /**
     * Processes the file to handle the enrolment methods
     *
     * Opens the file, loops through each row. Cleans the values in each column,
     * checks that the operation is valid and the methods exist. If all is well,
     * adds, updates or deletes the enrolment method metalink in column 3 to/from the course in column 2
     * context as specified.
     * Returns a report of successes and failures.
     *
     * @see open_file()
     * @uses enrol_meta_sync() Meta plugin function for syncing users
     * @return string A report of successes and failures.
     *
     * @param object $tracker the output tracker to use.
     * @return void
     */
    public function execute($tracker = null) {
        global $DB;

        if (empty($tracker)) {
            $tracker = new tool_uploadcourse_tracker(tool_uploadcourse_tracker::NO_OUTPUT);
        }

        // Initialise the output heading row labels.
        $reportheadings = array('line' => get_string('csvline', 'tool_uploadcourse'),
            'oplabel' => get_string('operation', 'tool_uploadenrolmentmethods'),
            'methodname' => get_string('enrolmentmethod', 'enrol'),
            'shortname' => get_string('shortnamecourse'),
            'courseid' => get_string('id', 'tool_uploadcourse'),
            'metacohort' => get_string('linkedcourse', 'enrol_meta') . '/' . get_string('cohort', 'cohort'),
            'groupname' => get_string('group'),
            'role' => get_string('role'),
            'status' => get_string('participationstatus', 'enrol'),
            'result' => get_string('result', 'tool_uploadenrolmentmethods')
            );
        $tracker->start($reportheadings, true);

        // Trace debug messages.
        $trace = new text_progress_trace();
        // Initialise some counters to summarise the results.
        $total = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        $errors = 0;

        // We will most certainly need extra time and memory to process big files.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $report = array();

        // Prepare reporting message strings.
        $messagerow = array();
        $messagerow['status'] = get_string('statusenabled', 'enrol_manual');
        $messagerow['role'] = 'student';

        // Course roles lookup cache.
        $rolecache = uu_allowed_roles_cache();

        // Define some class variables.
        $target = new stdClass();
        $parent = new stdClass();

        // Check if Profile fields method is installed.
        $attributesinstalled = array_key_exists('attributes', core_plugin_manager::instance()->get_plugins_of_type('enrol'));

        // Loop through each row of the file.
        while ($line = $this->cir->next()) {
            $this->linenb++;
            $total++;

            // Read in and process one data line from the CSV file.
            $data = $this->parse_line($line);
            $op = $data['operation'];
            $method = $data['method'];
            $targetshortname = $data['shortname'];
            $sourcelabel = $data['metacohort'];
            $disabledstatus = $data['disabled'];
            $groupname = $data['group'];
            // Handle optional role field, if blank, use 'student' as the default.
            $rolename = 'student';
            if (isset($data['role']) && $data['role'] !== '' ) {
                $rolename = $data['role'];
                $messagerow['role'] = $rolename;
            }
            if (isset($data['attributes']) && $data['attributes'] !== '' ) {
                $attributes = $data['attributes'];
                $messagerow['attributes'] = $attributes;
            }

            // Add line-specific reporting message strings.
            $messagerow['line'] = $this->linenb;
            $messagerow['methodname'] = get_string('pluginname', 'enrol_' . $method);
            $messagerow['shortname'] = $targetshortname;
            $messagerow['metacohort'] = $sourcelabel;
            $messagerow['groupname'] = $groupname;

            if ($op == 'add') {
                $messagerow['oplabel'] = get_string('add');
            } else if ($op == 'del' || $op == 'delete') {
                $messagerow['oplabel'] = get_string('delete');
                $op = 'del';
            } else if ($op == 'upd' || $op == 'update' || $op == 'mod' || $op == 'modify') {
                $messagerow['oplabel'] = get_string('update');
                $op = 'upd';
            }

            // Need to check the line is valid. If not, add a message to the report and skip the line.

            // Check we've got a valid operation.
            if (!in_array($op, array('add', 'del', 'upd'))) {
                $errors++;
                $messagerow['result'] = get_string('invalidop', 'tool_uploadenrolmentmethods');
                $tracker->output($messagerow, false);
                continue;
            }
            // Check we've got a valid method.
            if (!in_array($method, array('meta', 'cohort', 'attributes'))) {
                $errors++;
                $messagerow['result'] = get_string('invalidmethod', 'tool_uploadenrolmentmethods');
                $tracker->output($messagerow, false);
                continue;
            }

            // If using the attributes profile fields method, check if it is installed.
            if ($method == 'attributes') {
                if (!$attributesinstalled) {
                    // Not installed, so skip this line.
                    $errors++;
                    $messagerow['result'] = get_string('attributesnotinstalled', 'tool_uploadenrolmentmethods', $sourcelabel);
                    $tracker->output($messagerow, false);
                    continue;
                } else {
                    // Installed, so include its class.
                    global $CFG;
                    require_once($CFG->dirroot.'/enrol/attributes/lib.php');
                }
            }

            // Check the enrolment method is enabled.
            if (!enrol_is_enabled($method)) {
                $errors++;
                $messagerow['result'] = get_string('methoddisabledwarning', 'tool_uploadenrolmentmethods',
                    get_string('pluginname', 'enrol_' . $method));
                $tracker->output($messagerow, false);
                continue;
            }

            // Check that the target course we're assigning the method to exists.
            if (!$target = $DB->get_record('course', array('shortname' => $targetshortname))) {
                $errors++;
                $messagerow['result'] = get_string('targetnotfound', 'tool_uploadenrolmentmethods');
                $tracker->output($messagerow, false);
                continue;
            }
            $messagerow['courseid'] = $target->id;

            // Check that the parent metacourse or cohort we're assigning exists.
            if ($method == 'meta' && !($parent = $DB->get_record('course', array('shortname' => $sourcelabel)))) {
                $errors++;
                $messagerow['result'] = get_string('parentnotfound', 'tool_uploadenrolmentmethods');
                $messagerow['parentid'] = $parent->id;
                $tracker->output($messagerow, false);
                continue;
            } else if ($method == 'cohort' && (!$parent = $DB->get_record('cohort', array('idnumber' => $sourcelabel)))) {
                // Check the cohort we're syncing exists.
                $errors++;
                $messagerow['result'] = get_string('cohortnotfound', 'tool_uploadenrolmentmethods');
                $messagerow['parentid'] = $parent->id;
                $tracker->output($messagerow, false);
                continue;
            } else if ($method == 'attributes') {
                // The method must exist for updates and deletions, and not for additions.
                // Attributes method names don't actually have to be unique, but we don't allow it.
                $attrecords = $DB->get_records('enrol', array('courseid' => $target->id, 'name' => $sourcelabel));
                if ($op == 'add' && count($attrecords) > 0) {
                    // Check the method doesn't already exist, even though technically its allowed.
                    $errors++;
                    $messagerow['result'] = get_string('relalreadyexists', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                    continue;
                } else if ($op != 'add' && count($attrecords) != 1) {
                    // Check the enrolment method we're processing exists.
                    $errors++;
                    $messagerow['result'] = get_string('attributesnotfound', 'tool_uploadenrolmentmethods', $sourcelabel);
                    $tracker->output($messagerow, false);
                    continue;
                }
                if (count($attrecords) == 0) {
                    $parent->id = null; // Set the Parent ID just to avoid an error message.
                } else {
                    $parent = $DB->get_record('enrol', array('courseid' => $target->id, 'name' => $sourcelabel));
                }
            }

            // Check that a valid role is specified.
            if (!array_key_exists($rolename, $rolecache)) {
                $errors++;
                $messagerow['result'] = get_string('unknownrole', 'error', $rolename);
                $tracker->output($messagerow, false);
                continue;
            } else {
                $roleid = $rolecache[$rolename]->id;
            }

            // Checks are mostly now done, so let's get to work.

            $messagerow['targetid'] = $target->id;

            $enrol = enrol_get_plugin($method);

            $instanceparams = array(
                'courseid' => $target->id,
                'customint1' => $parent->id,
                'enrol' => $method,
                'roleid' => $roleid
            );

            if ($op == 'del') {
                // Deleting, so check the parent is already linked to the target, and remove the link.
                // Skip the line if they're not linked.

                // Handle the special case of profile fields, by including the method name.
                // TODO: the name may not be unique, but we assume it is for the moment.
                if ($method == 'attributes') {
                    $instanceparams['name'] = $sourcelabel;
                }

                if ($instance = $DB->get_record('enrol', $instanceparams)) {
                    $enrol->delete_instance($instance);
                    $deleted++;
                    $messagerow['role'] = '';
                    $messagerow['status'] = '';
                    $messagerow['groupname'] = '';
                    $messagerow['result'] = get_string('eventenrolinstancedeleted', 'enrol');
                    $tracker->output($messagerow, true);
                } else {
                    $errors++;
                    $messagerow['result'] = get_string('reldoesntexist', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                }
            } else if ($op == 'upd') {
                // Updating, so check the parent is already linked to the target, and change the status.
                // Skip the line if they're not.

                // TODO: the name may not be unique, but we assume it is for the moment.
                if ($method == 'attributes') {
                    $instanceparams['name'] = $sourcelabel;
                    unset($instanceparams['customint1']);
                }
                if ($instance = $DB->get_record('enrol', $instanceparams)) {
                    // Found a valid  instance, so  enable or disable it.
                    $messagerow['instancename'] = $enrol->get_instance_name($instance);
                    if ($disabledstatus == 1) {
                        $messagerow['status'] = get_string('statusdisabled', 'enrol_manual');
                        $enrol->update_status($instance, ENROL_INSTANCE_DISABLED);
                    } else {
                        $messagerow['status'] = get_string('statusenabled', 'enrol_manual');
                        $enrol->update_status($instance, ENROL_INSTANCE_ENABLED);
                    }
                    $updated++;
                    $messagerow['result'] = get_string('eventenrolinstanceupdated', 'enrol');
                    $tracker->output($messagerow, true);
                } else {
                    $errors++;
                    $messagerow['result'] = get_string('reldoesntexist', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                }
            } else if ($op == 'add' && $method == 'attributes') {
                // Special case for Profile fields method.
                $instancenewparams = array(
                    'customint1' => $parent->id,
                    'roleid' => $roleid,
                    'name' => $sourcelabel,
                    'customtext1' => $attributes
                );

                // Ignore the group field in the CSV file, as it is handled inside the 'attributes' string.

                if ($instance = $DB->get_record('enrol', $instanceparams)) {
                    // This is a duplicate, skip it.
                    $errors++;
                    $messagerow['result'] = get_string('relalreadyexists', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                } else if ($instanceid = $enrol->add_instance($target, $instancenewparams)) {
                    // Successfully added a valid new instance, so now instantiate it.
                    // First synchronise the enrolment.
                    // Insert the JSON string describing the enrolment conditions.
                    $instancenewparams['customtext1'] = $sourcelabel;

                    // Is it initially disabled?
                    if ($disabledstatus == 1) {
                        $instance = $DB->get_record('enrol', array('id' => $instanceid));
                        $enrol->update_status($instance, ENROL_INSTANCE_DISABLED);
                        $messagerow['status'] = get_string('statusdisabled', 'enrol_manual');
                    }

                    $created++;
                    $messagerow['result'] = get_string('eventenrolinstancecreated', 'enrol');
                    $tracker->output($messagerow, true);
                } else {
                    // Instance not added for some reason, so report an error and go to the next line.
                    $errors++;
                    $messagerow['result'] = get_string('reladderror', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                }
            } else if ($op == 'add') {
                // Adding, so check that the parent is not already linked to the target, and add them.
                // Array of parameters to check if meta instance is circular.
                $instancemetacheck = array(
                    'courseid' => $parent->id,
                    'customint1' => $target->id,
                    'enrol' => $method,
                    'roleid' => $roleid
                );

                $instancenewparams = array(
                    'customint1' => $parent->id,
                    'roleid' => $roleid
                );

                // If method members should be added to a group, create it or get its ID.
                if ($groupname != '') {
                    $instancenewparams['customint2'] = uploadenrolmentmethods_get_group($target->id, $groupname);
                }

                if ($method == 'meta' && ($instance = $DB->get_record('enrol', $instancemetacheck))) {
                    $errors++;
                    $messagerow['result'] = get_string('targetisparent', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                } else if ($instance = $DB->get_record('enrol', $instanceparams)) {
                    // This is a duplicate, skip it.
                    $errors++;
                    $messagerow['result'] = get_string('relalreadyexists', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                } else if ($instanceid = $enrol->add_instance($target, $instancenewparams)) {
                    // Successfully added a valid new instance, so now instantiate it.
                    // First synchronise the enrolment.
                    if ($method == 'meta') {
                        enrol_meta_sync($instancenewparams['customint1']);
                    } else if ($method == 'cohort') {
                        $cohorttrace = new null_progress_trace();
                        enrol_cohort_sync($cohorttrace, $target->id);
                        $cohorttrace->finished();
                    }

                    // Is it initially disabled?
                    if ($disabledstatus == 1) {
                        $instance = $DB->get_record('enrol', array('id' => $instanceid));
                        $enrol->update_status($instance, ENROL_INSTANCE_DISABLED);
                        $messagerow['status'] = get_string('statusdisabled', 'enrol_manual');
                    }

                    $created++;
                    $messagerow['result'] = get_string('eventenrolinstancecreated', 'enrol');
                    $tracker->output($messagerow, true);
                } else {
                    // Instance not added for some reason, so report an error and go to the next line.
                    $errors++;
                    $messagerow['result'] = get_string('reladderror', 'tool_uploadenrolmentmethods');
                    $tracker->output($messagerow, false);
                }
            }
        } // End of while loop.

        $message = array(
            get_string('methodstotal', 'tool_uploadenrolmentmethods', $total),
            get_string('methodscreated', 'tool_uploadenrolmentmethods', $created),
            get_string('methodsupdated', 'tool_uploadenrolmentmethods', $updated),
            get_string('methodsdeleted', 'tool_uploadenrolmentmethods', $deleted),
            get_string('methodserrors', 'tool_uploadenrolmentmethods', $errors)
        );

        $tracker->finish();
        $tracker->results($message);
    }

    /**
     * Parse a line to return an array(column => value)
     *
     * @param array $line returned by csv_import_reader
     * @return array
     */
    protected function parse_line($line) {
        $data = array();
        foreach ($line as $keynum => $value) {
            if (!isset($this->columns[$keynum])) {
                // This should not happen.
                continue;
            }

            $key = $this->columns[$keynum];
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * Reset the current process.
     *
     * @return void.
     */
    public function reset() {
        $this->processstarted = false;
        $this->linenb = 0;
        $this->cir->init();
        $this->errors = array();
    }

    /**
     * Validation.
     *
     * @return void
     */
    protected function validate() {
        if (empty($this->columns)) {
            throw new moodle_exception('cannotreadtmpfile', 'error');
        } else if (count($this->columns) < 2) {
            throw new moodle_exception('csvfewcolumns', 'error');
        }
    }
    /**
     * Write contents to a file for debugging purposes
     *
     * @param string $content text to save
     * @param string $prefix file prefix
     * @return void
     */
    public function debug_write(string $content, string $prefix) {
        global $CFG;

        if (debugging(null, DEBUG_DEVELOPER)) {
            $tempxmlfilename = tempnam($CFG->tempdir, $prefix);
            file_put_contents($tempxmlfilename, $content);
        }
    }

    /**
     * Delete temporary files if debugging disabled
     *
     * @param string $filename name of file to be deleted
     * @return void
     */
    public function debug_unlink($filename) {
        if (!debugging(null, DEBUG_DEVELOPER)) { // Not Parenthesis debugging(null, DEBUG_DEVELOPER) parenthesis.
            unlink($filename);
        }
    }
}


/**
 * An exception for reporting errors when processing files
 *
 * Extends the moodle_exception with an http property, to store an HTTP error
 * code for responding to AJAX requests.
 *
 * @copyright   2010 Tauntons College, UK
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploadenrolmentmethods_processor_exception extends moodle_exception {

    /**
     * Stores an HTTP error code
     *
     * @var int
     */
    public $http;

    /**
     * Constructor, creates the exeption from a string identifier, string
     * parameter and HTTP error code.
     *
     * @param string $errorcode
     * @param string $a
     * @param int $http
     */
    public function __construct($errorcode, $a = null, $http = 200) {
        parent::__construct($errorcode, 'tool_uploadenrolmentmethods', '', $a);
        $this->http = $http;
    }
}
