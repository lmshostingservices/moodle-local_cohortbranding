<?php
/**
 * Cohort Branding - Hook callback for before_http_headers
 *
 * [2️⃣ Architecture] Hook class calls manager only (thin controller)
 * [8️⃣ Observability] Safe fallback with logging
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortbranding\hook;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callback handler for before_http_headers.
 * Used in Moodle 4.4+ which uses the new hook system.
 */
class before_http_headers {

    /**
     * Inject cohort-specific CSS before HTTP headers are sent.
     *
     * @param \core\hook\output\before_http_headers $hook The hook instance
     */
    public static function callback(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $USER;

        try {
            // [3️⃣ Security] Only for logged-in, non-guest users.
            if (!isloggedin() || isguestuser()) {
                return;
            }

            // [2️⃣ Architecture] Delegate to manager for branding check.
            require_once(__DIR__ . '/../manager.php');
            
            // Only load CSS if user has branding (uses cache).
            $branding = \local_cohortbranding\manager::get_user_branding($USER->id);
            if (!$branding) {
                return;
            }

            // Inject CSS.
            $PAGE->requires->css(
                new \moodle_url('/local/cohortbranding/cohortcss.php')
            );

            \local_cohortbranding\manager::debug_log("CSS injected via hook for user {$USER->id}");

        } catch (\Exception $e) {
            // [8️⃣ Observability] Safe fallback - log error but don't break the page.
            \local_cohortbranding\manager::debug_log("Hook error: " . $e->getMessage());
        }
    }
}
