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
 * Contains class mod_expertforum_post
 *
 * @package   mod_expertforum
 * @copyright 2015 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage one post
 *
 * @package   mod_expertforum
 * @copyright 2015 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_expertforum_post implements templatable {

    protected $record;
    protected $cm;

    public function __construct($record, cm_info $cm) {
        $this->record = $record;
        $this->cm = $cm;
    }

    public function __get($name) {
        if ($name === 'cm') {
            return $this->cm;
        }
        return $this->record->$name;
    }

    public function __isset($name) {
        return ($name === 'cm') || isset($this->record->$name);
    }

    public static function editoroptions() {
        return array('trusttext' => 1); // TODO maxbytes,maxfiles.
    }

    public static function create($data, cm_info $cm) {
        global $USER, $DB;
        $time = time();
        $data = (object)((array)$data + array(
            'groupid' => 0,
            'votes' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
            'userid' => $USER->id,
            'message' => '',
            'messageformat' => FORMAT_PLAIN,
        ));
        unset($data->id);
        $data->courseid = $cm->course;
        $data->expertforumid = $cm->instance;
        if (empty($data->parent)) {
            $data->parent = null;
            $parent = null;
            if (empty($data->subject)) {
                throw new coding_exception('Caller must verify that subject is set');
            }
        } else {
            $parent = $DB->get_record('expertforum_post', array('id' => $data->parent,
                'courseid' => $data->courseid, 'expertforumid' => $data->expertforumid),
                    'id, subject, groupid', MUST_EXIST);
            $data->groupid = $parent->groupid;
            $data->subject = null;
        }
        $data->messagetrust = trusttext_trusted($cm->context);

        $data->id = $DB->insert_record('expertforum_post', $data);

        if (isset($data->message_editor)) {
            $data = file_postupdate_standard_editor($data, 'message', self::editoroptions(),
                $cm->context, 'mod_expertforum', 'post', $data->id);
            unset($data->message_editor);
            $DB->update_record('expertforum_post', $data);
        }

        return new mod_expertforum_post($data, $cm);
    }

    public function get_url() {
        $id = empty($this->record->parent) ? $this->record->id : $this->record->parent;
        $url = new moodle_url('/mod/expertforum/viewpost.php',
                array('e' => $this->cm->instance, 'id' => $id));
        return $url;
    }

    public function get_formatted_subject() {
        global $CFG;
        require_once($CFG->libdir.'/externallib.php');
        return external_format_string($this->record->subject, $this->cm->context->id);
    }

    public function get_formatted_message() {
        global $CFG;
        require_once($CFG->libdir.'/externallib.php');
        // TODO not possible to set trusttext!
        //return $this->record->message;
        list($text, $textformat) = external_format_text($this->record->message,
                $this->record->messageformat,
                $this->cm->context->id,
                'mod_expertforum',
                'post',
                $this->record->id);
        return $text;
    }

    public function get_excerpt() {
        $message = $this->get_formatted_message();
        return html_to_text(shorten_text($message, 250), 300); // TODO exceprt legth as parameter.
    }

    public static function get($id, cm_info $cm, $strictness = IGNORE_MISSING) {
        global $DB;
        $userfields = \user_picture::fields('u', array('deleted'), 'useridx', 'user');
        $record = $DB->get_record_sql('SELECT p.*, ' . $userfields . '
                FROM {expertforum_post} p
                LEFT JOIN {user} u ON u.id = p.userid AND u.deleted = 0
                WHERE p.id = ? AND p.expertforumid = ? AND p.parent IS NULL',
                array($id, $cm->instance),
                $strictness);
        if ($record) {
            return new self($record, $cm);
        }
        return null;
    }

    public function get_answers() {
        global $DB;
        $userfields = \user_picture::fields('u', array('deleted'), 'useridx', 'user');
        $records = $DB->get_records_sql('SELECT a.*, ' . $userfields . '
                FROM {expertforum_post} a
                LEFT JOIN {user} u ON u.id = a.userid AND u.deleted = 0
                WHERE a.parent = ? AND a.expertforumid = ?
                ORDER BY a.votes DESC, a.timecreated, a.id',
                array($this->record->id, $this->cm->instance));
        $answers = array();
        foreach ($records as $record) {
            $answers[] = new self($record, $this->cm);
        }
        return $answers;
    }

    public function vote($answerid, $vote) {
        global $DB, $USER;
        if (!isloggedin() || isguestuser()) {
            return;
        }
        $params = array('userid' => $USER->id, 'answerid' => $answerid, 'parent' => $this->record->id);
        $answer = $DB->get_record_sql('SELECT p.id, p.parent, v.id AS voteid, v.vote
                FROM {expertforum_post} p
                LEFT JOIN {expertforum_vote} v ON v.postid = p.id AND v.userid = :userid
                WHERE p.id = :answerid AND p.parent = :parent',
                $params);
        if (!$answer) {
            return;
        }
        if ($answer->voteid) {
            if ($answer->vote != $vote) {
                $DB->update_record('expertforum_vote', array(
                    'vote' => $vote,
                    'id' => $answer->voteid,
                    'timemodified' => time()));
            } else {
                return;
            }
        } else {
            $DB->insert_record('expertforum_vote', array(
                'userid' => $USER->id,
                'courseid' => $this->cm->course,
                'expertforumid' => $this->cm->instance,
                'parent' => $this->record->id,
                'postid' => $answerid,
                'timemodified' => time(),
                'vote' => $vote
            ));
        }
        $DB->execute("UPDATE {expertforum_post} p SET votes =
                (SELECT SUM(v.vote) FROM {expertforum_vote} v WHERE v.postid = p.id)
                WHERE p.id = :answerid", $params);
        $DB->execute("UPDATE {expertforum_post} p SET votes =
                (SELECT SUM(v.vote) FROM {expertforum_vote} v WHERE v.parent = p.id)
                WHERE p.id = :parent", $params);
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        $posts = array();
        $record = new stdClass();
        $record->id = $this->record->id;
        $record->subject = $this->get_formatted_subject();
        $record->message = $this->get_formatted_message();
        $record->votes = empty($this->votes) ? 0 : $this->votes;
        $record->timecreated = userdate($this->record->timecreated, get_string('strftimedatetime', 'core_langconfig'));
        $record->userpicture = null;
        $record->username = null;

        $user = \user_picture::unalias($this->record, array('deleted'), 'useridx', 'user');
        if (isset($user->id)) {
            $record->userpicture = $output->user_picture($user, array('size' => 35));
            $record->username = fullname($user);
            if (user_can_view_profile($user)) {
                $profilelink = new moodle_url('/user/view.php', array('id' => $user->id));
                $record->username = html_writer::link($profilelink, $record->username);
            }
        }

        if (empty($this->record->parent)) {
            $record->answers = array();
            $answers = $this->get_answers();
            foreach ($answers as $answer) {
                $record->answers[] = $answer->export_for_template($output);
            }
        } else {
            $record->upvoteurl = $this->get_url()->out(false,
                    array('upvote' => $this->record->id, 'sesskey' => sesskey()));
            $record->downvoteurl = $this->get_url()->out(false,
                    array('downvote' => $this->record->id, 'sesskey' => sesskey()));
        }
        return $record;
    }
}