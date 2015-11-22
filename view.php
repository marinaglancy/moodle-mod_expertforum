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
 * Prints a particular instance of expertforum
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_expertforum
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace expertforum with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$instanceid  = optional_param('e', 0, PARAM_INT);  // ... expertforum instance ID - it should be named as the first character of the module.

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, 'expertforum');
} else if ($instanceid) {
    list($course, $cm) = get_course_and_cm_from_instance($instanceid, 'expertforum');
} else {
    print_error('missingparameter');
}

require_login($course, true, $cm);
$expertforum  = $DB->get_record('expertforum', array('id' => $cm->instance), '*', MUST_EXIST);

$event = \mod_expertforum\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $expertforum);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/expertforum/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($expertforum->name));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('expertforum-'.$somevar);
 */

// Output starts here.
echo $OUTPUT->header();

// Conditions to show the intro can change to look for own settings or whatever.
if ($expertforum->intro) {
    echo $OUTPUT->box(format_module_intro('expertforum', $expertforum, $cm->id), 'generalbox mod_introbox', 'expertforumintro');
}

echo $OUTPUT->single_button(
        new moodle_url('/mod/expertforum/post.php', array('e' => $expertforum->id)),
        'Ask question' // TODO string
        );

$listing = new mod_expertforum\output\listing($cm);
echo $OUTPUT->render_from_template('mod_expertforum/listing', $listing->export_for_template($OUTPUT));

// Finish the page.
echo $OUTPUT->footer();
