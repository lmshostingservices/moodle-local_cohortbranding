# Changelog - Cohort Branding

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.4] - 2026-01-07

### Fixed
- **Silenced debug logs**: Removed all normal operation debug logs (cache hit, cache set, cache purge)
- Cleaner admin logs without noise from routine branding operations

## [1.3.3] - 2025-12-23

### Fixed
- **Silenced 'no cohorts' log**: Users without cohort memberships no longer trigger debug messages
- Reduces log noise for sites with many non-cohort users

## [1.3.2] - 2025-12-23

### Fixed
- **Critical**: Added `global $CFG;` declaration before require_once in manager.php
- Fixes "Undefined variable $CFG" fatal error during Moodle upgrade

## [1.3.1] - 2025-12-23

### Fixed
- **CSS Scoping**: Secondary color heading styles now scoped to `#page-content`, `#region-main`, `.course-content` only
- No longer affects admin tables, course listings, or system menus

## [1.3.0] - 2025-12-23

### Production-Grade Release

This release implements a comprehensive improvement plan covering 12 areas:
architecture, security, performance, UX, maintainability, testing, and documentation.

### Added
- **[5️⃣ Performance]** MUC caching for user branding lookups (5-minute TTL)
- **[6️⃣ Database]** `db/caches.php` cache definition for userbranding
- **[6️⃣ Database]** `db/upgrade.php` for safe schema evolution
- **[8️⃣ Observability]** Debug logging throughout manager class
- **[9️⃣ Testing]** PHPUnit tests for manager validation methods
- **[7️⃣ Admin UX]** Enhanced language strings with `_help` text for all fields

### Changed
- **[1️⃣ Platform]** PHP requirement raised to 8.1+ for modern type safety
- **[1️⃣ Platform]** Moodle 4.0-5.x explicitly declared in version.php
- **[2️⃣ Architecture]** Manager class is now the authoritative API for all branding logic
- **[2️⃣ Architecture]** Hook class and lib.php use thin controller pattern (call manager only)
- **[3️⃣ Security]** Strict type declarations on all manager methods
- **[3️⃣ Security]** Guard clauses for invalid IDs (return null/false, no exceptions)
- **[4️⃣ CSS Safety]** All CSS generation moved to `manager::generate_css()`
- **[4️⃣ CSS Safety]** Hex colors validated and normalised to uppercase
- **[4️⃣ CSS Safety]** Font URLs restricted to trusted domains only

### Fixed
- **[8️⃣ Observability]** Silent hook failures now logged in developer mode
- **[8️⃣ Observability]** Safe fallback in cohortcss.php prevents page breakage

### Security
- All cohort IDs validated as positive integers
- Font family input sanitised against CSS injection vectors
- Logo URLs restricted to http/https protocols only
- Font URLs restricted to trusted sources (fonts.googleapis.com, use.typekit.net, etc.)

## [1.2.3] - 2025-12-22

### Changed
- Restored site-wide styling for headings, primary/secondary buttons

## [1.2.2] - 2025-12-22

### Fixed
- CSS selectors now target header/navbar only, not course content

## [1.2.1] - 2025-12-22

### Changed
- Version bump

## [1.2.0] - 2025-12-22

### Added
- Heading colors (h1-h6) use secondary color
- Secondary buttons use secondary color
- Navbar menu text uses primary color
- Menu hover uses secondary color

## [1.1.2] - 2025-12-22

### Changed
- Version bump

## [1.1.1] - 2025-12-22

### Added
- Primary buttons (.btn-primary) now use primary color

## [1.1.0] - 2025-12-22

### Changed
- Top header bar (nav#header) gets primary color background
- CSS variables: --cohort-primary, --cohort-secondary for plugins
- Logo replacement if configured

## [1.0.9] - 2025-12-22

### Changed
- Removed ALL Moodle theme styling (navbar, headings, buttons)
- CSS variables ONLY: --cohort-primary, --cohort-secondary for plugins to consume
- No interference with theme default styling

## [1.0.8] - 2025-12-22

### Changed
- Further simplified: CSS variables only + navbar background. No heading colors injected
- Plugins consume var(--cohort-primary) and var(--cohort-secondary) as needed

## [1.0.7] - 2025-12-22

### Changed
- Simplified CSS to minimal branding only: top header color, heading colors, and --cohort-primary CSS variable
- Removed excessive styling (button colors, navbar links, font styling, etc.)

## [1.0.6] - 2025-12-22

### Changed
- Version bump for release

## [1.0.5] - 2025-12-22

### Fixed
- Improved color validation and XSS protection using s() escaping
- Code cleanup and consistency improvements

## [1.0.4] - 2025-12-22

### Fixed
- Navbar/top bar now uses secondary color with high specificity selectors
- Button text color now white for visibility on colored backgrounds
- Navbar links and icons styled white for dark backgrounds

## [1.0.3] - 2025-12-22

### Fixed
- Font URL now always auto-populates when selecting a font

## [1.0.2] - 2025-12-22

### Changed
- Backward compatible with Moodle 4.0+ (legacy callback for 4.0-4.3, hook system for 4.4+)
- Uses class_exists() check to conditionally define legacy callback

### Fixed
- Hook migration pattern matching sitefont plugin
- Added PHP 8.0 version requirement

## [1.0.0] - 2025-12-22

### Added
- Initial release
- Cohort-based branding with logo, primary/secondary colors, and custom fonts
- Dynamic CSS injection (safe, fast, upgrade-proof)
- Priority system for users in multiple cohorts
- Admin UI for managing cohort brandings
- 10 built-in Google Fonts with auto-populated URLs
- Color picker with hex input
- Enable/disable toggle per branding
- Moodle 4.0 to 5.x compatibility
- Privacy API compliance (null provider)
