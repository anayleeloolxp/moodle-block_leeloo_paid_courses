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
 * Course overview block
 *
 * @package   block_leeloo_paid_courses
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/blocks/leeloo_paid_courses/locallib.php');

/**
 * Course overview block
 *
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_leeloo_paid_courses extends block_base {
    /**
     * If this is passed as mynumber then showallcourses, irrespective of limit by user.
     */
    const SHOW_ALL_COURSES = -2;

    /**
     * Block initialization
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_leeloo_paid_courses');
    }

    /**
     * Allow Multiple Instance
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Return contents of leeloo_paid_courses block
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->libdir . '/filelib.php');

        $leeloolxplicense = get_config('block_leeloo_paid_courses')->license;
        $settingsjson = get_config('block_leeloo_paid_courses')->settingsjson;
        $resposedata = json_decode(base64_decode($settingsjson));
        if( isset( $resposedata->data->courses_for_sale ) ){
            $settingleeloolxp = $resposedata->data->courses_for_sale;
        }else{
            $settingleeloolxp = new stdClass();
        }

        if (empty($settingleeloolxp->course_title)) {
            $settingleeloolxp->course_title = get_string('displayname', 'block_leeloo_paid_courses');
        }
        $this->title = $settingleeloolxp->course_title;

        if (empty($settingleeloolxp->course_cat_id)) {
            $settingleeloolxp->course_cat_id = 0;
        }

        if (empty($this->config)) {
            $this->config = new stdClass();
        }

        if (empty($this->config->courses)) {
            $this->config->courses = array();
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $updatemynumber = optional_param('mynumber', -1, PARAM_INT);
        if ($updatemynumber >= 0) {
            block_leeloo_paid_courses_update_mynumber($updatemynumber);
        }

        profile_load_custom_fields($USER);

        $showallcourses = ($updatemynumber === self::SHOW_ALL_COURSES);
        list($sortedcourses, $totalcourses) = block_leeloo_paid_courses_get_sorted_courses($showallcourses, $this->config->courses);

        $renderer = $this->page->get_renderer('block_leeloo_paid_courses');
        if (!empty($config->showwelcomearea)) {
            require_once($CFG->dirroot . '/message/lib.php');
            $msgcount = message_count_unread_messages();
            $this->content->text = $renderer->welcome_area($msgcount);
        }

        // Number of sites to display.
        if ($this->page->user_is_editing() && empty($config->forcedefaultmaxcourses)) {
            $this->content->text .= $renderer->editing_bar_head($totalcourses);
        }

        if (empty($sortedcourses)) {
            $this->content = new stdClass();
            $this->title = '';
            $this->content->text = '';
            $this->content->footer = '';

            return $this->content;
            $this->content->text .= get_string('nocourses', 'my');
        } else {
            // For each course, build category cache.
            $this->content->text .= $renderer->leeloo_paid_courses($sortedcourses, $settingleeloolxp);
        }

        return $this->content;
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Locations where block can be displayed
     *
     * @return array
     */
    public function applicable_formats() {
        return array('all' => true);
    }
    
    /**
     * Get settings from Leeloo
     */
    public function cron() {
        require_once($CFG->dirroot . '/blocks/leeloo_paid_courses/lib.php');
        updateconfpaid_courses();
    }
}
