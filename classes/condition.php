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
 * Activity self completion condition.
 *
 * @package   availability_completionself
 * @copyright 2025 Salvador Banderas Rovira <info@salvadorbanderas.eu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_completionself;

use cache;
use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Activity self completion condition.
 *
 * @package   availability_completionself
 * @copyright 2025 Salvador Banderas Rovira <info@salvadorbanderas.eu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {

    /** @var array IDs of the current module and section */
    protected $selfids;

    /** @var int Expected completion type (one of the COMPLETE_xx constants) */
    protected $expectedcompletion;

    /** @var array Array of previous cmids used to calculate relative completions */
    protected $modfastprevious = [];

    /** @var array Array of cmids previous to each course section */
    protected $sectionfastprevious = [];

    /** @var array Array of modules used in these conditions for course */
    protected static $modsusedincondition = [];

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        // Get expected completion.
        if (isset($structure->e) && in_array($structure->e,
                [COMPLETION_COMPLETE, COMPLETION_INCOMPLETE,
                COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL])) {
            $this->expectedcompletion = $structure->e;
        } else {
            throw new \coding_exception('Missing or invalid ->e for completion condition');
        }
    }

    /**
     * Saves tree data back to a structure object.
     *
     * @return stdClass Structure object (ready to be made into JSON format)
     */
    public function save(): stdClass {
        return (object) [
            'type' => 'completionself',
            'e' => $this->expectedcompletion,
        ];
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * Intended for unit testing, as normally the JSON values are constructed
     * by JavaScript code.
     *
     * @param int $cmid Course-module id of other activity
     * @param int $expectedcompletion Expected completion value (COMPLETION_xx)
     * @return stdClass Object representing condition
     */
    public static function get_json(int $expectedcompletion): stdClass {
        return (object) [
            'type' => 'completionself',
            'e' => (int)$expectedcompletion,
        ];
    }

    /**
     * Determines whether a particular item is currently available
     * according to this availability condition.
     *
     * @see \core_availability\tree_node\update_after_restore
     *
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we're checking
     * @param bool $grabthelot Performance hint: if true, caches information
     *   required for all course-modules, to make the front page and similar
     *   pages work more quickly (works only for current user)
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, info $info, $grabthelot, $userid): bool {
        list($selfcmid, $selfsectionid) = $this->get_selfids($info);
        $modinfo = $info->get_modinfo();
        $completion = new \completion_info($modinfo->get_course());
        if (!array_key_exists($selfcmid, $modinfo->cms) || $modinfo->cms[$selfcmid]->deletioninprogress) {
            // If the cmid cannot be found, always return false regardless
            // of the condition or $not state. (Will be displayed in the
            // information message.)
            $allow = false;
        } else {
            // The completion system caches its own data so no caching needed here.
            $completiondata = $completion->get_data((object)['id' => $selfcmid],
                    $grabthelot, $userid);

            $allow = true;
            if ($this->expectedcompletion == COMPLETION_COMPLETE) {
                // Complete also allows the pass state.
                switch ($completiondata->completionstate) {
                    case COMPLETION_COMPLETE:
                    case COMPLETION_COMPLETE_PASS:
                        break;
                    default:
                        $allow = false;
                }
            } else if ($this->expectedcompletion == COMPLETION_INCOMPLETE) {
                // Incomplete also allows the fail state.
                switch ($completiondata->completionstate) {
                    case COMPLETION_INCOMPLETE:
                    case COMPLETION_COMPLETE_FAIL:
                        break;
                    default:
                        $allow = false;
                }
            } else {
                // Other values require exact match.
                if ($completiondata->completionstate != $this->expectedcompletion) {
                    $allow = false;
                }
            }

            if ($not) {
                $allow = !$allow;
            }
        }

        return $allow;
    }

    /**
     * Return current item IDs (cmid and sectionid).
     *
     * @param info $info
     * @return int[] with [0] => cmid/null, [1] => sectionid/null
     */
    public function get_selfids(info $info): array {
        if (isset($this->selfids)) {
            return $this->selfids;
        }
        if ($info instanceof info_module) {
            $cminfo = $info->get_course_module();
            if (!empty($cminfo->id)) {
                $this->selfids = [$cminfo->id, null];
                return $this->selfids;
            }
        }
        if ($info instanceof info_section) {
            $section = $info->get_section();
            if (!empty($section->id)) {
                $this->selfids = [null, $section->id];
                return $this->selfids;
            }

        }
        return [null, null];
    }

    /**
     * Returns a more readable keyword corresponding to a completion state.
     *
     * Used to make lang strings easier to read.
     *
     * @param int $completionstate COMPLETION_xx constant
     * @return string Readable keyword
     */
    protected static function get_lang_string_keyword(int $completionstate): string {
        switch($completionstate) {
            case COMPLETION_INCOMPLETE:
                return 'incomplete';
            case COMPLETION_COMPLETE:
                return 'complete';
            case COMPLETION_COMPLETE_PASS:
                return 'complete_pass';
            case COMPLETION_COMPLETE_FAIL:
                return 'complete_fail';
            default:
                throw new \coding_exception('Unexpected completion state: ' . $completionstate);
        }
    }

    /**
     * Obtains a string describing this restriction (whether or not
     * it actually applies).
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we're checking
     * @return string Information string (for admin) about all restrictions on
     *   this item
     */
    public function get_description($full, $not, info $info): string {
        $str = 'requires_';


        // Work out which lang string to use depending on required completion status.
        if ($not) {
            // Convert NOT strings to use the equivalent where possible.
            switch ($this->expectedcompletion) {
                case COMPLETION_INCOMPLETE:
                    $str .= self::get_lang_string_keyword(COMPLETION_COMPLETE);
                    break;
                case COMPLETION_COMPLETE:
                    $str .= self::get_lang_string_keyword(COMPLETION_INCOMPLETE);
                    break;
                default:
                    // The other two cases do not have direct opposites.
                    $str .= 'not_' . self::get_lang_string_keyword($this->expectedcompletion);
                    break;
            }
        } else {
            $str .= self::get_lang_string_keyword($this->expectedcompletion);
        }

        return get_string($str, 'availability_completionself');
    }

    /**
     * Obtains a representation of the options of this condition as a string,
     * for debugging.
     *
     * @return string Text representation of parameters
     */
    protected function get_debug_string(): string {
        switch ($this->expectedcompletion) {
            case COMPLETION_COMPLETE :
                $type = 'COMPLETE';
                break;
            case COMPLETION_INCOMPLETE :
                $type = 'INCOMPLETE';
                break;
            case COMPLETION_COMPLETE_PASS:
                $type = 'COMPLETE_PASS';
                break;
            case COMPLETION_COMPLETE_FAIL:
                $type = 'COMPLETE_FAIL';
                break;
            default:
                throw new \coding_exception('Unexpected expected completion');
        }
        return $type;
    }

    /**
     * Updates this node after restore, returning true if anything changed.
     *
     * @see \core_availability\tree_node\update_after_restore
     *
     * @param string $restoreid Restore ID
     * @param int $courseid ID of target course
     * @param \base_logger $logger Logger for any warnings
     * @param string $name Name of this item (for use in warning messages)
     * @return bool True if there was any change
     */
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name): bool {
        return false;
    }

    /**
     * Used in course/lib.php because we need to disable the completion JS if
     * a completion value affects a conditional activity.
     *
     * @param \stdClass $course Moodle course object
     * @param int $cmid Course-module id
     * @return bool True if this is used in a condition, false otherwise
     */
    public static function completion_value_used($course, $cmid): bool {
        // Have we already worked out a list of required completion values
        // for this course? If so just use that.
        if (!array_key_exists($course->id, self::$modsusedincondition)) {
            // We don't have data for this course, build it.
            $modinfo = get_fast_modinfo($course);
            self::$modsusedincondition[$course->id] = [];

            // Activities.
            foreach ($modinfo->cms as $othercm) {
                if (is_null($othercm->availability)) {
                    continue;
                }
                $ci = new \core_availability\info_module($othercm);
                $tree = $ci->get_availability_tree();
                foreach ($tree->get_all_children('availability_completionself\condition') as $cond) {
                    self::$modsusedincondition[$course->id][$othercm->id] = true;
                }
            }

            // Sections.
            foreach ($modinfo->get_section_info_all() as $section) {
                if (is_null($section->availability)) {
                    continue;
                }
                $ci = new \core_availability\info_section($section);
                $tree = $ci->get_availability_tree();
                foreach ($tree->get_all_children('availability_completionself\condition') as $cond) {
                    // This condition applies to the module itself, so we don't need to do anything here.
                }
            }
        }
        return array_key_exists($cmid, self::$modsusedincondition[$course->id]);
    }

    /**
     * Wipes the static cache of modules used in a condition (for unit testing).
     */
    public static function wipe_static_cache() {
        self::$modsusedincondition = [];
    }

    public function update_dependency_id($table, $oldid, $newid) {
        return false;
    }
}
