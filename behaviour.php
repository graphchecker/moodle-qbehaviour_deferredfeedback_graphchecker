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
 * Modified version of Moodle's built-in deferredfeedback question type.
 *
 * This takes the same approach as adaptive_adapted_for_coderunner by Richard
 * Lobb. grade_response() in the question returns extra data which is then
 * attached to the attempt with set_qt_var().
 *
 * For more background information, see the forum post at
 * https://moodle.org/mod/forum/discuss.php?d=241455
 *
 * @package    qbehaviour
 * @subpackage deferredfeedback_graphchecker
 * @copyright  2009 The Open University
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * The deferredfeedback question behaviour, adapted for GraphChecker.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_deferredfeedback_graphchecker extends question_behaviour_with_save {

    public function is_compatible_question(question_definition $question) {
        // only allow GraphChecker questions; it does not make sense to use
        // this with other question types as they won't know what to do with
        // the extra qt_vars anyway
        return $question instanceof qtype_graphchecker_question;
    }

    public function get_min_fraction() {
        return $this->question->get_min_fraction();
    }

    public function get_right_answer_summary() {
        return $this->question->get_right_answer_summary();
    }

    public function process_action(question_attempt_pending_step $pendingstep) {
        if ($pendingstep->has_behaviour_var('comment')) {
            return $this->process_comment($pendingstep);
        } else if ($pendingstep->has_behaviour_var('finish')) {
            return $this->process_finish($pendingstep);
        } else {
            return $this->process_save($pendingstep);
        }
    }

    /*
     * Like the parent method, except that when a respones is gradable, but not
     * completely, we move it to the invalid state.
     *
     * TODO refactor, to remove the duplication.
     */
    public function process_save(question_attempt_pending_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        } else if (!$this->qa->get_state()->is_active()) {
            throw new coding_exception('Question is not active, cannot process_actions.');
        }

        if ($this->is_same_response($pendingstep)) {
            return question_attempt::DISCARD;
        }

        if ($this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::$complete);
        } else if ($this->question->is_gradable_response($pendingstep->get_qt_data())) {
            $pendingstep->set_state(question_state::$invalid);
        } else {
            $pendingstep->set_state(question_state::$todo);
        }
        return question_attempt::KEEP;
    }

    public function summarise_action(question_attempt_step $step) {
        if ($step->has_behaviour_var('comment')) {
            return $this->summarise_manual_comment($step);
        } else if ($step->has_behaviour_var('finish')) {
            return $this->summarise_finish($step);
        } else {
            return $this->summarise_save($step);
        }
    }

    public function process_finish(question_attempt_pending_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        $response = $this->qa->get_last_step()->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $pendingstep->set_state(question_state::$gaveup);
        } else {
            // the following part is edited from the default deferredfeedback
            // question type, to accept the _testoutcome returned by
            // grade_response() and put it into a qt_var
            $gradedata = $this->question->grade_response($response);
            list($fraction, $state) = $gradedata;
            if (count($gradedata) > 2) {
                foreach ($gradedata[2] as $name => $value) {
                    $pendingstep->set_qt_var($name, $value);
                }
            }
            // (edited until here)

            $pendingstep->set_fraction($fraction);
            $pendingstep->set_state($state);
        }
        $pendingstep->set_new_response_summary($this->question->summarise_response($response));
        return question_attempt::KEEP;
    }
}
