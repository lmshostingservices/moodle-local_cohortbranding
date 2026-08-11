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
 * Cohort Branding - CSV Bulk Import with Auto-Scraping
 *
 * Accepts a CSV of school names + URLs, scrapes each site to extract
 * the logo and primary brand colour, creates Moodle cohorts, and
 * bulk-creates cohort branding records ready to go.
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/local/cohortbranding/classes/manager.php');
require_once($CFG->dirroot . '/cohort/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/cohortbranding:manage', $context);

$PAGE->set_url('/local/cohortbranding/csv_import.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('csv_import', 'local_cohortbranding'));
$PAGE->set_heading(get_string('csv_import', 'local_cohortbranding'));

// ─── Scraping helper functions ────────────────────────────────────────────────

/**
 * Make a relative URL absolute based on a base URL.
 */
function local_cohortbranding_make_absolute($url, $baseurl) {
    if (empty($url)) return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    if (strpos($url, '//') === 0) return 'https:' . $url;
    if ($url[0] === '/') {
        $parsed = parse_url($baseurl);
        return $parsed['scheme'] . '://' . $parsed['host'] . $url;
    }
    return rtrim($baseurl, '/') . '/' . $url;
}

/**
 * Validate and normalise a hex colour string.
 * Returns #RRGGBB or empty string.
 */
function local_cohortbranding_normalize_hex($color) {
    $color = trim($color);
    if (preg_match('/^#?([0-9A-Fa-f]{6})$/', $color, $m)) {
        return '#' . strtoupper($m[1]);
    }
    // Expand shorthand #RGB → #RRGGBB
    if (preg_match('/^#?([0-9A-Fa-f]{3})$/', $color, $m)) {
        $c = $m[1];
        return '#' . strtoupper($c[0].$c[0].$c[1].$c[1].$c[2].$c[2]);
    }
    return '';
}

/**
 * Judge if a colour is too light (likely a background, not a brand colour).
 * Returns true if the colour should be skipped.
 */
function local_cohortbranding_is_too_light($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return true;
    list($r, $g, $b) = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    $luminance = (0.299*$r + 0.587*$g + 0.114*$b);
    return $luminance > 200; // > 200/255 is very light
}

/**
 * Scrape a school website and extract logo URL and primary brand colour.
 *
 * @param string $url The website URL to scrape
 * @return array ['logourl'=>string, 'primarycolor'=>string, 'error'=>string]
 */
function local_cohortbranding_scrape_site($url) {
    $result = ['logourl' => '', 'primarycolor' => '', 'secondarycolor' => '', 'error' => ''];

    // Normalise URL
    $url = trim($url);
    if (empty($url)) {
        $result['error'] = 'No URL provided';
        return $result;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    // Parse base URL for resolving relative paths
    $parsed = parse_url($url);
    if (!$parsed || empty($parsed['host'])) {
        $result['error'] = 'Invalid URL format';
        return $result;
    }
    $baseurl = $parsed['scheme'] . '://' . $parsed['host'];

    // Fetch page HTML
    $context = stream_context_create([
        'http' => [
            'timeout'         => 15,
            'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'follow_location' => true,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $html = @file_get_contents($url, false, $context);
    if ($html === false || strlen($html) < 100) {
        $result['error'] = 'Could not fetch page (connection failed or empty response)';
        return $result;
    }

    // ── 1. Extract theme-color meta ─────────────────────────────────────────
    if (preg_match('/<meta[^>]+name=["\']theme-color["\'][^>]+content=["\']([^"\']+)/i', $html, $m)
     || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']theme-color["\']/i', $html, $m)) {
        $c = local_cohortbranding_normalize_hex($m[1]);
        if ($c && !local_cohortbranding_is_too_light($c)) {
            $result['primarycolor'] = $c;
        }
    }

    // ── 2. Extract logo URL ─────────────────────────────────────────────────
    $logourl = '';

    // Try apple-touch-icon (highest quality logo)
    if (preg_match('/<link[^>]+rel=["\']apple-touch-icon(?:-precomposed)?["\'][^>]+href=["\']([^"\']+)/i', $html, $m)
     || preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']apple-touch-icon[^"\']*["\']/i', $html, $m)) {
        $logourl = $m[1];
    }

    // Try og:image (usually a high quality image)
    if (!$logourl && preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $logourl = $m[1];
    }
    if (!$logourl && preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
        $logourl = $m[1];
    }

    // Try any link icon with PNG/SVG (prefer larger sizes)
    if (!$logourl) {
        // Look for icon links with size attributes, prefer 192+ or 180+
        if (preg_match_all('/<link[^>]+rel=["\'][^"\']*icon[^"\']*["\'][^>]+>/i', $html, $icons)) {
            $best = '';
            $bestSize = 0;
            foreach ($icons[0] as $iconTag) {
                if (preg_match('/href=["\']([^"\']+\.(?:png|svg))/i', $iconTag, $hm)) {
                    $href = $hm[1];
                    $size = 0;
                    if (preg_match('/sizes=["\'](\d+)x/i', $iconTag, $sm)) {
                        $size = (int)$sm[1];
                    }
                    if ($size > $bestSize) {
                        $bestSize = $size;
                        $best = $href;
                    } elseif (!$best) {
                        $best = $href;
                    }
                }
            }
            if ($best) $logourl = $best;
        }
    }

    // Try link rel="shortcut icon"
    if (!$logourl && preg_match('/<link[^>]+rel=["\']shortcut icon["\'][^>]+href=["\']([^"\']+)/i', $html, $m)) {
        $logourl = $m[1];
    }

    if ($logourl) {
        $result['logourl'] = local_cohortbranding_make_absolute($logourl, $baseurl);
    }

    // ── 3. Fallback colour extraction from CSS ──────────────────────────────
    if (!$result['primarycolor']) {
        // CSS custom properties: --primary, --brand-color, --color-primary, etc.
        $cssVarPatterns = [
            '/--(?:primary|brand|main|accent|site|key)-(?:color|colour|bg|background)\s*:\s*(#[0-9A-Fa-f]{3,6})/i',
            '/--color-(?:primary|brand|main|accent)\s*:\s*(#[0-9A-Fa-f]{3,6})/i',
            '/--(?:primary|brand)\s*:\s*(#[0-9A-Fa-f]{3,6})/i',
        ];
        foreach ($cssVarPatterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $c = local_cohortbranding_normalize_hex($m[1]);
                if ($c && !local_cohortbranding_is_too_light($c)) {
                    $result['primarycolor'] = $c;
                    break;
                }
            }
        }
    }

    // ── 4. Fallback: header/nav background-color in style blocks ───────────
    if (!$result['primarycolor']) {
        // Extract all <style> content and look for nav/header colors
        preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $html, $styleBlocks);
        foreach ($styleBlocks[1] as $css) {
            // Look for header, nav, .header, .navbar selectors with background
            if (preg_match('/(?:header|nav|\.header|\.navbar|\.site-header)[^{}]*\{[^}]*background(?:-color)?\s*:\s*(#[0-9A-Fa-f]{3,6})/i', $css, $m)) {
                $c = local_cohortbranding_normalize_hex($m[1]);
                if ($c && !local_cohortbranding_is_too_light($c)) {
                    $result['primarycolor'] = $c;
                    break;
                }
            }
        }
    }

    return $result;
}

/**
 * Parse a CSV file and return rows as associative arrays.
 * Supports columns: name, url, primary_color, logo_url, secondary_color
 */
function local_cohortbranding_parse_csv($filepath) {
    $rows = [];
    $handle = @fopen($filepath, 'r');
    if (!$handle) return $rows;

    $headers = null;
    $linenum = 0;
    while (($row = fgetcsv($handle, 4096, ',')) !== false) {
        $linenum++;
        if ($linenum === 1) {
            // Normalise header names
            $headers = array_map(function ($h) {
                return strtolower(trim(str_replace([' ', '-'], '_', $h)));
            }, $row);
            continue;
        }
        if (!$headers) continue;
        $data = [];
        foreach ($headers as $i => $h) {
            $data[$h] = isset($row[$i]) ? trim($row[$i]) : '';
        }
        // Ensure required fields exist
        $rows[] = [
            'name'            => $data['name'] ?? $data['school_name'] ?? $data['cohort_name'] ?? '',
            'url'             => $data['url'] ?? $data['website'] ?? $data['website_url'] ?? '',
            'primary_color'   => $data['primary_color'] ?? $data['primarycolor'] ?? $data['colour'] ?? $data['color'] ?? '',
            'logo_url'        => $data['logo_url'] ?? $data['logourl'] ?? $data['logo'] ?? '',
            'secondary_color' => $data['secondary_color'] ?? $data['secondarycolor'] ?? '',
        ];
    }
    fclose($handle);
    return $rows;
}

// ─── Handle download of sample CSV ────────────────────────────────────────────

if (optional_param('downloadsample', 0, PARAM_INT)) {
    $samplecsv = "name,url,primary_color,logo_url,secondary_color\n"
        . "\"RMIT University\",\"https://www.rmit.edu.au\",\"\",\"\",\"\"\n"
        . "\"Monash University\",\"https://www.monash.edu\",\"\",\"\",\"\"\n"
        . "\"University of Melbourne\",\"https://www.unimelb.edu.au\",\"\",\"\",\"\"\n"
        . "\"Charles Sturt University\",\"https://www.csu.edu.au\",\"\",\"\",\"\"\n"
        . "\"Griffith University\",\"https://www.griffith.edu.au\",\"\",\"\",\"\"\n"
        . "\"Springfield High School\",\"https://www.springfieldhigh.edu.au\",\"#003366\",\"\",\"#cc0000\"\n"
        . "\"Riverside Training College\",\"https://www.rtc.edu.au\",\"\",\"\",\"\"\n";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cohort_branding_import_sample.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    @ob_end_clean();
    echo $samplecsv;
    exit;
}

// ─── Determine which step we're on ────────────────────────────────────────────

$step = optional_param('step', 1, PARAM_INT);

// ─── Step 3: Confirm and create cohorts + branding ───────────────────────────

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $importjson = optional_param('importjson', '', PARAM_RAW);
    $importrows = json_decode($importjson, true);

    $created = 0; $skipped = 0; $errors = [];

    if ($importrows) {
        foreach ($importrows as $row) {
            if (empty($row['name']) || empty($row['selected'])) continue;

            $name       = clean_param($row['name'], PARAM_TEXT);
            $logourl    = clean_param($row['logourl'] ?? '', PARAM_URL);
            $primary    = clean_param($row['primarycolor'] ?? '', PARAM_TEXT);
            $secondary  = clean_param($row['secondarycolor'] ?? '', PARAM_TEXT);

            // Validate colors
            if ($primary && !preg_match('/^#[0-9A-Fa-f]{6}$/', $primary)) $primary = '';
            if ($secondary && !preg_match('/^#[0-9A-Fa-f]{6}$/', $secondary)) $secondary = '';

            // 1. Find or create Moodle cohort
            $cohort = $DB->get_record('cohort', ['name' => $name], '*');
            if (!$cohort) {
                $newcohort = new stdClass();
                $newcohort->contextid       = $context->id;
                $newcohort->name            = $name;
                $newcohort->idnumber        = '';
                $newcohort->description     = '';
                $newcohort->descriptionformat = FORMAT_HTML;
                $newcohort->visible         = 1;
                try {
                    $cohortid = cohort_add_cohort($newcohort);
                } catch (Exception $e) {
                    $errors[] = "Could not create cohort for \"{$name}\": " . $e->getMessage();
                    $skipped++;
                    continue;
                }
            } else {
                $cohortid = $cohort->id;
            }

            // 2. Check if branding already exists
            $existing = \local_cohortbranding\manager::get_branding_by_cohort($cohortid);
            if ($existing) {
                $skipped++;
                continue;
            }

            // 3. Create branding record
            $data = new stdClass();
            $data->id             = 0;
            $data->cohortid       = $cohortid;
            $data->logourl        = $logourl;
            $data->primarycolor   = $primary ?: '#003399';
            $data->secondarycolor = $secondary ?: '#23282d';
            $data->fontfamily     = '';
            $data->fonturl        = '';
            $data->priority       = 0;
            $data->enabled        = 1;

            try {
                \local_cohortbranding\manager::save_branding($data);
                $created++;
            } catch (Exception $e) {
                $errors[] = "Could not save branding for \"{$name}\": " . $e->getMessage();
                $skipped++;
            }
        }
    }

    // Show results
    echo $OUTPUT->header();
    echo html_writer::tag('h4', get_string('csv_import', 'local_cohortbranding'));

    if ($created > 0) {
        echo $OUTPUT->notification(
            get_string('csv_import_success', 'local_cohortbranding', $created),
            'success'
        );
    }
    if ($skipped > 0) {
        echo $OUTPUT->notification("{$skipped} row(s) skipped (already have branding or no name).", 'info');
    }
    foreach ($errors as $err) {
        echo $OUTPUT->notification($err, 'error');
    }
    echo html_writer::link(
        new moodle_url('/local/cohortbranding/index.php'),
        get_string('managebranding', 'local_cohortbranding'),
        ['class' => 'btn btn-primary mr-2']
    );
    echo html_writer::link(
        new moodle_url('/local/cohortbranding/csv_import.php'),
        get_string('csv_import_another', 'local_cohortbranding'),
        ['class' => 'btn btn-outline-secondary']
    );
    echo $OUTPUT->footer();
    exit;
}

// ─── Step 2: Process uploaded CSV and scrape sites ────────────────────────────

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $uploadedfile = $_FILES['csvfile'] ?? null;
    $previewRows = [];
    $parseError = '';

    if (!$uploadedfile || $uploadedfile['error'] !== UPLOAD_ERR_OK) {
        $parseError = 'No file uploaded or upload error. Please try again.';
    } elseif (strtolower(pathinfo($uploadedfile['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $parseError = 'File must be a .csv file.';
    } else {
        $csvrows = local_cohortbranding_parse_csv($uploadedfile['tmp_name']);
        if (empty($csvrows)) {
            $parseError = 'CSV file is empty or could not be parsed. Ensure it has a header row with "name" and "url" columns.';
        } else {
            // Scrape each URL
            foreach ($csvrows as $row) {
                $name   = trim($row['name']);
                $url    = trim($row['url']);
                $preset_primary   = local_cohortbranding_normalize_hex($row['primary_color']);
                $preset_logo      = $row['logo_url'];
                $preset_secondary = local_cohortbranding_normalize_hex($row['secondary_color']);

                $preview = [
                    'name'          => $name,
                    'url'           => $url,
                    'logourl'       => $preset_logo,
                    'primarycolor'  => $preset_primary,
                    'secondarycolor'=> $preset_secondary,
                    'scraped_logo'  => '',
                    'scraped_color' => '',
                    'scrape_error'  => '',
                    'cohort_exists' => false,
                    'branding_exists' => false,
                ];

                // Check if cohort + branding already exist
                if ($name) {
                    $existingCohort = $DB->get_record('cohort', ['name' => $name], 'id');
                    if ($existingCohort) {
                        $preview['cohort_exists'] = true;
                        $existingBranding = \local_cohortbranding\manager::get_branding_by_cohort($existingCohort->id);
                        if ($existingBranding) {
                            $preview['branding_exists'] = true;
                        }
                    }
                }

                // Scrape the URL if provided
                if ($url) {
                    $scraped = local_cohortbranding_scrape_site($url);
                    $preview['scrape_error'] = $scraped['error'];
                    $preview['scraped_logo']  = $scraped['logourl'];
                    $preview['scraped_color'] = $scraped['primarycolor'];

                    // Use scraped data only if not preset
                    if (!$preview['logourl'] && $scraped['logourl']) {
                        $preview['logourl'] = $scraped['logourl'];
                    }
                    if (!$preview['primarycolor'] && $scraped['primarycolor']) {
                        $preview['primarycolor'] = $scraped['primarycolor'];
                    }
                }

                // Default color fallback
                if (!$preview['primarycolor']) {
                    $preview['primarycolor'] = '#003399';
                }

                $previewRows[] = $preview;
            }
        }
    }

    // Render Step 2 preview page
    echo $OUTPUT->header();
    echo html_writer::tag('h4', get_string('csv_import', 'local_cohortbranding'));

    if ($parseError) {
        echo $OUTPUT->notification($parseError, 'error');
        echo html_writer::link(
            new moodle_url('/local/cohortbranding/csv_import.php'),
            '← Back to upload',
            ['class' => 'btn btn-outline-secondary']
        );
        echo $OUTPUT->footer();
        exit;
    }

    echo html_writer::tag('p', 'Review the scraped data below. Tick the rows you want to import, adjust colours or logo URLs if needed, then click <strong>Confirm Import</strong>.', []);

    // Build the form
    $importdata = [];
    echo '<form method="post" action="">';
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'step', 'value' => '3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo '<div style="overflow-x:auto;">';
    echo '<table class="generaltable" style="min-width:900px;margin-bottom:16px;">';
    echo '<thead><tr>'
        . '<th style="width:35px;"><input type="checkbox" id="selectall" checked title="Select all"> All</th>'
        . '<th>School / Cohort Name</th>'
        . '<th>Website</th>'
        . '<th>Scraped Logo</th>'
        . '<th>Logo URL</th>'
        . '<th>Primary Colour</th>'
        . '<th>Status</th>'
        . '</tr></thead><tbody>';

    foreach ($previewRows as $i => $row) {
        $rowId     = 'row_' . $i;
        $disabled  = $row['branding_exists'] ? 'disabled title="Branding already exists for this cohort"' : '';
        $checked   = $row['branding_exists'] ? '' : 'checked';
        $rowStyle  = $row['branding_exists'] ? 'background:#fafafa;color:#999;' : '';

        $statusBadge = '';
        if ($row['branding_exists']) {
            $statusBadge = '<span class="badge badge-warning bg-warning text-dark">Branding exists</span>';
        } elseif ($row['cohort_exists']) {
            $statusBadge = '<span class="badge badge-info bg-info">Cohort exists, add branding</span>';
        } else {
            $statusBadge = '<span class="badge badge-success bg-success">Will create cohort + branding</span>';
        }
        if ($row['scrape_error']) {
            $statusBadge .= ' <span class="badge badge-secondary bg-secondary" title="' . s($row['scrape_error']) . '">Scrape failed</span>';
        }

        // Logo thumbnail
        $logoThumb = '';
        if (!empty($row['logourl'])) {
            $logoThumb = '<img src="' . s($row['logourl']) . '" alt="logo" style="max-height:40px;max-width:80px;border:1px solid #eee;border-radius:3px;" onerror="this.style.display=\'none\'">';
        }

        // Color swatch
        $colorSwatch = '<input type="color" value="' . s($row['primarycolor']) . '" '
            . 'id="color_' . $i . '" '
            . 'oninput="document.getElementById(\'colortext_' . $i . '\').value=this.value;document.getElementById(\'colorhidden_' . $i . '\').value=this.value;" '
            . 'style="width:40px;height:32px;padding:0;border:1px solid #ccc;cursor:pointer;"> '
            . '<input type="text" id="colortext_' . $i . '" value="' . s($row['primarycolor']) . '" '
            . 'style="width:75px;font-size:12px;" '
            . 'oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){document.getElementById(\'color_' . $i . '\').value=this.value;document.getElementById(\'colorhidden_' . $i . '\').value=this.value;}">';

        // The data we'll serialize for Step 3
        $importdata[$i] = [
            'name'          => $row['name'],
            'url'           => $row['url'],
            'logourl'       => $row['logourl'],
            'primarycolor'  => $row['primarycolor'],
            'secondarycolor'=> $row['secondarycolor'],
            'selected'      => !$row['branding_exists'],
        ];

        echo '<tr style="' . $rowStyle . '">';
        echo '<td style="text-align:center;"><input type="checkbox" id="' . $rowId . '" name="selected_' . $i . '" value="1" ' . $checked . ' ' . $disabled . ' onchange="updateImportData()"></td>';
        echo '<td><strong>' . s($row['name']) . '</strong></td>';
        echo '<td><small><a href="' . s($row['url']) . '" target="_blank">' . s($row['url']) . '</a></small></td>';
        echo '<td>' . $logoThumb . '</td>';
        echo '<td><input type="text" id="logourl_' . $i . '" value="' . s($row['logourl']) . '" style="width:180px;font-size:11px;" placeholder="https://..." oninput="updateImportData()"></td>';
        echo '<td>' . $colorSwatch . '<input type="hidden" id="colorhidden_' . $i . '" value="' . s($row['primarycolor']) . '" onchange="updateImportData()"></td>';
        echo '<td>' . $statusBadge . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    // Hidden field to pass serialized import data
    $importjson = json_encode(array_values($importdata));
    echo '<input type="hidden" name="importjson" id="importjson" value="' . s($importjson) . '">';

    echo '<div style="display:flex;gap:10px;align-items:center;margin-top:8px;">';
    echo '<button type="submit" class="btn btn-primary">Confirm Import</button>';
    echo html_writer::link(new moodle_url('/local/cohortbranding/csv_import.php'), 'Cancel', ['class' => 'btn btn-outline-secondary']);
    echo '</div>';
    echo '</form>';

    ?>
    <script>
    // Select all checkbox
    document.getElementById('selectall').addEventListener('change', function () {
        var cbs = document.querySelectorAll('tbody input[type=checkbox]:not([disabled])');
        cbs.forEach(function (cb){ cb.checked = this.checked; }, this);
        updateImportData();
    });

    // Update the hidden importjson field before submit
    var importData = <?php echo $importjson; ?>;

    function updateImportData() {
        for (var i = 0; i < importData.length; i++) {
            var selEl = document.getElementById('row_' + i);
            if (selEl) importData[i].selected = selEl.checked;
            var logoEl = document.getElementById('logourl_' + i);
            if (logoEl) importData[i].logourl = logoEl.value;
            var colorEl = document.getElementById('colorhidden_' + i);
            if (colorEl) importData[i].primarycolor = colorEl.value;
        }
        document.getElementById('importjson').value = JSON.stringify(importData);
    }
    // Sync color pickers to hidden fields and data array
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id && e.target.id.startsWith('color_')) {
            updateImportData();
        }
    });
    </script>
    <?php

    echo $OUTPUT->footer();
    exit;
}

// ─── Step 1: Upload form ──────────────────────────────────────────────────────

echo $OUTPUT->header();
echo html_writer::tag('h4', get_string('csv_import', 'local_cohortbranding'));

echo html_writer::start_div('', ['style' => 'max-width:700px;']);

echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom:20px;']);
echo '<strong>' . get_string('csv_import_howto', 'local_cohortbranding') . '</strong>';
echo '<ol style="margin:8px 0 0;padding-left:20px;">';
echo '<li>' . get_string('csv_import_step1', 'local_cohortbranding') . '</li>';
echo '<li>' . get_string('csv_import_step2', 'local_cohortbranding') . '</li>';
echo '<li>' . get_string('csv_import_step3', 'local_cohortbranding') . '</li>';
echo '<li>' . get_string('csv_import_step4', 'local_cohortbranding') . '</li>';
echo '</ol>';
echo html_writer::end_div();

// CSV format description
echo html_writer::start_div('', ['style' => 'background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:14px;margin-bottom:20px;font-size:13px;']);
echo '<strong>CSV Format:</strong> The file must have a header row. Supported columns:<br>';
echo '<code style="display:block;margin:8px 0;background:#fff;border:1px solid #eee;padding:6px;border-radius:4px;">name, url, primary_color, logo_url, secondary_color</code>';
echo '<ul style="margin:4px 0;padding-left:16px;">';
echo '<li><strong>name</strong> (required) — school or organisation name, used as the cohort name</li>';
echo '<li><strong>url</strong> (recommended) — website URL to auto-scrape logo and primary brand colour</li>';
echo '<li><strong>primary_color</strong> (optional) — hex colour e.g. <code>#003366</code> — overrides scraped colour</li>';
echo '<li><strong>logo_url</strong> (optional) — direct logo URL — overrides scraped logo</li>';
echo '<li><strong>secondary_color</strong> (optional) — secondary hex colour</li>';
echo '</ul>';
echo html_writer::end_div();

$sampleUrl = new moodle_url('/local/cohortbranding/csv_import.php', ['downloadsample' => 1]);
echo html_writer::tag('p',
    html_writer::link($sampleUrl, '⬇ Download sample CSV (7 schools)', ['class' => 'btn btn-outline-secondary btn-sm', 'style' => 'margin-bottom:20px;'])
);

echo '<form method="post" enctype="multipart/form-data">';
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'step', 'value' => '2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo '<div class="fitem row form-group mb-3">';
echo '<div class="col-md-3"><label for="csvfile" class="col-form-label"><strong>' . get_string('csv_upload', 'local_cohortbranding') . '</strong></label></div>';
echo '<div class="col-md-9">';
echo '<input type="file" name="csvfile" id="csvfile" accept=".csv" class="form-control" required>';
echo '<small class="form-text text-muted">Maximum 1000 rows. File must be UTF-8 encoded CSV with header row.</small>';
echo '</div></div>';

echo '<div class="fitem row form-group">';
echo '<div class="col-md-3"></div>';
echo '<div class="col-md-9">';
echo '<button type="submit" class="btn btn-primary">' . get_string('csv_upload_scrape', 'local_cohortbranding') . '</button>';
echo '&nbsp;&nbsp;';
echo html_writer::link(new moodle_url('/local/cohortbranding/index.php'), get_string('cancel', 'local_cohortbranding'), ['class' => 'btn btn-outline-secondary']);
echo '</div></div>';
echo '</form>';

echo html_writer::end_div();
echo $OUTPUT->footer();
