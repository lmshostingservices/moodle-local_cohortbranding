<?php
/**
 * Cohort Branding - Database upgrade steps
 *
 * [6️⃣ Database] Safe upgrade path for schema evolution
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_cohortbranding plugin.
 *
 * @param int $oldversion The version we are upgrading from
 * @return bool Always returns true
 */
function xmldb_local_cohortbranding_upgrade(int $oldversion): bool {
    global $DB;
    
    $dbman = $DB->get_manager();

    // Version 1.3.0 upgrade - no schema changes, just cache purge.
    if ($oldversion < 2025122300) {
        // Purge any stale caches from previous versions.
        cache_helper::purge_by_definition('local_cohortbranding', 'userbranding');
        
        upgrade_plugin_savepoint(true, 2025122300, 'local', 'cohortbranding');
    }

    // v1.3.13: SYNC FIX — version.php was bumped to 202603070101 through several releases
    //   without a corresponding upgrade.php savepoint being added. Added retroactively
    //   to bring the DB version in sync with version.php. No DB schema changes.
    if ($oldversion < 202603070101) {
        upgrade_plugin_savepoint(true, 202603070101, 'local', 'cohortbranding');
    }

    // v1.3.14: CSV bulk import with auto-scraping.
    //   New page csv_import.php: accepts CSV of school names + website URLs, scrapes each
    //   site for logo URL (apple-touch-icon, og:image, icon links) and primary brand colour
    //   (meta theme-color, CSS variables, header/nav CSS fallback). Preview table with colour
    //   pickers and logo thumbnails before confirming. Auto-creates Moodle cohorts that don't
    //   exist and bulk-creates branding records. Includes sample CSV with 7 example schools.
    //   "Import from CSV" button added to index.php. No DB schema changes.
    if ($oldversion < 202603070102) {
        upgrade_plugin_savepoint(true, 202603070102, 'local', 'cohortbranding');
    }

    // v1.3.15: FIX — unlock_verifier.php switched from raw PHP curl_init() to Moodle's \curl
    // class (require_once $CFG->libdir/filelib.php). Raw curl_init() bypassed Moodle's SSL
    // cert bundle, causing silent API call failures on Moodle hosting environments.
    // Moodle \curl uses the correct CA bundle and respects proxy settings.
    // No DB schema changes. version.php → 2026041000315.
    if ($oldversion < 2026041000315) {
        upgrade_plugin_savepoint(true, 2026041000315, 'local', 'cohortbranding');
    }

    if ($oldversion < 2026072300208) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026072300208, 'local', 'cohortbranding');
    }

    if ($oldversion < 2026072300209) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300209, 'local', 'cohortbranding');
    }

    if ($oldversion < 2026072300210) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300210, 'local', 'cohortbranding');
    }

    return true;
}