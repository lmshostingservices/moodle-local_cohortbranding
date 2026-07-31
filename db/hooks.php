<?php
/**
 * Cohort Branding - Hook callbacks registration
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_cohortbranding\hook\before_http_headers::class . '::callback',
        'priority' => 500,
    ],
];
