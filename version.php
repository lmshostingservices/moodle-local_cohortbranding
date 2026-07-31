<?php
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
$plugin->version   = 2026072300210;          // 2026-04-10, v1.3.15
$plugin->requires  = 2022041900;           // Moodle 4.0 minimum
$plugin->supported = [400, 500];           // Moodle 4.0 to 5.x
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.18'; // FIX: unlock_verifier switched from curl_init() to Moodle \curl class so unlock API calls succeed on Moodle hosting environments. No DB schema changes.

// PHP version requirement - raised to 8.1 for modern features.
$plugin->requires_php = '8.1';
