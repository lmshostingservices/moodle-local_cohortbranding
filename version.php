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
 * Cohort Branding v1.3.0 - Version information
 * 
 * [1️⃣ Platform & Compatibility]
 * - Moodle 4.0-5.x support declared
 * - PHP 8.1+ baseline requirement
 * - Semantic versioning
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_cohortbranding';
$plugin->version   = 2026072300;          // 2026-04-10, v1.3.15
$plugin->requires  = 2022041900;           // Moodle 4.0 minimum
$plugin->supported = [400, 500];           // Moodle 4.0 to 5.x
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.19'; // FIX: unlock_verifier switched from curl_init() to Moodle \curl class so unlock API calls succeed on Moodle hosting environments. No DB schema changes.

// PHP version requirement - raised to 8.1 for modern features.
$plugin->requires_php = '8.1';
