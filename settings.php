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
 * Cohort Branding - Admin settings
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page
    $settings = new admin_settingpage('local_cohortbranding', get_string('pluginname', 'local_cohortbranding'));
    
    // Check if Central Config plugin is installed (provides site-wide credentials)
    $centralconfiginstalled = file_exists($CFG->dirroot . '/local/aiconfig/version.php');
    
    // API Credentials heading
    $settings->add(new admin_setting_heading(
        'local_cohortbranding/apicredentials',
        get_string('apicredentials', 'local_cohortbranding'),
        get_string('apicredentials_desc', 'local_cohortbranding')
    ));
    
    // Site ID (fallback if Central Config not installed)
    $settings->add(new admin_setting_configtext(
        'local_cohortbranding/siteid',
        get_string('siteid', 'local_cohortbranding'),
        get_string('siteid_desc', 'local_cohortbranding') . ($centralconfiginstalled ? ' ' . get_string('centralconfig_fallback', 'local_cohortbranding') : ''),
        '',
        PARAM_TEXT
    ));
    
    // API Key (fallback if Central Config not installed)
    $settings->add(new admin_setting_configpasswordunmask(
        'local_cohortbranding/apikey',
        get_string('apikey', 'local_cohortbranding'),
        get_string('apikey_desc', 'local_cohortbranding') . ($centralconfiginstalled ? ' ' . get_string('centralconfig_fallback', 'local_cohortbranding') : ''),
        ''
    ));
    
    $ADMIN->add('localplugins', $settings);
    
    // Add management page under appearance
    $ADMIN->add('appearance', new admin_externalpage(
        'local_cohortbranding_manage',
        get_string('pluginname', 'local_cohortbranding'),
        new moodle_url('/local/cohortbranding/index.php'),
        'local/cohortbranding:manage'
    ));
}
