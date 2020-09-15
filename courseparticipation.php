<?php
// http://moodle35.localhost/mod/oublog/courseparticipation.php?course=868&individual=28751
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
 * Page for viewing user  participation list
 *
 * @package mod_oublog
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/mod/oublog/lib.php');
require_once($CFG->dirroot . '/mod/oublog/locallib.php');

$courseid = required_param('course', PARAM_INT); // Course ID.
$curindividual = optional_param('individual', 0, PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$start = optional_param('start', 0, PARAM_INT);
$end = optional_param('end', 0, PARAM_INT);

$download = optional_param('download', '', PARAM_ALPHA);

if ($groupid < 0) {
    $groupid = 0;
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$context = context_course::instance($courseid);

$isStudent = !has_capability('moodle/course:update', $context, $USER->id) ? true : false;
$users = oublog_get_users_in_course($course->id);

if ($isStudent) {
    $curindividual = $USER->id;
} else {
    if (isset($users) && $curindividual == 0) {
        $curindividual = reset($users)->userid;
    }
}

$urlroot = '/mod/oublog/courseparticipation.php';
$params = array(
    'course' => $courseid,
    'individual' => $curindividual,
    'group' => $groupid,
    'page' => $page,
    'start' => $start,
    'end' => $end
);

$url = new moodle_url($urlroot, $params);
$PAGE->set_url($url);

$limitnum = OUBLOG_PARTICIPATION_PERPAGE;
$limitfrom = empty($page) ? null : $page * $limitnum;

$user = $DB->get_record('user', array('id' => $curindividual), '*', MUST_EXIST);

$exportfilename = $course->shortname . '-oublog (' . fullname($user) . ')';

$columns = array(
    'name',
    'content',
    'timeposted',
);

$headers = array(
    'Name',
    'Post',
    'Time',
);

$table = new \flexible_table($exportfilename);
$isdownloading = $table->is_downloading($download, $exportfilename);

if (!$table->is_downloading()) {
    $PAGE->set_course($course);
    $context = context_course::instance($course->id);
    $PAGE->set_pagelayout('incourse');
    require_course_login($course, true);

    // Create time filter options form.
    $customdata = array(
        'course' => $course->id,
        'individual' => $curindividual,
        'group' => $groupid,
        'startyear' => $course->startdate,
    );
    $timefilter = new oublog_participation_timefilter_form(null, $customdata, $method = 'get');

    $start = $end = 0;
    // If data has been received from this form.
    if ($submitted = $timefilter->get_data()) {
        if ($submitted->start) {
            $start = strtotime('00:00:00', $submitted->start);
        }
        if ($submitted->end) {
            $end = strtotime('23:59:59', $submitted->end);
        }
    }

    $url->params($params);
    $PAGE->set_url($url);
    $PAGE->set_title(format_string($course->fullname));
    $PAGE->set_heading(format_string($course->fullname));

    echo $OUTPUT->header();

    // User dropdown in with posts in the course

    if (!$isStudent) {
        $label = get_string('separateindividual', 'oublog') . ' ';
        $active = '';
        foreach ($users as $user) {
            $url = $urlroot . '?course=' . $course->id . '&amp;individual=' . $user->userid;
            $url = str_replace($CFG->wwwroot, '', $url);
            $url = str_replace('&amp;', '&', $url);
            if ($curindividual == $user->userid) {
                $active = $url;
            }
            $urls[$url] = format_string($user->firstname . ' ' . $user->lastname);
        }
        if (!empty($urls)) {
            $select = new url_select($urls, $active, null, 'selectindividual');
            $select->set_label($label);
            $individualdetails = $OUTPUT->render($select);
        }

        echo '<div class="oublog-individualselector">' . $individualdetails . '</div>';
    }
    echo html_writer::tag('h2', $course->fullname, array('class' => 'oublog-post-title'));

    $timefilter->display();
}

$posts = oublog_get_user_course_posts($course->id, $curindividual, $start, $end);

$table->define_headers($headers);
$table->define_columns($columns);
$table->define_baseurl($url);
$table->show_download_buttons_at(array(TABLE_P_BOTTOM));
$table->setup();
$table->is_downloadable(true);

foreach ($posts as $post) {
    $row['name'] = \html_writer::link(new moodle_url('/mod/oublog/viewpost.php', array('post' => $post->id)), $post->name);
    $row['content'] = $post->content;
    $row['timeposted'] = userdate($post->timeposted);
    $table->add_data_keyed($row);
}

$table->finish_output();

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
