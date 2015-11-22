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
 * Defines the class
 *
 * @package    mod_expertforum
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The mod_expertforum instance list viewed event class
 *
 * If the view mode needs to be stored as well, you may need to
 * override methods get_url() and get_legacy_log_data(), too.
 *
 * @package    mod_expertforum
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_expertforum_post_form extends moodleform {

    public function definition() {

        $expertforum = $this->_customdata['expertforum'];
        $parent = null;
        if (!empty($this->_customdata['parent'])) {
            $parent = $this->_customdata['parent'];
        }

        $mform = $this->_form;

        $mform->addElement('hidden', 'e');
        $mform->setType('e', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        if (!$parent) {
            $mform->addElement('text', 'subject', 'SUBJECT'); // TODO string;
            $mform->setType('subject', PARAM_NOTAGS);
            // TODO limit length
        }

        $mform->addElement('editor', 'message_editor', 'MESSAGE', mod_expertforum_post::editoroptions()); // TODO string;

        $data = array('e' => $expertforum->id);
        if ($parent) {
            $data['parent'] = $parent->id;
        }
        $this->set_data($data);

        $this->add_action_buttons();
    }
}
