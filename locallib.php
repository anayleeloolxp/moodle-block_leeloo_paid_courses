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
 * Helper functions for leeloo_paid_courses block
 *
 * @package   block_leeloo_paid_courses
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
define('BLOCKS_LEELOO_PAID_COURSES_SHOWCATEGORIES_NONE', '0');
define('BLOCKS_LEELOO_PAID_COURSES_SHOWCATEGORIES_ONLY_PARENT_NAME', '1');
define('BLOCKS_LEELOO_PAID_COURSES_SHOWCATEGORIES_FULL_PATH', '2');
define('BLOCKS_LEELOO_PAID_COURSES_IMAGEASBACKGROUND_FALSE', '0');
define('BLOCKS_LEELOO_PAID_COURSES_SHOWGRADES_NO', '0');
define('BLOCKS_LEELOO_PAID_COURSES_SHOWGRADES_YES', '1');
define('BLOCKS_LEELOO_PAID_COURSES_STARTGRID_NO', '0');
define('BLOCKS_LEELOO_PAID_COURSES_STARTGRID_YES', '1');
define('BLOCKS_LEELOO_PAID_COURSES_DEFAULT_COURSES_ROW', '4');
define('BLOCKS_LEELOO_PAID_COURSES_DEFAULT_COL_SIZE', '3');
define('BLOCKS_LEELOO_PAID_COURSES_SHOWTEACHERS_NO', '0');
define('BLOCKS_LEELOO_PAID_COURSES_SHOWTEACHERS_YES', '1');
require_once($CFG->libdir . '/completionlib.php');
use core_completion\progress;
/**
 * Display overview for courses
 *
 * @param array $courses courses for which overview needs to be shown
 * @return array html overview
 */
function block_leeloo_paid_courses_get_overviews($courses) {
    $htmlarray = array();
    if ($modules = get_plugin_list_with_function('mod', 'print_overview')) {
        // Split courses list into batches with no more than MAX_MODINFO_CACHE_SIZE courses in one batch.
        // Otherwise we exceed the cache limit in get_fast_modinfo() and rebuild it too often.
        if (defined('MAX_MODINFO_CACHE_SIZE') && MAX_MODINFO_CACHE_SIZE > 0 && count($courses) > MAX_MODINFO_CACHE_SIZE) {
            $batches = array_chunk($courses, MAX_MODINFO_CACHE_SIZE, true);
        } else {
            $batches = array($courses);
        }
        foreach ($batches as $courses) {
            foreach ($modules as $fname) {
                $fname($courses, $htmlarray);
            }
        }
    }
    return $htmlarray;
}

/**
 * Sets user preference for maximum courses to be displayed in leeloo_paid_courses block
 *
 * @param int $number maximum courses which should be visible
 */
function block_leeloo_paid_courses_update_mynumber($number) {
    set_user_preference('leeloo_paid_courses_number_of_courses', $number);
}

/**
 * Sets user course sorting preference in leeloo_paid_courses block
 *
 * @param array $sortorder list of course ids
 */
function block_leeloo_paid_courses_update_myorder($sortorder) {
    $value = implode(',', $sortorder);
    if (core_text::strlen($value) > 1333) {
        // The value won't fit into the user preference. Remove courses in the end of the list
        // (mostly likely user won't even notice).
        $value = preg_replace('/,[\d]*$/', '', core_text::substr($value, 0, 1334));
    }
    set_user_preference('leeloo_paid_courses_course_sortorder', $value);
}

/**
 * Gets user course sorting preference in leeloo_paid_courses block
 *
 * @return array list of course ids
 */
function block_leeloo_paid_courses_get_myorder() {
    if ($value = get_user_preferences('leeloo_paid_courses_course_sortorder')) {
        return explode(',', $value);
    }
    // If preference was not found, look in the old location and convert if found.
    $order = array();
    if ($value = get_user_preferences('leeloo_paid_courses_course_order')) {
        $order = unserialize_array($value);
        block_leeloo_paid_courses_update_myorder($order);
        unset_user_preference('leeloo_paid_courses_course_order');
    }
    return $order;
}

/**
 * Returns shortname of activities in course
 *
 * @param int $courseid id of course for which activity shortname is needed
 * @return string|bool list of child shortname
 */
function block_leeloo_paid_courses_get_child_shortnames($courseid) {
    global $DB;
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $sql = "SELECT c.id, c.shortname, $ctxselect
            FROM {enrol} e
            JOIN {course} c ON (c.id = e.customint1)
            JOIN {context} ctx ON (ctx.instanceid = e.customint1)
            WHERE e.courseid = :courseid AND e.enrol = :method AND ctx.contextlevel = :contextlevel ORDER BY e.sortorder";
    $params = array('method' => 'meta', 'courseid' => $courseid, 'contextlevel' => CONTEXT_COURSE);

    if ($results = $DB->get_records_sql($sql, $params)) {
        $shortnames = array();
        // Preload the context we will need it to format the category name shortly.
        foreach ($results as $res) {
            context_helper::preload_from_record($res);
            $context = context_course::instance($res->id);
            $shortnames[] = format_string($res->shortname, true, $context);
        }
        $total = count($shortnames);
        $suffix = '';
        if ($total > 10) {
            $shortnames = array_slice($shortnames, 0, 10);
            $diff = $total - count($shortnames);
            if ($diff > 1) {
                $suffix = get_string('shortnamesufixprural', 'block_leeloo_paid_courses', $diff);
            } else {
                $suffix = get_string('shortnamesufixsingular', 'block_leeloo_paid_courses', $diff);
            }
        }
        $shortnames = get_string('shortnameprefix', 'block_leeloo_paid_courses', implode('; ', $shortnames));
        $shortnames .= $suffix;
    }

    return isset($shortnames) ? $shortnames : false;
}

/**
 * Returns maximum number of courses which will be displayed in leeloo_paid_courses block
 *
 * @param bool $showallcourses if set true all courses will be visible.
 * @return int maximum number of courses
 */
function block_leeloo_paid_courses_get_max_user_courses($showallcourses = false) {
    // Get block configuration.
    $leeloolxplicense = get_config('block_leeloo_paid_courses')->license;

    $url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
    $postdata = [
        'license_key' => $leeloolxplicense,
    ];

    $curl = new curl;

    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );

    if (!$output = $curl->post($url, $postdata, $options)) {
        $limit = get_user_preferences('leeloo_paid_courses_number_of_courses', $limit);
    }

    $infoleeloolxp = json_decode($output);

    if ($infoleeloolxp->status != 'false') {
        $leeloolxpurl = $infoleeloolxp->data->install_url;
    } else {
        $limit = get_user_preferences('leeloo_paid_courses_number_of_courses', $limit);
    }

    $url = $leeloolxpurl . '/admin/Theme_setup/get_courses_for_sale_settings';

    $postdata = [
        'license_key' => $leeloolxplicense,
    ];

    $curl = new curl;

    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );

    if (!$output = $curl->post($url, $postdata, $options)) {
        $limit = get_user_preferences('leeloo_paid_courses_number_of_courses', $limit);
    }

    $resposedata = json_decode($output);
    $settingleeloolxp = $resposedata->data->courses_for_sale;

    $limit = @$settingleeloolxp->defaultmaxcourses;

    // If max course is not set then try get user preference.
    if (empty($settingleeloolxp->forcedefaultmaxcourses)) {
        if ($showallcourses) {
            $limit = 0;
        } else {
            $limit = get_user_preferences('leeloo_paid_courses_number_of_courses', $limit);
        }
    }
    return $limit;
}

/**
 * Return sorted list of user courses
 *
 * @param bool $showallcourses if set true all courses will be visible.
 * @param array $fccourses set which courses to show.
 * @return array list of sorted courses and count of courses.
 */
function block_leeloo_paid_courses_get_sorted_courses($showallcourses = false, $fccourses = array()) {
    global $USER;

    $limit = block_leeloo_paid_courses_get_max_user_courses($showallcourses);

    $allcourses = get_courses();

    $courses = array();

    foreach ($allcourses as $courseid => $coursesing) {
        if (in_array($coursesing->id, $fccourses)) {
            $courses[$courseid] = $coursesing;
        }
    }

    $site = get_site();

    if (array_key_exists($site->id, $courses)) {
        unset($courses[$site->id]);
    }

    foreach ($courses as $c) {
        if (isset($USER->lastcourseaccess[$c->id])) {
            $courses[$c->id]->lastaccess = $USER->lastcourseaccess[$c->id];
        } else {
            $courses[$c->id]->lastaccess = 0;
        }
    }

    // Get remote courses.
    $remotecourses = array();
    if (is_enabled_auth('mnet')) {
        $remotecourses = get_my_remotecourses();
        // Remote courses will have -ve remoteid as key, so it can be differentiated from normal courses.
        foreach ($remotecourses as $val) {
            $remoteid = $val->remoteid * -1;
            $val->id = $remoteid;
            $courses[$remoteid] = $val;
        }
    }

    $order = block_leeloo_paid_courses_get_myorder();

    $sortedcourses = array();
    $counter = 0;
    // Get courses in sort order into list.
    foreach ($order as $cid) {
        if (($counter >= $limit) && ($limit != 0)) {
            break;
        }

        // Make sure user is still enrolled.
        if (isset($courses[$cid])) {
            $sortedcourses[$cid] = $courses[$cid];
            $counter++;
        }
    }
    // Append unsorted courses if limit allows.
    foreach ($courses as $c) {
        if (($limit != 0) && ($counter >= $limit)) {
            break;
        }
        if (!in_array($c->id, $order)) {
            $sortedcourses[$c->id] = $c;
            $counter++;
        }
    }
    return array($sortedcourses, count($courses));
}

/**
 * The course progress builder
 *
 * @param object $course The course whose progress we want
 * @param object $config Settings from leeloo
 * @return string
 */
function block_leeloo_paid_courses_build_progress($course, $config) {
    global $CFG;

    require_once($CFG->dirroot . '/grade/querylib.php');
    require_once($CFG->dirroot . '/grade/lib.php');
    $completestring = get_string('complete');

    if (@$config->progressenabled == BLOCKS_LEELOO_PAID_COURSES_SHOWGRADES_NO) {
        return '';
    }

    $percentage = progress::get_course_progress_percentage($course);
    if (!is_null($percentage)) {
        $percentage = floor($percentage);
    } else {
        $percentage = 0;
    }

    $bar = html_writer::div('', 'value', array('aria-valuenow' => "$percentage",
        'aria-valuemin' => "0", 'aria-valuemax' => "100", 'style' => "width:$percentage%"));
    $progress = html_writer::div($bar, 'progress', array('data-label' => "$percentage% $completestring"));

    return $progress;
}

/**
 * The course progress check
 *
 * @param object $course The course whose progress we want
 * @return string
 */
function block_leeloo_paid_courses_progress_percent($course) {
    global $CFG;

    require_once($CFG->dirroot . '/grade/querylib.php');
    require_once($CFG->dirroot . '/grade/lib.php');

    $percentage = progress::get_course_progress_percentage($course);
    if (!is_null($percentage)) {
        $percentage = floor($percentage);
    } else {
        $percentage = 0;
    }
    return $percentage;
}

/**
 * Fetch and Update Configration From L
 */
function updateconfpaid_courses() {
    if (isset(get_config('block_leeloo_paid_courses')->license)) {
        $leeloolxplicense = get_config('block_leeloo_paid_courses')->license;
    } else {
        return;
    }

    $url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
    $postdata = [
        'license_key' => $leeloolxplicense,
    ];
    $curl = new curl;
    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );
    if (!$output = $curl->post($url, $postdata, $options)) {
        return;
    }
    $infoleeloolxp = json_decode($output);
    if ($infoleeloolxp->status != 'false') {
        $leeloolxpurl = $infoleeloolxp->data->install_url;
    } else {
        set_config('settingsjson', base64_encode($output), 'block_leeloo_paid_courses');
        return;
    }
    $url = $leeloolxpurl . '/admin/Theme_setup/get_courses_for_sale_settings';
    $postdata = [
        'license_key' => $leeloolxplicense,
    ];
    $curl = new curl;
    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );
    if (!$output = $curl->post($url, $postdata, $options)) {
        return;
    }
    set_config('settingsjson', base64_encode($output), 'block_leeloo_paid_courses');
}