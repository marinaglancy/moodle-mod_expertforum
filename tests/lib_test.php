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
 * The module expertforums tests
 *
 * @package    mod_expertforum
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mod_expertforum_lib_testcase extends advanced_testcase {

    /**
     * Create a course, expertforum, three users, one question and two answers
     * by different users.
     * @return mod_expertforum_post the question post
     */
    protected function prepare() {
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $ef = $this->getDataGenerator()->create_module('expertforum',
                array('course' => $course->id, 'grade' => 100));
        $cm = get_fast_modinfo($course)->get_cm($ef->cmid);

        $post = mod_expertforum_post::create(
                array('userid' => $user1->id, 'subject' => 'Question',
                    'message' => 'Question text'),
                $cm);

        $post2 = mod_expertforum_post::create(
                array('parent' => $post->id, 'userid' => $user2->id,
                    'message' => 'Answer text'), $cm);

        $post3 = mod_expertforum_post::create(
                array('parent' => $post->id, 'userid' => $user3->id,
                    'message' => 'Answer text'), $cm);

        return $post;
    }

    public function test_generator() {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->assertEquals(0, $DB->count_records('expertforum'));

        $course = $this->getDataGenerator()->create_course();
        $ef = $this->getDataGenerator()->create_module('expertforum',
                array('course' => $course->id, 'grade' => 100));

        $this->assertEquals(1, $DB->count_records('expertforum'));

        /** @var mod_expertforum_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_expertforum');

        $this->assertEquals(0, $DB->count_records('expertforum_post'));
        $post = $generator->create_post(
                array('expertforumid' => $ef->id, 'courseid' => $course->id,
                    'userid' => $user1->id));
        $this->assertEquals(1, $DB->count_records('expertforum_post'));
        $this->assertEquals(array('courseid' => $course->id,
            'expertforumid' => $ef->id,
            'parent' => 0,
            'userid' => $user1->id),
                (array)$DB->get_record('expertforum_post',
                array('id' => $post->id), 'courseid,expertforumid,parent,userid'));

        $post2 = $generator->create_post(
                array('parent' => $post->id, 'userid' => $user2->id));
        $this->assertEquals(2, $DB->count_records('expertforum_post'));
        $this->assertEquals(array('courseid' => $course->id,
            'expertforumid' => $ef->id,
            'parent' => $post->id,
            'userid' => $user2->id),
                (array)$DB->get_record('expertforum_post',
                array('id' => $post2->id), 'courseid,expertforumid,parent,userid'));
    }

    public function test_create() {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $ef = $this->getDataGenerator()->create_module('expertforum',
                array('course' => $course->id, 'grade' => 100));
        $cm = get_fast_modinfo($course)->get_cm($ef->cmid);

        $this->assertEquals(0, $DB->count_records('expertforum_post'));
        $post = mod_expertforum_post::create(
                array('userid' => $user1->id, 'subject' => 'Question',
                    'message' => 'Question text'),
                $cm);
        $this->assertEquals(1, $DB->count_records('expertforum_post'));
        $this->assertEquals(array('courseid' => $course->id,
            'expertforumid' => $ef->id,
            'parent' => 0,
            'userid' => $user1->id),
                (array)$DB->get_record('expertforum_post',
                array('id' => $post->id), 'courseid,expertforumid,parent,userid'));

        $post2 = mod_expertforum_post::create(
                array('parent' => $post->id, 'userid' => $user2->id,
                    'message' => 'Answer text'), $cm);
        $this->assertEquals(2, $DB->count_records('expertforum_post'));
        $this->assertEquals(array('courseid' => $course->id,
            'expertforumid' => $ef->id,
            'parent' => $post->id,
            'userid' => $user2->id),
                (array)$DB->get_record('expertforum_post',
                array('id' => $post2->id), 'courseid,expertforumid,parent,userid'));

        // Test mod_expertforum_post::get().
        $fetchedpost = mod_expertforum_post::get($post->id, $cm);
        $this->assertEquals($post->id, $fetchedpost->id);

        // One can not use method get() to retrieve answer.
        $this->assertNull(mod_expertforum_post::get($post2->id, $cm));

        // Test method get_answers().
        $answers = array_values($fetchedpost->get_answers());
        $this->assertCount(1, $answers);
        $this->assertEquals($post2->id, $answers[0]->id);
    }

    public function test_update() {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $ef = $this->getDataGenerator()->create_module('expertforum',
                array('course' => $course->id, 'grade' => 100));
        $cm = get_fast_modinfo($course)->get_cm($ef->cmid);

        $post = mod_expertforum_post::create(
                array('userid' => $user1->id, 'subject' => 'Question',
                    'message' => 'Question text'),
                $cm);

        $post->update((object)array('message' => 'New question text'));
        $record = $DB->get_record('expertforum_post', array('id' => $post->id));
        $this->assertEquals('New question text', $record->message);

        $post->update((object)array('message_editor' =>
            array('text' => 'Another edit', 'format' => FORMAT_HTML)));
        $record = $DB->get_record('expertforum_post', array('id' => $post->id));
        $this->assertEquals('Another edit', $record->message);
    }

    public function test_update_tags() {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $ef = $this->getDataGenerator()->create_module('expertforum',
                array('course' => $course->id, 'grade' => 100));
        $cm = get_fast_modinfo($course)->get_cm($ef->cmid);

        $post = mod_expertforum_post::create(
                array('userid' => $user1->id, 'subject' => 'Question',
                    'message' => 'Question text', 'tags' => array('programming')),
                $cm);

        $postfetched = mod_expertforum_post::get($post->id, $cm);
        $tags = $postfetched->get_tags();
        $this->assertCount(1, $tags);
        $this->assertEquals('programming', $tags[0]['tagname']);

        $post->update((object)array('tags' => array('Php', 'programming')));

        $postfetched = mod_expertforum_post::get($post->id, $cm);
        $tags = $postfetched->get_tags();
        $this->assertCount(2, $tags);
        $this->assertEquals('Php', $tags[0]['tagname']);
        $this->assertEquals('programming', $tags[1]['tagname']);
    }

    public function test_voting() {
        $post = $this->prepare();
        $answers = array_values($post->get_answers());

        $this->assertEquals(0, $post->votes);
        $this->assertEquals(0, $answers[0]->votes);
        $this->assertEquals(0, $answers[1]->votes);

        // User can not vote for his own question but other users can.
        $this->setUser($post->userid);
        $this->assertFalse($post->can_upvote());
        $this->assertFalse($post->can_downvote());

        $this->setUser($answers[0]->userid);
        $this->assertTrue($post->can_upvote());
        $this->assertTrue($post->can_downvote());

        // Voting on own posts has no effect.
        $this->setUser($post->userid);
        $post->vote($post->id, 1);
        $this->assertEquals(0, $post->votes);
        $fetchedpost = mod_expertforum_post::get($post->id, $post->cm);
        $this->assertEquals(0, $fetchedpost->votes);

        // Users can vote on other users' posts but only once.
        $this->setUser($answers[0]->userid);
        $post->vote($post->id, 1);
        $this->assertEquals(1, $post->votes);
        $fetchedpost = mod_expertforum_post::get($post->id, $post->cm);
        $this->assertEquals(1, $fetchedpost->votes);
        // Voting again makes no effect.
        $post->vote($post->id, 1);
        $this->assertEquals(1, $post->votes);
        $fetchedpost = mod_expertforum_post::get($post->id, $post->cm);
        $this->assertEquals(1, $fetchedpost->votes);
        // Same user downvoting.
        $post->vote($post->id, -1);
        $this->assertEquals(-1, $post->votes);
        $fetchedpost = mod_expertforum_post::get($post->id, $post->cm);
        $this->assertEquals(-1, $fetchedpost->votes);

        // Another user downvoting.
        $this->setUser($answers[1]->userid);
        $post->vote($post->id, -1);
        $this->assertEquals(-2, $post->votes);
        $fetchedpost = mod_expertforum_post::get($post->id, $post->cm);
        $this->assertEquals(-2, $fetchedpost->votes);

        // Two users voting for an answer.
        $post->vote($answers[0]->id, 1);
        $this->setUser($post->userid);
        $post->vote($answers[0]->id, 1);
        $this->assertEquals(2, $answers[0]->votes);
        $fetchedpost = mod_expertforum_post::get($post->id, $post->cm);
        $this->assertEquals(2, $post->find_answer($answers[0]->id)->votes);
    }
}
