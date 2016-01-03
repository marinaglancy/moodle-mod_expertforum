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
 * mod_expertforum data generator
 *
 * @package    mod_expertforum
 * @category   test
 * @copyright  2016 Marina Galncy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Expertforum module data generator class
 *
 * @package    mod_expertforum
 * @category   test
 * @copyright  2016 Marina Galncy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_expertforum_generator extends testing_module_generator {

    /**
     * @var int keep track of how many posts have been created.
     */
    protected $postcount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->postcount = 0;
        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Function to create a dummy post.
     *
     * @param array|stdClass $record
     * @return mod_expertforum_post the post object
     */
    public function create_post($record, cm_info $cm = null) {
        global $DB, $USER;

        // Increment the forum post count.
        $this->postcount++;

        // Variable to store time.
        $time = time() + $this->postcount;

        $record = (array) $record +
                array(
                    'userid' => $USER->id,
                    'parent' => null,
                    'subject' => 'Forum post subject ' . $this->postcount,
                    'message' => html_writer::tag('p', 'Forum message post ' . $this->postcount),
                    'messageformat' => FORMAT_HTML,
                    'messagetrust' => 1,
                    'timecreated' => $time,
                    'timemodified' => $time
                );

        if (!empty($record['parent']) &&
                (empty($record['expertforumid']) || empty($record['courseid']))) {
            $parent = $DB->get_record_sql("SELECT courseid, expertforumid FROM {expertforum_post} WHERE id = ?",
                    array($record['parent']), MUST_EXIST);
            $record['expertforumid'] = $parent->expertforumid;
            $record['courseid'] = $parent->courseid;
        }

        if (empty($record['courseid']) && !empty($record['expertforumid'])) {
            $record['courseid'] = $DB->get_field('expertforum', 'course',
                    array('id' => $record['expertforumid']), MUST_EXIST);
        }

        if ($cm) {
            $record['courseid'] = $cm->course;
            $record['expertforumid'] = $cm->instance;
        }

        if (!isset($record['expertforumid'])) {
            throw new coding_exception('expertforumid must be present in mod_expertforum_generator::create_post() $record');
        }

        if (!isset($record['courseid'])) {
            throw new coding_exception('courseid must be present in mod_expertforum_generator::create_post() $record');
        }

        if (!$cm) {
            $modinfo = get_fast_modinfo($record['courseid']);
            $instances = $modinfo->get_instances_of('expertforum');
            $cm = $instances[$record['expertforumid']];
        }
        return mod_expertforum_post::create($record, $cm);
    }
}
