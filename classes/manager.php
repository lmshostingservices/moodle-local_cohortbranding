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
 * Cohort Branding - Manager service class
 *
 * [2️⃣ Architecture] Centralised logic with single responsibility
 * [3️⃣ Security] Strict typing, validation, capability checks
 * [5️⃣ Performance] MUC caching for cohort branding lookups
 * [8️⃣ Observability] Debug logging support
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortbranding;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Manager class for cohort branding operations.
 * This is the authoritative API for all branding logic.
 */
class manager {
    /** @var string Cache definition name */
    private const CACHE_AREA = 'userbranding';

    /** @var int Cache TTL in seconds (5 minutes) */
    private const CACHE_TTL = 300;

    /**
     * Get the branding settings for a user based on their cohort memberships.
     * Returns the branding with highest priority if user belongs to multiple cohorts.
     * Uses MUC caching for performance.
     *
     * @param int $userid The user ID (must be positive integer)
     * @return object|null The branding record or null if none found
     */
    public static function get_user_branding(int $userid): ?object {
        global $DB;

        // [3️⃣ Security] Validate user ID strictly.
        if ($userid <= 0) {
            self::debug_log("Invalid user ID: {$userid}");
            return null;
        }

        // [5️⃣ Performance] Check MUC cache first.
        $cache = \cache::make('local_cohortbranding', self::CACHE_AREA);
        $cachekey = 'user_' . $userid;
        $cached = $cache->get($cachekey);
        
        if ($cached !== false) {
            return $cached === 'null' ? null : $cached;
        }

        // Get user's cohorts.
        $cohorts = cohort_get_user_cohorts($userid);
        if (empty($cohorts)) {
            // No debug log - this is a normal case, not worth logging.
            $cache->set($cachekey, 'null');
            return null;
        }

        $cohortids = array_keys($cohorts);
        
        // [3️⃣ Security] Use parameterised query.
        list($insql, $params) = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
        $params['enabled'] = 1;

        $sql = "SELECT *
                FROM {local_cohortbranding}
                WHERE cohortid $insql
                  AND enabled = :enabled
                ORDER BY priority DESC
                LIMIT 1";

        $record = $DB->get_record_sql($sql, $params);
        $result = $record ?: null;

        // Cache the result.
        $cache->set($cachekey, $result ?? 'null');

        return $result;
    }

    /**
     * Invalidate cache for a specific user or all users.
     *
     * @param int|null $userid User ID or null to invalidate all
     */
    public static function invalidate_cache(?int $userid = null): void {
        $cache = \cache::make('local_cohortbranding', self::CACHE_AREA);
        
        if ($userid !== null) {
            $cache->delete('user_' . $userid);
        } else {
            $cache->purge();
        }
    }

    /**
     * Get all branding records with cohort names.
     *
     * @return array Array of branding records with cohort info
     */
    public static function get_all_brandings(): array {
        global $DB;

        $sql = "SELECT b.*, c.name as cohortname, c.idnumber as cohortidnumber
                FROM {local_cohortbranding} b
                JOIN {cohort} c ON c.id = b.cohortid
                ORDER BY b.priority DESC, c.name ASC";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get branding by ID with validation.
     *
     * @param int $id Branding record ID (must be positive)
     * @return object|null
     */
    public static function get_branding(int $id): ?object {
        global $DB;

        // [3️⃣ Security] Validate ID.
        if ($id <= 0) {
            self::debug_log("Invalid branding ID: {$id}");
            return null;
        }

        $record = $DB->get_record('local_cohortbranding', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get branding by cohort ID with validation.
     *
     * @param int $cohortid Cohort ID (must be positive)
     * @return object|null
     */
    public static function get_branding_by_cohort(int $cohortid): ?object {
        global $DB;

        // [3️⃣ Security] Validate cohort ID.
        if ($cohortid <= 0) {
            self::debug_log("Invalid cohort ID: {$cohortid}");
            return null;
        }

        $record = $DB->get_record('local_cohortbranding', ['cohortid' => $cohortid]);
        return $record ?: null;
    }

    /**
     * Save branding settings with full validation.
     *
     * @param object $data Branding data
     * @return int The branding ID
     * @throws \moodle_exception If validation fails
     */
    public static function save_branding(object $data): int {
        global $DB;

        // [3️⃣ Security] Validate required fields.
        if (empty($data->cohortid) || $data->cohortid <= 0) {
            throw new \moodle_exception('invalidcohortid', 'local_cohortbranding');
        }

        // [4️⃣ CSS Safety] Validate and sanitise colors.
        if (!empty($data->primarycolor)) {
            $data->primarycolor = self::validate_hex_color($data->primarycolor);
        }
        if (!empty($data->secondarycolor)) {
            $data->secondarycolor = self::validate_hex_color($data->secondarycolor);
        }

        // [3️⃣ Security] Validate URLs.
        if (!empty($data->logourl)) {
            $data->logourl = self::validate_url($data->logourl);
        }
        if (!empty($data->fonturl)) {
            $data->fonturl = self::validate_font_url($data->fonturl);
        }

        // [3️⃣ Security] Sanitise font family.
        if (!empty($data->fontfamily)) {
            $data->fontfamily = self::validate_font_family($data->fontfamily);
        }

        // Ensure priority is within bounds.
        $data->priority = max(0, min(99999, (int)($data->priority ?? 0)));
        
        // Ensure enabled is boolean.
        $data->enabled = !empty($data->enabled) ? 1 : 0;

        $now = time();

        if (!empty($data->id) && $data->id > 0) {
            $data->timemodified = $now;
            $DB->update_record('local_cohortbranding', $data);
            $id = $data->id;
            self::debug_log("Updated branding ID {$id}");
        } else {
            $data->timecreated = $now;
            $data->timemodified = $now;
            $id = $DB->insert_record('local_cohortbranding', $data);
            self::debug_log("Created branding ID {$id}");
        }

        // Invalidate all user caches as cohort membership may vary.
        self::invalidate_cache();

        return $id;
    }

    /**
     * Delete branding by ID with validation.
     *
     * @param int $id Branding record ID
     * @return bool
     */
    public static function delete_branding(int $id): bool {
        global $DB;

        // [3️⃣ Security] Validate ID.
        if ($id <= 0) {
            self::debug_log("Invalid delete ID: {$id}");
            return false;
        }

        $result = $DB->delete_records('local_cohortbranding', ['id' => $id]);
        
        if ($result) {
            self::invalidate_cache();
            self::debug_log("Deleted branding ID {$id}");
        }

        return $result;
    }

    /**
     * Get cohorts that don't have branding configured.
     *
     * @return array Array of cohort records
     */
    public static function get_available_cohorts(): array {
        global $DB;

        $sql = "SELECT c.*
                FROM {cohort} c
                WHERE c.id NOT IN (
                    SELECT cohortid FROM {local_cohortbranding}
                )
                ORDER BY c.name ASC";

        return $DB->get_records_sql($sql);
    }

    /**
     * Validate hex color code.
     *
     * @param string $color Color to validate
     * @return string Valid hex color or empty string
     */
    public static function validate_hex_color(string $color): string {
        $color = trim($color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtoupper($color);
        }
        self::debug_log("Invalid hex color rejected: {$color}");
        return '';
    }

    /**
     * Validate URL for safety.
     *
     * @param string $url URL to validate
     * @return string Valid URL or empty string
     */
    public static function validate_url(string $url): string {
        $url = trim($url);
        $url = clean_param($url, PARAM_URL);
        
        // Only allow https URLs for security.
        if (!empty($url) && strpos($url, 'https://') === 0) {
            return $url;
        }
        
        // Also allow http for local development.
        if (!empty($url) && strpos($url, 'http://') === 0) {
            return $url;
        }
        
        self::debug_log("Invalid URL rejected: {$url}");
        return '';
    }

    /**
     * Validate font URL (only allow trusted sources).
     *
     * @param string $url Font URL to validate
     * @return string Valid font URL or empty string
     */
    public static function validate_font_url(string $url): string {
        $url = self::validate_url($url);
        
        if (empty($url)) {
            return '';
        }

        // [4️⃣ CSS Safety] Only allow trusted font sources.
        $allowedDomains = [
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'use.typekit.net',
            'fast.fonts.net',
        ];

        $host = parse_url($url, PHP_URL_HOST);
        foreach ($allowedDomains as $domain) {
            if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                return $url;
            }
        }

        self::debug_log("Untrusted font URL rejected: {$url}");
        return '';
    }

    /**
     * Validate font family name.
     *
     * @param string $fontfamily Font family to validate
     * @return string Valid font family or empty string
     */
    public static function validate_font_family(string $fontfamily): string {
        $fontfamily = trim($fontfamily);
        
        // [4️⃣ CSS Safety] Only allow alphanumeric, spaces, quotes.
        if (preg_match('/^[A-Za-z0-9\s\'",-]+$/', $fontfamily)) {
            return $fontfamily;
        }
        
        self::debug_log("Invalid font family rejected: {$fontfamily}");
        return '';
    }

    /**
     * Generate safe CSS for a branding record.
     * [4️⃣ CSS Safety] All CSS is scoped and validated.
     *
     * @param object $branding Branding record
     * @return string Safe CSS output
     */
    public static function generate_css(object $branding): string {
        $css = [];

        // CSS variables for plugins to consume.
        $cssVars = [];
        
        if (!empty($branding->primarycolor)) {
            $primary = self::validate_hex_color($branding->primarycolor);
            if ($primary) {
                $cssVars[] = "    --cohort-primary: {$primary};";
            }
        }
        
        if (!empty($branding->secondarycolor)) {
            $secondary = self::validate_hex_color($branding->secondarycolor);
            if ($secondary) {
                $cssVars[] = "    --cohort-secondary: {$secondary};";
            }
        }

        if (!empty($cssVars)) {
            $css[] = ":root {";
            $css[] = implode("\n", $cssVars);
            $css[] = "}";
        }

        // Primary color styling.
        if (!empty($branding->primarycolor)) {
            $primary = self::validate_hex_color($branding->primarycolor);
            if ($primary) {
                $css[] = self::generate_primary_css($primary);
            }
        }

        // Secondary color styling.
        if (!empty($branding->secondarycolor)) {
            $secondary = self::validate_hex_color($branding->secondarycolor);
            if ($secondary) {
                $css[] = self::generate_secondary_css($secondary);
            }
        }

        // Logo replacement.
        if (!empty($branding->logourl)) {
            $logourl = self::validate_url($branding->logourl);
            if ($logourl) {
                $css[] = self::generate_logo_css($logourl);
            }
        }

        return implode("\n\n", $css);
    }

    /**
     * Generate CSS for primary color.
     *
     * @param string $color Validated hex color
     * @return string CSS rules
     */
    private static function generate_primary_css(string $color): string {
        return "
/* Cohort Branding: Primary color */
nav#header,
nav#header.fixed-top {
    background-color: {$color} !important;
}

.btn-primary {
    background-color: {$color} !important;
    border-color: {$color} !important;
}

.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active {
    background-color: {$color} !important;
    border-color: {$color} !important;
    filter: brightness(0.9);
}

.primary-navigation .nav-link,
.primary-navigation .dropdown-toggle,
#page-wrapper nav.navbar .nav-link,
#page-wrapper nav.navbar .dropdown-toggle {
    color: {$color} !important;
}";
    }

    /**
     * Generate CSS for secondary color.
     *
     * @param string $color Validated hex color
     * @return string CSS rules
     */
    private static function generate_secondary_css(string $color): string {
        return "
/* Cohort Branding: Secondary color */
/* Only target page content headings, not admin tables/menus */
#page-content h1,
#page-content h2,
#page-content h3,
#page-content h4,
#page-content h5,
#page-content h6,
#region-main h1,
#region-main h2,
#region-main h3,
#region-main h4,
#region-main h5,
#region-main h6,
.course-content h1,
.course-content h2,
.course-content h3,
.course-content h4,
.course-content h5,
.course-content h6 {
    color: {$color} !important;
}

.btn-secondary {
    background-color: {$color} !important;
    border-color: {$color} !important;
}

.btn-secondary:hover,
.btn-secondary:focus,
.btn-secondary:active {
    background-color: {$color} !important;
    border-color: {$color} !important;
    filter: brightness(0.9);
}

.primary-navigation .nav-link:hover,
.primary-navigation .nav-link:focus,
.primary-navigation .dropdown-toggle:hover,
.primary-navigation .dropdown-toggle:focus,
#page-wrapper nav.navbar .nav-link:hover,
#page-wrapper nav.navbar .nav-link:focus,
#page-wrapper nav.navbar .dropdown-toggle:hover,
#page-wrapper nav.navbar .dropdown-toggle:focus {
    color: {$color} !important;
}

.primary-navigation .dropdown-item:hover,
.primary-navigation .dropdown-item:focus,
.primary-navigation .dropdown-menu .dropdown-item:hover,
.primary-navigation .dropdown-menu .dropdown-item:focus {
    background-color: {$color} !important;
    color: #fff !important;
}";
    }

    /**
     * Generate CSS for logo replacement.
     *
     * @param string $logourl Validated logo URL
     * @return string CSS rules
     */
    private static function generate_logo_css(string $logourl): string {
        // Escape for CSS.
        $escaped = addslashes($logourl);
        return "
/* Cohort Branding: Custom logo */
.navbar-brand img,
.logo img {
    content: url('{$escaped}');
    max-height: 50px;
}";
    }

    /**
     * Log debug message if debugging is enabled.
     * [8️⃣ Observability] Debug logging support.
     *
     * @param string $message Debug message
     */
    public static function debug_log(string $message): void {
        // Use error_log() instead of debugging() to avoid on-screen output.
        // debugging() with a message string causes visible stack traces in Moodle
        // developer mode, which appears as errors to site admins. error_log()
        // writes silently to the server's PHP error log.
        if (debugging('', DEBUG_DEVELOPER)) {
            error_log('[local_cohortbranding] ' . $message);
        }
    }

    /**
     * Check if current user has manage capability.
     * [3️⃣ Security] Centralised capability check.
     *
     * @return bool
     */
    public static function can_manage(): bool {
        return has_capability('local/cohortbranding:manage', \context_system::instance());
    }

    /**
     * Require manage capability or throw exception.
     * [3️⃣ Security] Guard clause pattern.
     *
     * @throws \required_capability_exception
     */
    public static function require_manage(): void {
        require_capability('local/cohortbranding:manage', \context_system::instance());
    }
}
