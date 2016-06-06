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
 * Contains class mod_expertforum\output\listing
 *
 * @package   mod_expertforum
 * @copyright 2015 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_expertforum\output;

defined('MOODLE_INTERNAL') || die();

use templatable, renderer_base, stdClass, user_picture, cm_info;

/**
 * Class mod_expertforum\output\listing
 *
 * @package   mod_expertforum
 * @copyright 2015 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class listing implements templatable {
    protected $records;
    protected $cm;
    protected $fetchedtags;

    /**
     * Constructor
     *
     * @param \cm_info $cm
     * @param \core_tag_tag $tagobject
     * @global \moodle_database $DB
     */
    public function __construct(cm_info $cm, $tagobject = null) {
        global $DB;
        $params = array('expertforumid' => $cm->instance, 'courseid' => $cm->course);
        $subquery = '';
        if ($tagobject) {
            $subquery = "INNER JOIN {tag_instance} ti ON ti.component = :component AND "
                    . "ti.itemtype = :recordtype AND ti.itemid = p.id AND ti.tagid = :tagid";
            $params['tagid'] = $tagobject->id;
            $params['component'] = 'mod_expertforum';
            $params['recordtype'] = 'expertforum_post';
        }
        $postfields = "p.id, p.subject, p.votes, p.timecreated, p.subject, p.message, p.messageformat";
        $userfields = user_picture::fields('u', array('deleted'), 'useridx', 'user');
        $userfieldsnoalias = user_picture::fields('u', array('deleted'));
        $sql = "SELECT $postfields, $userfields, COUNT(a.id) AS answers
            FROM {expertforum_post} p
            $subquery
            LEFT OUTER JOIN {user} u ON u.deleted = 0 AND u.id = p.userid
            LEFT OUTER JOIN {expertforum_post} a ON a.parent = p.id
            WHERE p.parent is null
                AND p.expertforumid = :expertforumid
                AND p.courseid = :courseid
            GROUP BY $postfields, $userfieldsnoalias
            ORDER BY p.timecreated DESC
                ";
        // TODO limit, offset
        $this->records = $DB->get_records_sql($sql, $params);
        $this->cm = $cm;
        $this->fetchedtags = array();

        if (!$this->records) {
            return;
        }

        list($itemsql, $itemparams) = $DB->get_in_or_equal(array_keys($this->records), SQL_PARAMS_NAMED);
        $sql = "SELECT ti.itemid, tg.id, tg.name, tg.rawname
                  FROM {tag_instance} ti
                  JOIN {tag} tg ON tg.id = ti.tagid
                  WHERE ti.component = :component AND ti.itemtype = :recordtype AND ti.itemid $itemsql
               ORDER BY ti.ordering ASC";
        $itemparams['component'] = 'mod_expertforum';
        $itemparams['recordtype'] = 'expertforum_post';
        $rs = $DB->get_recordset_sql($sql, $itemparams);
        foreach ($rs as $record) {
            $this->fetchedtags[$record->itemid][] = $record;
        }
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
    public function export_for_template(\renderer_base $output) {
        $posts = array();
        foreach ($this->records as $id => $record) {
            $post = new \mod_expertforum_post($record, $this->cm,
                    empty($this->fetchedtags[$id]) ? array() : $this->fetchedtags[$id]);
            $user = \user_picture::unalias($record, array('deleted'), 'useridx', 'user');

            $r = new stdClass();

            // Post information.
            $r->viewurl = $post->get_url()->out(false);
            $r->subject = $post->get_formatted_subject();
            $r->timecreated = userdate($record->timecreated, get_string('strftimedatetime', 'core_langconfig'));
            $r->votes = $record->votes;
            $r->answers = $record->answers;
            $r->excerpt = $post->get_excerpt();
            $r->views = 0; // TODO
            $r->timestamp = $post->get_timestamp();

            // User information.
            $userpicture = new user_picture($user);
            $userpicture->size = 32;
            $userpicture->courseid = $this->cm->course;
            $context = \context_course::instance($this->cm->course);
            $r->username = fullname($user, has_capability('moodle/site:viewfullnames', $context));
            if (has_capability('moodle/user:viewdetails', $context)) {
                $r->username = \html_writer::link(new \moodle_url('/user/view.php',
                    array('id' => $user->id, 'course' => $this->cm->course)), $r->username);
            } else {
                $userpicture->link = false;
            }
            $r->userpicture = $output->render($userpicture);
            $r->userreputation = 15; // TODO

            $r->tags = $post->get_tags();

            $posts[] = $r;
        }
        return array('posts' => $posts);
    }
}
