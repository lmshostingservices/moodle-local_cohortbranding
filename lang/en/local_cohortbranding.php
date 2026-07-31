<?php
/**
 * Cohort Branding - Language strings
 *
 * [7️⃣ Admin UX] Improved wording, inline help text, warnings
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Cohort Branding';
$string['managebranding'] = 'Manage cohort branding';

// Capabilities.
$string['cohortbranding:manage'] = 'Manage cohort branding settings';
$string['cohortbranding:view'] = 'View cohort branding settings';

// Form fields.
$string['cohort'] = 'Cohort';
$string['cohort_help'] = 'Select the cohort to apply branding to. Users in this cohort will see the custom branding when they log in.';

$string['logourl'] = 'Logo URL';
$string['logourl_desc'] = 'Full URL to the custom logo image (HTTPS recommended). The logo will replace the site logo for users in this cohort.';
$string['logourl_help'] = 'Enter a secure HTTPS URL to your organisation logo. Recommended size: 200x50 pixels. Supported formats: PNG, SVG, JPG.';

$string['primarycolor'] = 'Primary colour';
$string['primarycolor_desc'] = 'Main brand colour for header bar, primary buttons, and navigation links.';
$string['primarycolor_help'] = 'This colour is used for the top navigation bar and primary action buttons. Choose a colour that provides good contrast with white text.';

$string['secondarycolor'] = 'Secondary colour';
$string['secondarycolor_desc'] = 'Secondary brand colour for headings, secondary buttons, and hover states.';
$string['secondarycolor_help'] = 'This colour is used for page headings and secondary buttons. It should complement your primary colour.';

$string['fontfamily'] = 'Font family';
$string['fontfamily_desc'] = 'Custom font for this cohort (optional). Select a Google Font or leave empty to use the theme default.';
$string['fontfamily_help'] = 'The font URL will be automatically populated when you select a font. Custom fonts are loaded from Google Fonts.';

$string['fonturl'] = 'Font URL';
$string['fonturl_desc'] = 'Google Fonts CSS URL (auto-populated when you select a font above).';
$string['fonturl_help'] = 'This should be a Google Fonts embed URL. Only trusted font sources are allowed for security.';

$string['priority'] = 'Priority';
$string['priority_desc'] = 'When a user belongs to multiple cohorts with branding, the highest priority wins (0-99999).';
$string['priority_help'] = 'If a user is in multiple cohorts with branding configured, the branding with the highest priority number will be applied. Use this to create a hierarchy of branding rules.';

$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'Enable this branding. Disabled brandings are saved but not applied.';
$string['enabled_help'] = 'Toggle this off to temporarily disable branding without deleting the configuration.';

// Actions.
$string['edit'] = 'Edit branding';
$string['delete'] = 'Delete';
$string['add'] = 'Add cohort branding';
$string['savechanges'] = 'Save changes';
$string['cancel'] = 'Cancel';
$string['confirm'] = 'Confirm';

// Confirmations and messages.
$string['confirmdelete'] = 'Are you sure you want to delete branding for this cohort? This action cannot be undone.';
$string['brandingdeleted'] = 'Cohort branding has been deleted successfully.';
$string['brandingsaved'] = 'Cohort branding has been saved successfully.';
$string['brandingcreated'] = 'Cohort branding has been created successfully.';

// Warnings and errors.
$string['nocohorts'] = 'No cohorts available. Create a cohort first before adding branding.';
$string['nobrandings'] = 'No cohort brandings have been configured yet. Click "Add cohort branding" to get started.';
$string['selectcohort'] = 'Select a cohort';
$string['cohorthasbranding'] = 'This cohort already has branding configured. Edit the existing branding instead.';
$string['invalidcohortid'] = 'Invalid cohort ID provided.';
$string['invalidcolor'] = 'Invalid colour format. Please use hex format (e.g., #FF5500).';

// Security warnings.
$string['csswarning'] = 'Warning: Custom CSS can affect site appearance. Test changes on a staging site first.';
$string['urlwarning'] = 'Only HTTPS URLs are recommended for security.';

// Privacy.
$string['privacy:metadata'] = 'The Cohort Branding plugin does not store any personal user data. Branding settings are linked to cohorts, not individual users.';

// Cache.
$string['cachedef_userbranding'] = 'Cached branding settings per user for performance.';

// Unlock verification
$string['unlock_required'] = 'This plugin requires 1000 AI credits to unlock. Please visit your AI Grader dashboard at lms-labs.com to unlock this plugin.';

// API Credentials
$string['apicredentials'] = 'API Credentials';
$string['apicredentials_desc'] = 'Enter your AI Grader credentials to enable plugin unlock verification. These credentials are available from your AI Grader dashboard at lms-labs.com.';
$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your unique Site ID from the AI Grader dashboard.';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API Key from the AI Grader dashboard.';
$string['centralconfig_fallback'] = '(Fallback - Central Config takes priority if installed)';

// CSV bulk import.
$string['csv_import'] = 'Import from CSV';
$string['csv_upload'] = 'Select CSV file';
$string['csv_upload_scrape'] = 'Upload & Scrape Sites';
$string['csv_import_howto'] = 'How bulk CSV import works:';
$string['csv_import_step1'] = 'Prepare a CSV file with a header row containing at least a <code>name</code> column';
$string['csv_import_step2'] = 'Add a <code>url</code> column — each school site is scraped for its logo and brand colour automatically';
$string['csv_import_step3'] = 'Review the preview table, adjust any colours or logos, then confirm the import';
$string['csv_import_step4'] = 'Cohorts are created automatically if they don\'t already exist; branding records are saved and ready to go';
$string['csv_import_success'] = '{$a} cohort branding record(s) created successfully.';
$string['csv_import_another'] = 'Import another CSV';
