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
 * Lib for vplquestion question type.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('QVPL', 'qtype_vplquestion');

/**
 * Format and filter execution files provided by the user.
 * This method adds a suffix (_qvpl) to file names, and filters out files specified as UNUSED.
 * @param array $execfiles The files to format and filter.
 * @param array $selector If specified, only the files with name contained in this array will be considered.
 * @return array The resulting files array.
 */
function qtype_vplquestion_format_execution_files($execfiles, $selector=null) {
    $formattedfiles = [];
    foreach ($execfiles as $name => $content) {
        if ($selector === null || in_array($name, $selector)) {
            if (substr($content, 0, 6) != 'UNUSED') {
                $formattedfiles[$name.'_qvpl'] = $content;
            }
        }
    }
    return $formattedfiles;
}

/**
 * Insert answer into required file and format it for submission.
 * @param object $question The question data.
 * @param string $answer The answer to the question, to include in submission.
 * @return array Files ready for submission.
 */
function qtype_vplquestion_get_reqfile_for_submission($question, $answer) {
    global $CFG;
    require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
    $vpl = new mod_vpl($question->templatevpl);

    $reqfiles = $vpl->get_required_fgm()->getAllFiles();
    $reqfilename = array_keys($reqfiles)[0];

    // Escape all backslashes, as following operation deletes them.
    $answer = preg_replace('/\\\\/', '$0$0', $answer);
    // Replace the {{ANSWER}} tag, propagating indentation.
    $answeredreqfile = preg_replace('/([ \t]*)(.*)\{\{ANSWER\}\}/i',
            '$1${2}'.implode("\n".'${1}', explode("\n", $answer)),
            $question->templatecontext);

    return [ $reqfilename => $answeredreqfile ];
}

/**
 * Evaluate an answer to a question by submitting it to the VPL and requesting an evaluate.
 * @param string $answer The answer to evaluate.
 * @param object $question The question data.
 * @param bool $deletesubmissions Whether user submissions should be discarded at the end of the operation.
 * @return object The evaluation result.
 */
function qtype_vplquestion_evaluate($answer, $question, $deletesubmissions) {
    global $USER, $CFG;
    require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
    require_once($CFG->dirroot .'/mod/vpl/vpl_submission.class.php');
    require_once($CFG->dirroot .'/mod/vpl/forms/edit.class.php');

    $userid = $USER->id;
    $vpl = new mod_vpl($question->templatevpl);

    // Forbid simultaneous evaluations (as the VPL won't allow multiple executions at once).
    $lock = \core\lock\lock_config::get_lock_factory('qtype_vplquestion_evaluate')->get_lock($userid, 5);
    if ($lock === false) {
        throw new moodle_exception('locktimeout');
    }

    try {
        $reqfile = qtype_vplquestion_get_reqfile_for_submission($question, $answer);
        $execfiles = qtype_vplquestion_format_execution_files(json_decode($question->execfiles));
        $files = $reqfile + $execfiles;
        $subid = mod_vpl_edit::save($vpl, $userid, $files)->version ?? $vpl->last_user_submission($userid)->id; // For VPL pre 3.4.

        $coninfo = mod_vpl_edit::execute($vpl, $userid, 'evaluate');

        $wsprotocol = $coninfo->wsProtocol;
        if ( $wsprotocol == 'always_use_wss' ||
                ($wsprotocol == 'depends_on_https' && stripos($_SERVER['SERVER_PROTOCOL'], 'https') !== false) ) {
            $port = $coninfo->securePort;
            $protocol = 'ssl';
        } else {
            $port = $coninfo->port;
            $protocol = 'tcp';
        }

        $ws = new qtype_vplquestion\websocket($coninfo->server, $port, $protocol);

        $ws->open("/$coninfo->monitorPath");

        $closeflag = false;
        $retrieveflag = false;
        $servermessages = [];

        while (($message = $ws->read_next_message()) !== false) {
            $servermessages[] = $message;
            $parts = preg_split('/:/', $message);
            if ($parts[0] == 'close') {
                $closeflag = true;
            }
            if ($parts[0] == 'retrieve') {
                $retrieveflag = true;
                break;
            }
        }

        // DO NOT close the connection.
        // If we send a close signal through the monitor websocket, the jail server will clean the task
        // and result retrieval will fail.
        // This is an issue with jail server, and is discussed here: https://github.com/jcrodriguez-dis/vpl-jail-system/issues/75.

        $result = new stdClass();
        if ($retrieveflag) {
            // Only retrieve result if the 'retrieve:' flag was recieved.
            $result->vplresult = mod_vpl_edit::retrieve_result($vpl, $userid, $coninfo->processid ?? -1);
        } else {
            // We got no 'retrieve:' flag - it may be because execution ressources limits have been exceeded.
            $ws->close();
            $reason = 'unknown';
            foreach ($servermessages as $servermessage) {
                $matches = [];
                if (preg_match('/message:(timeout|outofmemory)/', $servermessage, $matches)) {
                    $reason = $matches[1];
                }
            }
            $message = get_string($closeflag ? 'closerecievednoretrieve' : 'unexpectedendofws', QVPL, $reason);
            if ($reason == 'unknown') {
                $message .= "\nServer messages:\n" . implode("\n", $servermessages) . "\n" . get_string('flagifproblem', QVPL);
            }

            // Format a result that will be interpreted as a wrong answer.
            $submission = new mod_vpl_submission($vpl, $subid);
            $result->vplresult = $submission->get_ce_for_editor([
                    'compilation' => '',
                    'executed' => 1,
                    'execution' => $message . "\n" . mod_vpl_submission::GRADETAG . " 0\n",
            ]);
        }

        // Now we can close the websocket.
        $ws->close();

        $result->servermessages = $servermessages;
        $result->errormessage = '';

    } catch (Exception | Error $e) {
        // There was an unexpected error during evaluation.
        $result = new stdClass();
        $result->vplresult = new stdClass();
        $result->servermessages = $servermessages ?? [];
        $result->errormessage = $e instanceof TypeError ? get_string('gradetypeerror', QVPL, $e->getMessage()) : $e->getMessage();
    } finally {
        // Always release locks.
        if ($lock) {
            $lock->release();
        }
    }

    if ($deletesubmissions) {
        try {
            require_once($CFG->dirroot.'/mod/vpl/vpl_submission.class.php');
            foreach ($vpl->user_submissions($userid) as $subrecord) {
                $submission = new mod_vpl_submission($vpl, $subrecord);
                $submission->delete();
            }
        } catch (Exception $e) {
            // Something went wrong while deleting submissions - do nothing more.
            return $result;
        }
    }

    return $result;
}

/**
 * Compute the fraction (grade between 0 and 1) from the result of an evaluation.
 * @param object $result The evaluation result.
 * @param int $templatevpl The ID of the VPL this evaluation has been executed on.
 * @return float|null The fraction if any, or null if there was no grade.
 */
function qtype_vplquestion_extract_fraction($result, $templatevpl) {
    if (!empty($result->grade)) {
        global $CFG;
        require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
        $maxgrade = (new mod_vpl($templatevpl))->get_grade();
        $fraction = floatval(preg_replace('/.*: (.*) \/.*/', '$1', $result->grade)) / $maxgrade;
        return $fraction;
    } else {
        return null;
    }
}

/**
 * Create a human-readable error message of why an evaluation went wrong.
 * This is to be called when the grade is null.
 * @param stdClass $evaluateresult Result as returned by qtype_vplquestion_evaluate().
 * @param string $usertype Either 'student' or 'teacher'.
 * @return string The formatted error message.
 */
function qtype_vplquestion_make_evaluation_error_message($evaluateresult, $usertype = 'student') {
    $details = [];
    if ($evaluateresult->errormessage) {
        $details[] = $evaluateresult->errormessage;
    } else {
        $details[] = get_string('nogradenoerror', QVPL, $evaluateresult->vplresult->grade);
    }
    if (empty($evaluateresult->servermessages)) {
        $details[] = get_string('serverwassilent', QVPL);
    } else {
        $lastmessage = '';
        foreach ($evaluateresult->servermessages as $servermessage) {
            $lastmessage = $servermessage ?: $lastmessage; // Get last non-empty message.
        }
        $details[] = get_string('lastservermessage', QVPL, $lastmessage);
    }
    $servererrormessage = get_string('serverexecutionerror', 'mod_vpl');
    if (strpos(ltrim($evaluateresult->errormessage), $servererrormessage) !== false) {
        $details[] = get_string('serverexecutionerror' . $usertype . 'message', QVPL);
    }
    return get_string('nogradeerror', QVPL, implode("\n", $details));
}

function qtype_vplquestion_get_mod_vpl_version() {
    global $CFG;
    require_once($CFG->libdir . '/classes/plugin_manager.php');
    return core_plugin_manager::instance()->get_plugin_info('mod_vpl')->versiondisk;
}

function qtype_vplquestion_get_ace_themes() {
    global $CFG;
    $themes = [];
    // Search for theme files.
    $acefiles = array_diff(scandir($CFG->dirroot . '/mod/vpl/editor/ace9/'), [ '.', '..' ]);
    $themefiles = array_filter($acefiles, function($name) {
        return substr($name, 0, 6) == 'theme-';
    });
    // Process theme files names to get displayable name,
    // by replacing underscores by spaces and
    // by putting upper case letters at the beginning of words.
    foreach ($themefiles as $themefile) {
        $theme = substr($themefile, 6, strlen($themefile) - 9);
        $themename = preg_replace_callback('/(^|_)([a-z])/', function($matches) {
            return ' ' . strtoupper($matches[2]);
        }, $theme);
        $themes[$theme] = trim($themename);
    }
    // Some exceptions.
    $themes['github'] = 'GitHub';
    $themes['idle_fingers'] = 'idle Fingers';
    $themes['iplastic'] = 'IPlastic';
    $themes['katzenmilch'] = 'KatzenMilch';
    $themes['kr_theme'] = 'krTheme';
    $themes['kr'] = 'kr';
    $themes['pastel_on_dark'] = 'Pastel on dark';
    $themes['sqlserver'] = 'SQL Server';
    $themes['textmate'] = 'TextMate';
    $themes['tomorrow_night_eighties'] = 'Tomorrow Night 80s';
    $themes['xcode'] = 'XCode';
    return $themes;
}
