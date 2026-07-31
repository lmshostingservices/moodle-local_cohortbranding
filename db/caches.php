<?php
/**
 * Cohort Branding - Cache definitions
 *
 * [5️⃣ Performance] MUC caching for user branding lookups
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'userbranding' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 300, // 5 minutes
        'invalidationevents' => [
            'local_cohortbranding_changed',
        ],
    ],
];
