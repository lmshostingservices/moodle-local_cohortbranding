<p align="center">
  <a href="https://lmshostingservices.com">
    <img src="https://raw.githubusercontent.com/lmshostingservices/lms-labs/main/attached_assets/lms-hosting-logo.png" alt="LMS Hosting Services" height="60">
  </a>
</p>

> **LMS Labs** is the Moodle plugin division of [LMS Hosting Services](https://lmshostingservices.com) — Australia's Moodle™ Certified Partner.

---

# Cohort Branding for Moodle

**Version:** 1.3.0 (Production-Grade)  
**Requires:** Moodle 4.0+, PHP 8.1+  
**License:** GNU GPL v3  

Apply custom branding (logo, colors, fonts) to specific cohorts in Moodle. Users in branded cohorts see their organisation's colors automatically.

## Features

- **Per-cohort branding** - Each cohort can have its own logo, colors, and fonts
- **Priority system** - When users belong to multiple cohorts, highest priority wins
- **CSS variables** - Plugins can use `var(--cohort-primary)` and `var(--cohort-secondary)`
- **Google Fonts integration** - 10 pre-configured fonts with auto-populated URLs
- **Enable/disable toggle** - Temporarily disable branding without deleting
- **Safe CSS injection** - Dynamic, validated CSS that won't break your theme

## Installation

1. Download and extract to `local/cohortbranding/`
2. Navigate to Site administration > Notifications
3. Complete the installation process
4. Configure via Site administration > Plugins > Local plugins > Cohort Branding

## Usage

1. Create cohorts in Site administration > Users > Cohorts
2. Add branding via the Cohort Branding admin page
3. Assign users to cohorts
4. Users in branded cohorts will see custom styling

## Architecture

```
local_cohortbranding/
├── classes/
│   ├── manager.php           # Core service class (all logic here)
│   ├── hook/
│   │   └── before_http_headers.php  # Moodle 4.4+ hook handler
│   └── privacy/
│       └── provider.php      # Privacy API compliance
├── db/
│   ├── access.php            # Capabilities
│   ├── caches.php            # MUC cache definitions
│   ├── hooks.php             # Hook registration (Moodle 4.4+)
│   ├── install.xml           # Database schema
│   └── upgrade.php           # Upgrade steps
├── lang/en/
│   └── local_cohortbranding.php  # Language strings
├── tests/
│   └── manager_test.php      # PHPUnit tests
├── cohortcss.php             # Dynamic CSS endpoint
├── edit.php                  # Add/edit branding form
├── index.php                 # Admin listing page
├── lib.php                   # Legacy callbacks (Moodle 4.0-4.3)
├── settings.php              # Admin settings link
└── version.php               # Version information
```

### Design Decisions

1. **Manager Service Class** - All logic centralised in `classes/manager.php`
2. **Thin Controllers** - `edit.php`, `index.php`, hook classes only call manager
3. **MUC Caching** - User branding cached for 5 minutes to reduce DB hits
4. **Safe Fallbacks** - Errors are logged, never break pages
5. **Strict Validation** - All inputs validated before use

## Hook Behaviour

### Moodle 4.4+ (Hook System)
- Registered in `db/hooks.php`
- Handler: `local_cohortbranding\hook\before_http_headers`

### Moodle 4.0-4.3 (Legacy Callbacks)
- Defined in `lib.php`
- Function: `local_cohortbranding_before_http_headers()`

Both paths inject CSS via `$PAGE->requires->css()`.

## CSS Variables

The plugin exposes CSS variables for other plugins to consume:

```css
:root {
    --cohort-primary: #FF5500;
    --cohort-secondary: #333333;
}
```

Use in your theme or plugin:
```css
.my-element {
    background-color: var(--cohort-primary);
}
```

## Security

- All cohort IDs validated as positive integers
- Hex colors validated with regex and normalised
- Font URLs restricted to trusted sources only
- Logo URLs restricted to http/https protocols
- Font family names sanitised against CSS injection
- Capability checks at every entry point

### Trusted Font Sources

- fonts.googleapis.com
- fonts.gstatic.com  
- use.typekit.net
- fast.fonts.net

## Performance

- User branding cached in MUC (5-minute TTL)
- Cache invalidated on branding save/delete
- CSS only loaded for users with active branding
- Minimal DB queries per page view

## Testing

Run PHPUnit tests:
```bash
php vendor/bin/phpunit local/cohortbranding/tests/manager_test.php
```

## Upgrade Notes

### From 1.2.x to 1.3.0

- PHP 8.1+ now required
- Cache definitions added - purges automatically on upgrade
- No database schema changes
- No breaking API changes

## Privacy

This plugin does not store personal data. Branding settings are linked to cohorts, not individual users. Full Privacy API compliance with null provider.


## ⭐ Why this plugin is unlike anything else available

**Per-cohort visual identity from a single Moodle instance**

- Serving multiple client organisations with a branded portal traditionally requires a separate Moodle instance per client — separate hosting, maintenance, upgrades, and licences. Cohort Branding delivers a visually distinct portal experience for each client group using their cohort membership as the trigger, from a single shared Moodle instance.
- Brand elements — logo, header colour, site name, CSS overrides — are stored per cohort and applied at page render time. A learner in the 'Acme Corp' cohort sees the Acme logo; a learner in 'City Council' sees the City Council logo. Both are on the same Moodle site.
- Survives Moodle upgrades. Branding is applied via plugin hook, not theme file edits. Theme updates do not reset custom branding.

## Support

- **Portal:** [lms-labs.com](https://lms-labs.com)
- **Email:** support@lmshostingservices.com
- **Website:** [lmshostingservices.com](https://lmshostingservices.com)

LMS Labs is the plugin division of LMS Hosting Services, Australia's Moodle™ Certified Partner.

## Pricing

**$50 USD** — one-time purchase per site · lifetime updates · no subscription.

Download at [lms-labs.com/plugins](https://lms-labs.com/plugins).

## License

GNU General Public License v3 or later
https://www.gnu.org/copyleft/gpl.html
