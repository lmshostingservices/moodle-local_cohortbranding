<?php
/**
 * Cohort Branding - Library functions
 *
 * [2️⃣ Architecture] Thin controller pattern for legacy callbacks
 * [8️⃣ Observability] Safe fallback with logging
 *
 * This file provides the legacy callback for Moodle 4.0-4.3 only.
 * Moodle 4.4+ uses the hook system defined in db/hooks.php.
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only define the legacy callback if the new hook system doesn't exist.
// This prevents the deprecation warning in Moodle 4.4+.
if (!class_exists('\core\hook\output\before_http_headers')) {

    /**
     * Inject cohort-specific CSS before HTTP headers are sent.
     * Legacy callback for Moodle 4.0-4.3 only.
     */
    function local_cohortbranding_before_http_headers(): void {
        global $PAGE, $USER, $CFG;

        try {
            // [3️⃣ Security] Only for logged-in, non-guest users.
            if (!isloggedin() || isguestuser()) {
                return;
            }

            // [2️⃣ Architecture] Delegate to manager.
            require_once($CFG->dirroot . '/local/cohortbranding/classes/manager.php');
            
            // Only load CSS if user has branding (uses cache).
            $branding = \local_cohortbranding\manager::get_user_branding($USER->id);
            if (!$branding) {
                return;
            }

            $PAGE->requires->css(
                new \moodle_url('/local/cohortbranding/cohortcss.php')
            );

            \local_cohortbranding\manager::debug_log("CSS injected via legacy hook for user {$USER->id}");

        } catch (\Exception $e) {
            // [8️⃣ Observability] Safe fallback - log error but don't break the page.
            if (class_exists('\local_cohortbranding\manager')) {
                \local_cohortbranding\manager::debug_log("Legacy hook error: " . $e->getMessage());
            }
        }
    }
}

/**
 * Add navigation node to admin menu.
 *
 * @param global_navigation $nav The navigation node to add to
 */
function local_cohortbranding_extend_navigation(global_navigation $nav): void {
    // No navigation additions needed.
}

/**
 * Extend settings navigation.
 *
 * @param settings_navigation $nav The settings navigation object
 * @param context $context The context
 */
function local_cohortbranding_extend_settings_navigation(settings_navigation $nav, context $context): void {
    // Settings are added via settings.php.
}
