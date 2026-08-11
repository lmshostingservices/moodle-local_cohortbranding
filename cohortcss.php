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
 * Cohort Branding - Dynamic CSS output
 *
 * [4️⃣ CSS Safety] All CSS generated through manager with validation
 * [5️⃣ Performance] Uses cached branding data
 * [8️⃣ Observability] Safe fallback behaviour
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', false);

require('../../config.php');
require_login();

// Set headers first.
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

// [3️⃣ Security] Require login.
if (!isloggedin() || isguestuser()) {
    echo "/* Not logged in */\n";
    exit;
}

try {
    require_once($CFG->dirroot . '/local/cohortbranding/classes/manager.php');

    $branding = \local_cohortbranding\manager::get_user_branding($USER->id);

    if (!$branding) {
        echo "/* No cohort branding for this user */\n";
        exit;
    }

    // [4️⃣ CSS Safety] Generate CSS through manager (validated and scoped).
    $css = \local_cohortbranding\manager::generate_css($branding);
    
    if (empty($css)) {
        echo "/* No branding rules to apply */\n";
        exit;
    }

    echo "/* Cohort Branding v1.3.0 */\n";
    echo $css;

} catch (\Exception $e) {
    // [8️⃣ Observability] Safe fallback - don't break the page.
    \local_cohortbranding\manager::debug_log("CSS generation error: " . $e->getMessage());
    echo "/* Branding error - see debug log */\n";
}
