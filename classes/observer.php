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

namespace local_cohortbranding;

/**
 * Keeps the per-user branding cache in step with cohort membership.
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A user joined or left a cohort: forget their cached branding.
     *
     * @param \core\event\base $event
     */
    public static function cohort_member_changed(\core\event\base $event): void {
        if (!empty($event->relateduserid)) {
            manager::invalidate_cache((int)$event->relateduserid);
        }
    }

    /**
     * A cohort was deleted: drop its branding row and every cached lookup.
     *
     * @param \core\event\cohort_deleted $event
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        global $DB;
        $DB->delete_records('local_cohortbranding', ['cohortid' => $event->objectid]);
        manager::invalidate_cache();
    }
}
