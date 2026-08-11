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
 * Cohort Branding - PHPUnit tests for manager class
 *
 * [9️⃣ Testing] Automated tests for core functionality
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortbranding;

defined('MOODLE_INTERNAL') || die();

/**
 * Test cases for the manager class.
 *
 * @covers \local_cohortbranding\manager
 */
class manager_test extends \advanced_testcase {
    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Test hex color validation with valid colors.
     */
    public function test_validate_hex_color_valid(): void {
        $this->assertEquals('#FF5500', manager::validate_hex_color('#ff5500'));
        $this->assertEquals('#000000', manager::validate_hex_color('#000000'));
        $this->assertEquals('#FFFFFF', manager::validate_hex_color('#ffffff'));
        $this->assertEquals('#123ABC', manager::validate_hex_color('#123abc'));
    }

    /**
     * Test hex color validation with invalid colors.
     */
    public function test_validate_hex_color_invalid(): void {
        $this->assertEquals('', manager::validate_hex_color(''));
        $this->assertEquals('', manager::validate_hex_color('red'));
        $this->assertEquals('', manager::validate_hex_color('#fff'));
        $this->assertEquals('', manager::validate_hex_color('#GGGGGG'));
        $this->assertEquals('', manager::validate_hex_color('FF5500'));
        $this->assertEquals('', manager::validate_hex_color('#FF550'));
        $this->assertEquals('', manager::validate_hex_color('<script>'));
    }

    /**
     * Test URL validation with valid URLs.
     */
    public function test_validate_url_valid(): void {
        $this->assertEquals('https://example.com/logo.png', 
            manager::validate_url('https://example.com/logo.png'));
        $this->assertEquals('http://localhost/test.png', 
            manager::validate_url('http://localhost/test.png'));
    }

    /**
     * Test URL validation with invalid URLs.
     */
    public function test_validate_url_invalid(): void {
        $this->assertEquals('', manager::validate_url(''));
        $this->assertEquals('', manager::validate_url('ftp://example.com/file'));
        $this->assertEquals('', manager::validate_url('javascript:alert(1)'));
        $this->assertEquals('', manager::validate_url('data:text/html,<script>'));
    }

    /**
     * Test font URL validation allows only trusted sources.
     */
    public function test_validate_font_url_trusted(): void {
        $googleUrl = 'https://fonts.googleapis.com/css2?family=Inter&display=swap';
        $this->assertEquals($googleUrl, manager::validate_font_url($googleUrl));
        
        $typekitUrl = 'https://use.typekit.net/abc123.css';
        $this->assertEquals($typekitUrl, manager::validate_font_url($typekitUrl));
    }

    /**
     * Test font URL validation rejects untrusted sources.
     */
    public function test_validate_font_url_untrusted(): void {
        $this->assertEquals('', manager::validate_font_url('https://evil.com/malicious.css'));
        $this->assertEquals('', manager::validate_font_url('https://fonts.evil.com/font.css'));
    }

    /**
     * Test font family validation.
     */
    public function test_validate_font_family(): void {
        $this->assertEquals('Inter', manager::validate_font_family('Inter'));
        $this->assertEquals('Open Sans', manager::validate_font_family('Open Sans'));
        $this->assertEquals("'Roboto', sans-serif", manager::validate_font_family("'Roboto', sans-serif"));
        
        // Reject dangerous characters.
        $this->assertEquals('', manager::validate_font_family('font; @import'));
        $this->assertEquals('', manager::validate_font_family('font<script>'));
    }

    /**
     * Test get_user_branding with invalid user ID.
     */
    public function test_get_user_branding_invalid_userid(): void {
        $this->assertNull(manager::get_user_branding(0));
        $this->assertNull(manager::get_user_branding(-1));
    }

    /**
     * Test get_branding with invalid ID.
     */
    public function test_get_branding_invalid_id(): void {
        $this->assertNull(manager::get_branding(0));
        $this->assertNull(manager::get_branding(-1));
    }

    /**
     * Test save_branding validates cohort ID.
     */
    public function test_save_branding_requires_cohortid(): void {
        $this->expectException(\moodle_exception::class);
        
        $data = new \stdClass();
        $data->cohortid = 0;
        manager::save_branding($data);
    }

    /**
     * Test save_branding sanitises colors.
     */
    public function test_save_branding_sanitises_colors(): void {
        global $DB;
        
        // Create a cohort first.
        $cohort = $this->getDataGenerator()->create_cohort();
        
        $data = new \stdClass();
        $data->cohortid = $cohort->id;
        $data->primarycolor = 'invalid';
        $data->secondarycolor = '#FF5500';
        $data->enabled = 1;
        
        $id = manager::save_branding($data);
        
        $record = $DB->get_record('local_cohortbranding', ['id' => $id]);
        $this->assertEquals('', $record->primarycolor); // Invalid was rejected.
        $this->assertEquals('#FF5500', $record->secondarycolor);
    }

    /**
     * Test delete_branding with invalid ID.
     */
    public function test_delete_branding_invalid_id(): void {
        $this->assertFalse(manager::delete_branding(0));
        $this->assertFalse(manager::delete_branding(-1));
    }

    /**
     * Test generate_css produces safe output.
     */
    public function test_generate_css_safe_output(): void {
        $branding = new \stdClass();
        $branding->primarycolor = '#FF5500';
        $branding->secondarycolor = '#333333';
        $branding->logourl = 'https://example.com/logo.png';
        
        $css = manager::generate_css($branding);
        
        $this->assertStringContainsString('--cohort-primary: #FF5500', $css);
        $this->assertStringContainsString('--cohort-secondary: #333333', $css);
        $this->assertStringContainsString('Cohort Branding:', $css);
    }

    /**
     * Test generate_css rejects invalid data.
     */
    public function test_generate_css_rejects_invalid(): void {
        $branding = new \stdClass();
        $branding->primarycolor = '<script>alert(1)</script>';
        $branding->logourl = 'javascript:alert(1)';
        
        $css = manager::generate_css($branding);
        
        $this->assertStringNotContainsString('script', $css);
        $this->assertStringNotContainsString('javascript', $css);
    }

    /**
     * Test cache invalidation.
     */
    public function test_cache_invalidation(): void {
        // This test verifies cache methods don't throw exceptions.
        manager::invalidate_cache(1);
        manager::invalidate_cache(null);
        
        $this->assertTrue(true); // If we get here, no exceptions were thrown.
    }
}
