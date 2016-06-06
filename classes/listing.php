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
 * Contains class mod_expertforum_listing
 *
 * @package   mod_expertforum
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class mod_expertforum_listing
 *
 * @package   mod_expertforum
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_expertforum_listing {

    const PERPAGE = 20;

    /**
     * Returns posts tagged with a specified tag.
     *
     * This is a callback used by the tag area mod_expertforum/expertforum_post to search for posts
     * tagged with a specific tag.
     *
     * @global core_renderer $OUTPUT
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
    protected static function tagged_posts($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
        $perpage = $exclusivemode ? self::PERPAGE : 5;

        // Basic precheck.
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        if (!$rec && $context->contextlevel != CONTEXT_MODULE) {
            // Non-recursive search in any context except for the module context will not return anything anyway.
            return array();
        }
        if ($context->contextlevel == CONTEXT_BLOCK || $context->contextlevel == CONTEXT_USER) {
            // Nothing can be tagged
            return array();
        }

        // Build the SQL query.
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $userfields = user_picture::fields('u', array('deleted'), 'useridx', 'user');
        $params = array('itemtype' => 'expertforum_post', 'component' => 'mod_expertforum',
            'coursemodulecontextlevel' => CONTEXT_MODULE);
        $fromtag = $wheretag = '';
        if ($tag) {
            $fromtag = 'JOIN {tag_instance} tt ON ep.id = tt.itemid ';
            $wheretag = 'tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component AND ';
            $params['tagid'] = $tag->id;
        }
        $query = "SELECT ep.id, ep.groupid, ep.subject, e.id AS expertforumid, $userfields,
                        cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                    FROM {expertforum_post} ep
                    JOIN {expertforum} e ON e.id = ep.expertforumid
                    JOIN {modules} m ON m.name = 'expertforum'
                    JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = e.id
                    $fromtag
                    JOIN {course} c ON cm.course = c.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
                    LEFT OUTER JOIN {user} u ON u.deleted = 0 AND u.id = ep.userid
                   WHERE $wheretag ep.parent is NULL AND ep.id %ITEMFILTER% AND c.id %COURSEFILTER%";

        if ($context->contextlevel == CONTEXT_COURSE) {
            $query .= ' AND c.id = :courseid';
            $params['courseid'] = $context->instanceid;
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $query .= ' AND cm.id = :cmid';
            $params['cmid'] = $context->instanceid;
        } else if ($context->contextlevel != CONTEXT_SYSTEM) {
            $query .= ' AND (ctx.id = :contextid OR ctx.path LIKE :path)';
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

        // Use core_tag_index_builder to build and filter the list of items.
        $builder = new core_tag_index_builder('mod_expertforum', 'expertforum_post', $query, $params, $page * $perpage, $perpage + 1);
        while ($item = $builder->has_item_that_needs_access_check()) {
            context_helper::preload_from_record($item);
            $courseid = $item->courseid;
            if (!$builder->can_access_course($courseid)) {
                $builder->set_accessible($item, false);
                if ($context->contextlevel >= CONTEXT_COURSE) {
                    // No need to check anything else - everything is not accessible.
                    return array();
                }
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
                    } else if ($context->contextlevel >= CONTEXT_MODULE) {
                        // No need to check anything else - everything is not accessible.
                        return array();
                    }
                    $builder->set_accessible($taggeditem, $accessible);
                }
            });
        }

        $items = $builder->get_items();

        return $items;
    }


    /**
     * Returns posts tagged with a specified tag.
     *
     * This is a callback used by the tag area mod_expertforum/expertforum_post to search for posts
     * tagged with a specific tag.
     *
     * @global core_renderer $OUTPUT
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
    public static function tagged_posts_index($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
        global $OUTPUT;
        $perpage = $exclusivemode ? self::PERPAGE : 5;
        $items = self::tagged_posts($tag, $exclusivemode , $fromctx , $ctx , $rec, $page);
        $totalpages = $page + 1;
        if (count($items) > $perpage) {
            $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
            array_pop($items);
        }

        // Build the display contents.
        if ($items) {
            if ($exclusivemode) {
                $content = self::display_items_full($OUTPUT, $items, true);
            } else {
                $content = self::display_items_tagfeed($OUTPUT, $items);
            }

            return new core_tag\output\tagindex($tag, 'mod_expertforum', 'expertforum_post', $content,
                    $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
        }
    }

    /**
     *
     * @global moodle_database $DB
     * @param cm_info $cm
     * @param type $page
     * @return type
     */
    /*public static function module_posts_display_OLD(cm_info $cm, $page = 0) {
        // TODO: filter visible groups.
        global $DB, $OUTPUT;
        $perpage = 20;

        $params = array('expertforumid' => $cm->instance, 'courseid' => $cm->course, 'cmid' => $cm->id);
        $userfields = user_picture::fields('u', array('deleted'), 'useridx', 'user');
        $sql = "SELECT p.id, p.subject, p.expertforumid, $userfields, :cmid AS cmid, p.courseid
            FROM {expertforum_post} p
            LEFT OUTER JOIN {user} u ON u.deleted = 0 AND u.id = p.userid
            WHERE p.parent is null
                AND p.expertforumid = :expertforumid
                AND p.courseid = :courseid
            ORDER BY p.timecreated DESC
                ";
        $items = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        $content = self::display_items_full($OUTPUT, $items);
        return $content; // TODO display paging bar

        //$fromctx = $ctx = context_module::instance($cm->id)->id;
        //$totalpages = 1; // TODO.
        //$tagindex = new core_tag\output\tagindex($tag, 'mod_expertforum', 'expertforum_post', $content,
        //            true, $fromctx, $ctx, false, $page, $totalpages);
        //return $OUTPUT->render_from_template('core_tag/index', $tagindex->export_for_template($OUTPUT));
    }*/

    public static function module_posts_display($cm, $tag, $page = 0) {
        global $OUTPUT;
        $perpage = self::PERPAGE;
        $context = context_module::instance($cm->id);
        $items = self::tagged_posts($tag, true, 0, $context->id, false, $page);
        $hasnextpage = count($items) > $perpage;
        if ($hasnextpage) {
            array_pop($items);
        }
        $content = self::display_items_full($OUTPUT, $items, false);
        if ($page || $hasnextpage) {
            $content .= self::paging_bar($cm, $tag, $page, $hasnextpage);
        }
        return $content;
    }

    protected static function paging_bar($cm, $tag, $page, $hasnextpage) {
        return ''; // TODO display paging bar
    }

    protected static function display_items_tagfeed(core_renderer $output, $items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            $user = \user_picture::unalias($item, array('deleted'), 'useridx', 'user');
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/expertforum/viewpost.php',
                    array('e' => $item->expertforumid, 'id' => $item->id));
            $pagename = format_string($item->subject, true, array('context' => context_module::instance($item->cmid)));
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, array('context' => context_course::instance($item->courseid)));
            $coursename = html_writer::link($courseurl, $coursename);
            if (isset($user->id)) {
                $icon = $output->user_picture($user, ['courseid' => $item->courseid]);
            } else {
                $icon = html_writer::link($pageurl, html_writer::empty_tag('img', array('src' => $cm->get_icon_url())));
            }
            $tagfeed->add($icon, $pagename, $cmname.'<br>'.$coursename);
        }

        return $output->render_from_template('core_tag/tagfeed',
                $tagfeed->export_for_template($output));
    }

    protected static function fetch_additional_data(&$items) {
        global $DB;

        if (empty($items)) {
            return;
        }

        $fetchedtags = array();
        list($itemsql, $itemparams) = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED);

        // Get additional details about items (message, timecreated, etc.)
        $sql = "SELECT p.id, timecreated, message, messageformat, messagetrust, votes
                FROM {expertforum_post} p
                WHERE p.id $itemsql";
        $records = $DB->get_records_sql($sql, $itemparams);
        foreach ($records as $record) {
            $items[$record->id]->timecreated = $record->timecreated;
            $items[$record->id]->message = $record->message;
            $items[$record->id]->messageformat = $record->messageformat;
            $items[$record->id]->messagetrust = $record->messagetrust;
            $items[$record->id]->votes = $record->votes;
        }

        // Get number of answers for each post.
        $sql = "SELECT parent, COUNT(id) AS answers
                FROM {expertforum_post}
                WHERE parent $itemsql
                GROUP BY parent";
        $records = $DB->get_records_sql($sql, $itemparams);
        foreach ($records as $record) {
            $items[$record->parent]->answers = $record->answers;
        }

        // Fetch items tags.
        $sql = "SELECT ti.itemid, tg.id, tg.name, tg.rawname
                  FROM {tag_instance} ti
                  JOIN {tag} tg ON tg.id = ti.tagid
                  WHERE ti.component = :component AND ti.itemtype = :recordtype AND ti.itemid $itemsql
               ORDER BY ti.ordering ASC";
        $itemparams['component'] = 'mod_expertforum';
        $itemparams['recordtype'] = 'expertforum_post';
        $rs = $DB->get_recordset_sql($sql, $itemparams);
        foreach ($rs as $record) {
            if (!isset($items[$record->itemid]->tags)) {
                $items[$record->itemid]->tags = [];
            }
            $items[$record->itemid]->tags[] = $record;
        }
        $rs->close();

        return $fetchedtags;
    }

    protected static function display_items_full(core_renderer $output, $items, $showmoduleinfo) {
        self::fetch_additional_data($items);
        $listing = new \mod_expertforum\output\listing($items);
        return $output->render_from_template('mod_expertforum/listing',
                $listing->export_for_template($output));
    }
}

