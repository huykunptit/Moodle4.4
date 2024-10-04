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
 * Defines the editing form for the vplquestion question type.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Vplquestion editing form definition.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion_edit_form extends question_edit_form {

    /**
     * Question type name.
     * @see question_edit_form::qtype()
     */
    public function qtype() {
        return 'vplquestion';
    }

    /**
     * Add our fields to the form.
     * @param MoodleQuickForm $mform The form being built.
     * @see question_edit_form::definition_inner()
     */
    protected function definition_inner($mform) {
        // Create form fields.
        $this->add_vpl_template_field($mform);
        $this->add_answer_template_field($mform);
        $this->add_teacher_correction_field($mform);
        $this->add_execfiles_field($mform);

        // Setup Ace editors and form behavior.
        global $PAGE, $OUTPUT;
        $templatechangehelp = $OUTPUT->help_icon('templatevplchange', QVPL, get_string('help'));

        $vplversion = qtype_vplquestion_get_mod_vpl_version();

        $PAGE->requires->strings_for_js([ 'merge', 'overwrite', 'templatevplchange', 'templatevplchangeprompt' ], QVPL);
        $PAGE->requires->string_for_js('cancel', 'moodle');
        $PAGE->requires->string_for_js('binaryfile', 'mod_vpl');
        $PAGE->requires->js_call_amd(QVPL.'/editform', 'setup', [ $templatechangehelp, $vplversion ]);
    }

    /**
     * Add a field for selecting the template VPL and editing the template.
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function add_vpl_template_field($mform) {
        $this->create_header($mform, 'qvplbase');

        global $COURSE;
        $basevpls = get_coursemodules_in_course('vpl', $COURSE->id);
        foreach ($basevpls as &$vpl) {
            $vpl = format_string($vpl->name);
        }
        $mform->addElement('select', 'templatevpl', get_string('templatevpl', QVPL),
            [ '' => get_string('choose', QVPL) ] + $basevpls);
        $mform->addRule('templatevpl', null, 'required', null, 'client');
        $mform->addHelpButton('templatevpl', 'templatevpl', QVPL);

        $mform->addElement('hidden', 'templatelang');
        $mform->setType('templatelang', PARAM_RAW);

        $this->add_codeeditor($mform, 'templatecontext');
    }

    /**
     * Add a field for the answer template.
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function add_answer_template_field($mform) {
        $this->create_header($mform, 'answertemplate');
        $this->add_codeeditor($mform, 'answertemplate');
    }

    /**
     * Add a field for a correction from the teacher (optional).
     * @param MoodleQuickForm $mform the form being built.
     * @copyright Inspired from Coderunner question type.
     */
    protected function add_teacher_correction_field($mform) {
        $this->create_header($mform, 'teachercorrection');
        $this->add_codeeditor($mform, 'teachercorrection');

        $mform->addElement('advcheckbox', 'validateonsave', null, get_string('validateonsave', QVPL));
        $mform->setDefault('validateonsave', true);
        $mform->addHelpButton('validateonsave', 'validateonsave', QVPL);
    }

    /**
     * Add a field for the execution files and grading options.
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function add_execfiles_field($mform) {
        $this->create_header($mform, 'execfilesevalsettings');

        $this->add_fileset_editor($mform, 'execfiles', 'execfileslist', 'execfile');

        $mform->addElement('select', 'precheckpreference', get_string('precheckpreference', QVPL),
            [
                'none' => get_string('noprecheck', QVPL),
                'dbg' => get_string('precheckisdebug', QVPL),
                'same' => get_string('precheckhassamefiles', QVPL),
                'diff' => get_string('precheckhasownfiles', QVPL),
            ]);
        $mform->setDefault('precheckpreference', 'same');
        $mform->addHelpButton('precheckpreference', 'precheckpreference', QVPL);

        $this->add_fileset_editor($mform, 'precheckexecfiles', 'precheckexecfileslist', 'precheckexecfile');

        $mform->addElement('select', 'gradingmethod',
            get_string('gradingmethod', QVPL),
            [ get_string('allornothing', QVPL), get_string('scaling', QVPL) ]);
        $mform->addHelpButton('gradingmethod', 'gradingmethod', QVPL);
    }

    /**
     * Add an editor managing several files (with tabs).
     * @param MoodleQuickForm $mform the form being built.
     * @param string $name the name of the (hidden) field in which the files will be written.
     * @param string $listname the id of the file tabs element in DOM.
     * @param string $editorname the name of the editor.
     */
    private function add_fileset_editor($mform, $name, $listname, $editorname) {
        $mform->addElement('hidden', $name);
        $mform->setType($name, PARAM_RAW);
        $mform->addElement('static', $listname, get_string($name, QVPL),
            '<em class="novplmessage">'.get_string('selectavpl', QVPL, '#id_qvplbaseheader').'</em>
            <ul id="'.$listname.'" class="filelist inline-list"></ul>');
        $mform->addHelpButton($listname, $name, QVPL);

        $mform->addElement('textarea', $editorname, '', [ 'rows' => 1, 'class' => 'code-editor manylangs' ]);
    }

    /**
     * Add a code editor with an help button.
     * @param MoodleQuickForm $mform the form being built.
     * @param string $field the name of the editor.
     * @param array $attributes (optional) the attributes to add to the editor.
     */
    private function add_codeeditor($mform, $field, $attributes=null) {
        $mform->addElement('textarea', $field, get_string($field, QVPL),
            [ 'rows' => 1, 'class' => 'code-editor' ]);
        if ($attributes != null) {
            $mform->updateElementAttr($field, $attributes);
        }
        $mform->addHelpButton($field, $field, QVPL);
    }

    /**
     * Start a new form section with given name.
     * @param MoodleQuickForm $mform the form being built.
     * @param string $identifier the name of the section.
     */
    private function create_header($mform, $identifier) {
        $mform->addElement('header', $identifier.'header', get_string($identifier, QVPL));
        $mform->setExpanded($identifier.'header', true);
    }

    /**
     * Validate teacher correction against test cases.
     * @param array $submitteddata The data from the form.
     * @param array $files
     * @see question_edit_form::validation()
     */
    public function validation($submitteddata, $files) {
        require_sesskey();
        $errors = parent::validation($submitteddata, $files);

        if ($submitteddata['validateonsave']) {
            $question = new stdClass();
            foreach ($submitteddata as $key => $value) {
                $question->$key = $value;
            }

            try {
                $result = qtype_vplquestion_evaluate($submitteddata['teachercorrection'], $question, false);
                $vplres = $result->vplresult;
                $grade = qtype_vplquestion_extract_fraction($vplres, $question->templatevpl);
                if (!empty($vplres->compilation)) {
                    $errors['teachercorrection'] = '<pre style="color:inherit">'.htmlspecialchars($vplres->compilation).'</pre>';
                } else if ($grade !== null) {
                    if ($grade < 1.0) {
                        $errors['teachercorrection'] = '<pre style="color:inherit">'.htmlspecialchars($vplres->evaluation).'</pre>';
                    }
                } else {
                    // No grade obtained. Something went wrong, display a message as explicit as possible.
                    $errors['teachercorrection'] = nl2br(qtype_vplquestion_make_evaluation_error_message($result, 'teacher'));
                }
            } catch (Exception $e) {
                $message = '[' . get_class($e) . ' - ' . $e->getCode() . ']' . $e->getMessage();
                $errors['teachercorrection'] = nl2br(get_string('unexpectederror', QVPL, $message));
            }
        }

        return $errors;
    }
}
