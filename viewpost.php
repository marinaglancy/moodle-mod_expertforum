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
 * Views a forum post with answers
 *
 * @package    mod_expertforum
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$instanceid  = required_param('e', PARAM_INT);
$postid  = optional_param('parent', null, PARAM_INT);
if (!$postid) {
    $postid  = required_param('id', PARAM_INT);
}

list($course, $cm) = get_course_and_cm_from_instance($instanceid, 'expertforum');

require_login($course, true, $cm);
$expertforum  = $DB->get_record('expertforum', array('id' => $cm->instance), '*', MUST_EXIST);
$post = mod_expertforum_post::get($postid, $cm, MUST_EXIST);

if ($upvote = optional_param('upvote', null, PARAM_INT)) {
    require_sesskey();
    $post->vote($upvote, 1);
    redirect($post->get_url());
}
if ($downvote = optional_param('downvote', null, PARAM_INT)) {
    require_sesskey();
    $post->vote($downvote, -1);
    redirect($post->get_url());
}

$PAGE->set_url($post->get_url());
$PAGE->set_title($post->get_formatted_subject());
$PAGE->set_heading(format_string($course->fullname));

$form = new mod_expertforum_post_form(null,
        array('expertforum' => $expertforum, 'parent' => $post, 'cm' => $cm),
        'post', '', array('class' => 'mod_expertforum_post'));

if ($form->is_cancelled()) {
    redirect($PAGE->url);
} else if ($data = $form->get_data()) {
    $post = mod_expertforum_post::create($data, $cm);
    redirect($post->get_url());
}

$PAGE->navbar->add($post->get_formatted_subject());

echo $OUTPUT->header();
echo $OUTPUT->heading($post->get_formatted_subject());

echo $OUTPUT->render_from_template('mod_expertforum/thread', $post->export_for_template($OUTPUT));

$form->display();

// Finish the page.
echo $OUTPUT->footer();
