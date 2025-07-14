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
 * Front-end class.
 *
 * @package   availability_completionself
 * @copyright 2025 Salvador Banderas Rovira <info@salvadorbanderas.eu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_completionself;

defined('MOODLE_INTERNAL') || die();

/**
 * Front-end class.
 *
 * @package   availability_completionself
 * @copyright 2025 Salvador Banderas Rovira <info@salvadorbanderas.eu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * @var array Cached init parameters
     */
    protected $cacheparams = [];

    /**
     * @var string IDs of course, cm, and section for cache (if any)
     */
    protected $cachekey = '';

    protected function get_javascript_strings() {
        return ['option_complete', 'option_fail', 'option_incomplete', 'option_pass', 'label_completion'];
    }

    protected function get_javascript_init_params($course, \cm_info $cm = null,
            \section_info $section = null) {
        return [];
    }

    protected function allow_add($course, \cm_info $cm = null,
            \section_info $section = null) {
        global $CFG;

        // Check if completion is enabled for the course and activity.
        require_once($CFG->libdir . '/completionlib.php');
        $info = new \completion_info($course);
        if (!$info->is_enabled($cm)) {
            return false;
        }

        return true;
    }
}
