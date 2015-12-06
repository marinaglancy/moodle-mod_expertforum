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
 * Adds a post to the forum
 *
 * @package    mod_expertforum
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$instanceid  = required_param('e', PARAM_INT);
$editpostid  = optional_param('edit', null, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_instance($instanceid, 'expertforum');

require_login($course, true, $cm);
$expertforum  = $DB->get_record('expertforum', array('id' => $cm->instance), '*', MUST_EXIST);
if ($editpostid) {
    $postrecord = $DB->get_record('expertforum_post',
            array('id' => $editpostid, 'expertforumid' => $expertforum->id), '*', MUST_EXIST);
    $post = new mod_expertforum_post($postrecord, $cm);
} else {
    $post = null;
}

// Print the page header.

$PAGE->set_url('/mod/expertforum/post.php', array('e' => $cm->instance));
$PAGE->set_title(format_string($expertforum->name));
$PAGE->set_heading(format_string($course->fullname));

$form = new mod_expertforum_post_form(null,
        array('expertforum' => $expertforum, 'post' => $post, 'cm' => $cm),
        'post', '', array('class' => 'mod_expertforum_post'));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/expertforum/view.php', array('id' => $cm->id)));
} else if ($data = $form->get_data()) {
    if ($post) {
        $post->update($data);
    } else {
        $post = mod_expertforum_post::create($data, $cm);
    }
    redirect($post->get_url());
}


// Output starts here.
echo $OUTPUT->header();

$form->display();

// Finish the page.
echo $OUTPUT->footer();
