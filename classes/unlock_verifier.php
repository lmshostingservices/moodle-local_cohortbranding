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
 * Plugin unlock verification for AI Grader Time Saving Plugins.
 * Auto-unlocks when valid credentials and sufficient credits are available.
 *
 * @package    local_cohortbranding
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortbranding;

defined('MOODLE_INTERNAL') || die();

class unlock_verifier {
    
    const PLUGIN_ID = 'cohortbranding';
    const CREDITS_REQUIRED = 1000;
    const API_URL = 'https://lms-labs.com/api/plugin-unlock/verify';
    const UNLOCK_URL = 'https://lms-labs.com/api/plugin-unlock';
    const CACHE_KEY = 'local_cohortbranding_unlocked';
    const CACHE_DURATION = 3600;
    
    public static function is_unlocked(): bool {
        $cache = \cache::make('core', 'config');
        $cached = $cache->get(self::CACHE_KEY);
        
        if ($cached !== false && is_array($cached)) {
            if (isset($cached['expires']) && $cached['expires'] > time()) {
                return !empty($cached['unlocked']);
            }
        }
        
        $result = self::verify_with_api();
        
        if (!$result['unlocked'] && $result['has_credentials'] && $result['has_credits']) {
            $autoUnlockResult = self::auto_unlock();
            if ($autoUnlockResult) {
                $result['unlocked'] = true;
            }
        }
        
        $cache->set(self::CACHE_KEY, [
            'unlocked' => $result['unlocked'],
            'expires' => time() + self::CACHE_DURATION,
        ]);
        
        return $result['unlocked'];
    }
    
    public static function clear_cache(): void {
        $cache = \cache::make('core', 'config');
        $cache->delete(self::CACHE_KEY);
    }
    
    private static function verify_with_api(): array {
        global $CFG;
        $credentials = self::get_credentials();
        
        if (empty($credentials['siteid']) || empty($credentials['apikey'])) {
            return ['unlocked' => false, 'has_credentials' => false, 'has_credits' => false];
        }
        
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);

        $response = $curl->get(self::API_URL, [
            'pluginId' => self::PLUGIN_ID,
            'siteId'   => $credentials['siteid'],
            'apiKey'   => $credentials['apikey'],
        ]);
        $httpcode = (int)($curl->info['http_code'] ?? 0);
        
        if ($httpcode !== 200 || empty($response)) {
            // SECURITY: Fail-closed - deny access if API unreachable
            return ['unlocked' => false, 'has_credentials' => true, 'has_credits' => false];
        }
        
        $data = json_decode($response, true);
        $credits = isset($data['credits']) ? (int)$data['credits'] : 0;
        
        return [
            'unlocked' => !empty($data['unlocked']),
            'has_credentials' => true,
            'has_credits' => ($credits >= self::CREDITS_REQUIRED) || ($credits === -1),
        ];
    }
    
    private static function auto_unlock(): bool {
        global $CFG;
        $credentials = self::get_credentials();
        
        if (empty($credentials['siteid']) || empty($credentials['apikey'])) {
            return false;
        }
        
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT'        => 15,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_HTTPHEADER'     => ['Content-Type: application/json', 'Accept: application/json'],
        ]);

        $postdata = json_encode([
            'pluginId' => self::PLUGIN_ID,
            'siteId'   => $credentials['siteid'],
            'apiKey'   => $credentials['apikey'],
        ]);

        $response = $curl->post(self::UNLOCK_URL, $postdata);
        $httpcode  = (int)($curl->info['http_code'] ?? 0);
        
        if ($httpcode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            return !empty($data['success']);
        }
        
        return false;
    }
    
    private static function get_credentials(): array {
        $siteid = '';
        $apikey = '';
        
        if (class_exists('\\local_aiconfig\\config')) {
            $siteid = \local_aiconfig\config::get_site_id();
            $apikey = \local_aiconfig\config::get_api_key();
        }
        
        if (empty($siteid)) {
            $siteid = get_config('local_cohortbranding', 'siteid');
        }
        if (empty($apikey)) {
            $apikey = get_config('local_cohortbranding', 'apikey');
        }
        
        return ['siteid' => $siteid ?: '', 'apikey' => $apikey ?: ''];
    }
    
    public static function show_unlock_notice(): void {
        $message = get_string('unlock_required', 'local_cohortbranding');
        if (empty($message) || $message === '[[unlock_required]]') {
            $message = 'This plugin requires ' . self::CREDITS_REQUIRED . ' AI credits to unlock. ' .
                       'Please visit your AI Grader dashboard at lms-labs.com to unlock this plugin.';
        }
        \core\notification::warning($message);
    }
    
    public static function check_and_notify(): bool {
        if (self::is_unlocked()) {
            return true;
        }
        self::show_unlock_notice();
        return false;
    }
}
