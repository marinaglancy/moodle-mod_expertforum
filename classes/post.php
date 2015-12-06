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
    protected $cachedtags = null;
    protected $cachedparent = null;

    public function __construct($record, cm_info $cm, $fetchedtags = null) {
        $this->record = $record;
        $this->cm = $cm;
        $this->cachedtags = $fetchedtags;
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

    public static function editoroptions(cm_info $cm) {
        return array('trusttext' => 1, 'context' => $cm->context); // TODO maxbytes,maxfiles.
    }

    public static function can_create(cm_info $cm) {
        global $USER;
        // TODO
        return isloggedin() && !isguestuser();
    }

    public static function create($data, cm_info $cm) {
        global $USER, $DB, $CFG;

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
            $data = file_postupdate_standard_editor($data, 'message', self::editoroptions($cm),
                $cm->context, 'mod_expertforum', 'post', $data->id);
            unset($data->message_editor);
            $DB->update_record('expertforum_post', $data);
        }

        if (!empty($data->tags)) {
            require_once($CFG->dirroot.'/tag/lib.php');
            tag_set('expertforum_post', $data->id, $data->tags, 'mod_expertforum', $cm->context->id);
        }

        return new mod_expertforum_post($data, $cm);
    }

    public function can_update() {
        global $USER;
        // TODO
        return isloggedin() && !isguestuser() && ($USER->id == $this->record->userid);
    }

    public function update($formdata) {
        global $DB, $CFG;

        $allowedkeys = array('message' => 1, 'messageformat' => 1, 'message_editor' => 1);
        if (!$this->record->parent) {
            $allowedkeys['subject'] = 1;
        }
        $data = (object)array_intersect_key((array)$formdata, $allowedkeys);
        $data->id = $this->record->id;
        $data->timemodified = time();

        if (isset($data->message_editor)) {
            $data = file_postupdate_standard_editor($data, 'message', self::editoroptions($this->cm),
                $this->cm->context, 'mod_expertforum', 'post', $data->id);
            unset($data->message_editor);
        }
        $DB->update_record('expertforum_post', $data);

        if (!$this->record->parent && isset($formdata->tags)) {
            require_once($CFG->dirroot.'/tag/lib.php');
            tag_set('expertforum_post', $data->id, $formdata->tags, 'mod_expertforum', $this->cm->context->id);
        }
    }

    public function get_url($params = null) {
        $id = empty($this->record->parent) ? $this->record->id : $this->record->parent;
        $urlparams = array('e' => $this->cm->instance, 'id' => $id) + ($params ? $params : array());
        $url = new moodle_url('/mod/expertforum/viewpost.php', $urlparams);
        return $url;
    }

    public function get_parent() {
        global $DB;
        if (empty($this->record->parent)) {
            return null;
        }
        if ($this->cachedparent === null) {
            $this->cachedparent = new self(
                    $DB->get_record('expertforum_post', array('id' => $this->record->parent)),
                    $this->cm);
        }
        return $this->cachedparent;
    }

    public function get_formatted_subject() {
        global $CFG;
        require_once($CFG->libdir.'/externallib.php');
        if ($parent = $this->get_parent()) {
            return $parent->get_formatted_subject();
        }
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

    public function can_answer() {
        global $USER;
        // TODO
        return isloggedin() && !isguestuser();
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
            $answer = new self($record, $this->cm);
            $answer->cachedparent = $this;
            $answers[] = $answer;
        }
        return $answers;
    }

    public function can_upvote() {
        global $USER;
        // TODO
        return isloggedin() && !isguestuser() && $USER->id != $this->record->userid;
    }

    public function can_downvote() {
        global $USER;
        // TODO
        return isloggedin() && !isguestuser() && $USER->id != $this->record->userid;
    }

    public function vote($answerid, $vote) {
        global $DB, $USER;
        if (!isloggedin() || isguestuser()) {
            return;
        }
        $params = array('userid' => $USER->id, 'answerid' => $answerid, 'parent' => $this->record->id);
        if ($answerid != $this->record->id) {
            $parentsql = '= :parent';
        } else {
            $parentsql = 'IS NULL';
        }
        $answer = $DB->get_record_sql('SELECT p.id, p.parent, p.userid AS author, v.id AS voteid, v.vote
                FROM {expertforum_post} p
                LEFT JOIN {expertforum_vote} v ON v.postid = p.id AND v.userid = :userid
                WHERE p.id = :answerid AND p.parent '.$parentsql,
                $params);
        if (!$answer) {
            return;
        }
        if ($answer->author == $USER->id) {
            // Can't vote on own posts.
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
        /*$DB->execute("UPDATE {expertforum_post} p SET votes =
                (SELECT SUM(v.vote) FROM {expertforum_vote} v WHERE v.parent = p.id)
                WHERE p.id = :parent", $params);*/
    }

    public function get_timestamp() {
        $now = time();
        $timestamp = format_time($now - $this->record->timecreated);
        $timestamp = html_writer::span($timestamp, 'relativetime',
                array('title' => userdate($this->record->timecreated, get_string('strftimedatetime', 'core_langconfig'))));
        if (empty($this->record->parent)) {
            return get_string('askedago', 'mod_expertforum', $timestamp);
        } else {
            return get_string('answeredago', 'mod_expertforum', $timestamp);
        }
    }

    public function get_tags() {
        global $CFG;
        require_once($CFG->dirroot.'/tag/lib.php');
        $rv = array();
        if (empty($this->record->parent)) {
            if ($this->cachedtags === null) {
                $this->cachedtags = tag_get_tags('expertforum_post', $this->id);
            }
            $url = new moodle_url('/mod/expertforum/view.php', array('id' => $this->cm->id));
            foreach ($this->cachedtags as $tag) {
                $url->param('tag', $tag->name);
                $rv[] = array('tagname' => $tag->rawname,
                    'tagurl' => $url->out(false));
            }
        }
        return $rv;
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
        $record->userpicture = '';
        $record->username = '';
        $record->userreputation = '';
        $record->isfavourite = 1; // TODO
        $record->favouritecount = 5; // TODO
        $record->tags = $this->get_tags();

        $user = \user_picture::unalias($this->record, array('deleted'), 'useridx', 'user');
        if (isset($user->id)) {
            $record->userpicture = $output->user_picture($user, array('size' => 35));
            $record->username = fullname($user);
            if (user_can_view_profile($user)) {
                $profilelink = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $this->cm->course));
                $record->username = html_writer::link($profilelink, $record->username);
            }
            $record->userreputation = 15; // TODO
        }

        $record->timestamp = $this->get_timestamp();
        if (empty($this->record->parent)) {
            $record->answers = array();
            $answers = $this->get_answers();
            foreach ($answers as $answer) {
                $record->answers[] = $answer->export_for_template($output);
            }
            if ($answers) {
                $record->answersheader = get_string('answerscount', 'mod_expertforum', count($record->answers));
            }
        } else {
            $record->parent = $this->record->parent;
        }

        $record->upvoteurl = '';
        $record->downvoteurl = '';
        if ($this->can_upvote()) {
            $record->upvoteurl = $this->get_url()->out(false,
                    array('upvote' => $this->record->id, 'sesskey' => sesskey()));
        }
        if ($this->can_downvote()) {
            $record->downvoteurl = $this->get_url()->out(false,
                    array('downvote' => $this->record->id, 'sesskey' => sesskey()));
        }

        $record->editurl = '';
        if ($this->can_update()) {
            $url = new moodle_url('/mod/expertforum/post.php', array('e' => $this->cm->instance, 'edit' => $this->record->id));
            $record->editurl = $url->out(false);
        }

        return $record;
    }
}