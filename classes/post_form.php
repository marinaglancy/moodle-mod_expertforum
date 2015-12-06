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
        global $CFG, $PAGE;
        require_once($CFG->dirroot.'/tag/lib.php');

        $expertforum = $this->_customdata['expertforum'];
        $cm = $this->_customdata['cm'];
        $parent = null;
        $post = null;
        if (!empty($this->_customdata['post'])) {
            $post = $this->_customdata['post'];
            if ($post->parent) {
                $parent = (object)array('id' => $post->parent);
            }
        } else if (!empty($this->_customdata['parent'])) {
            $parent = $this->_customdata['parent'];
        }

        $mform = $this->_form;

        $mform->addElement('hidden', 'e');
        $mform->setType('e', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        if (!$parent) {
            $mform->addElement('text', 'subject', get_string('postsubject', 'mod_expertforum'));
            $mform->setType('subject', PARAM_NOTAGS);
            // TODO limit length
        }

        $mform->addElement('editor', 'message_editor',
                $parent ? get_string('youranswer', 'mod_expertforum') :
                get_string('yourquestion', 'mod_expertforum'),
                mod_expertforum_post::editoroptions($cm));

        if (!$parent) {
            $mform->addElement('tags', 'tags', get_string('tags'));
        }

        $data = (object)array('e' => $expertforum->id);
        if ($parent) {
            $data->parent = $parent->id;
        }
        if ($post) {
            $data->subject = $post->subject;
            $data->edit = $post->id;
            $data->tags = tag_get_tags_array('expertforum_post', $post->id);
            $data->message = $post->message;
            $data->messageformat = $post->messageformat;
            $data = file_prepare_standard_editor($data, 'message', mod_expertforum_post::editoroptions($cm),
                $cm->context, 'mod_expertforum', 'post', $post->id);
        }
        $this->set_data($data);

        $this->add_action_buttons();
    }
}
