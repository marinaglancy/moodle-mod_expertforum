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
 * Internal library of functions for module expertforum
 *
 * All the expertforum specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_expertforum
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * Does something really useful with the passed things
 *
 * @param array $things
 * @return object
 *function expertforum_do_something_useful(array $things) {
 *    return new stdClass();
 *}
 */


/**
 * Returns posts tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_expertforum/expertforum_post to search for posts
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_expertforum_get_tagged_posts($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT ep.id, ep.groupid, ep.subject, e.id AS epid,
                    cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {expertforum_post} ep
                JOIN {expertforum} e ON e.id = ep.expertforumid
                JOIN {modules} m ON m.name='expertforum'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = e.id
                JOIN {tag_instance} tt ON ep.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component AND ep.parent is NULL
                 AND ep.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = array('itemtype' => 'expertforum_post', 'tagid' => $tag->id, 'component' => 'mod_expertforum',
        'coursemodulecontextlevel' => CONTEXT_MODULE);

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path.'/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path.'/%';
    }
    $query .= ' c.sortorder, cm.id, ep.id';

    $totalpages = $page + 1;

    // Use core_tag_index_builder to build and filter the list of items.
    $builder = new core_tag_index_builder('mod_expertforum', 'expertforum_post', $query, $params, $page * $perpage, $perpage + 1);
    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        $builder->walk(function ($taggeditem) use ($courseid, $modinfo, $builder) {
            if ($taggeditem->courseid == $courseid) {
                $accessible = false;
                if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                    // TODO check group access.
                    $accessible = true;
                }
                $builder->set_accessible($taggeditem, $accessible);
            }
        });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/expertforum/viewpost.php', array('e' => $item->epid, 'id' => $item->id));
            $pagename = format_string($item->subject, true, array('context' => context_module::instance($item->cmid)));
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, array('context' => context_course::instance($item->courseid)));
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', array('src' => $cm->get_icon_url())));
            $tagfeed->add($icon, $pagename, $cmname.'<br>'.$coursename);
        }

        $content = $OUTPUT->render_from_template('core_tag/tagfeed',
                $tagfeed->export_for_template($OUTPUT));

        return new core_tag\output\tagindex($tag, 'mod_expertforum', 'expertforum_post', $content,
                $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    }
}
