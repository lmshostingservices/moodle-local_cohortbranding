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
 * Cohort Branding - Edit/Add form
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/local/cohortbranding/classes/manager.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->libdir . '/form/filemanager.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();
require_capability('local/cohortbranding:manage', context_system::instance());

$PAGE->set_url('/local/cohortbranding/edit.php', ['id' => $id]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');

$syscontext = context_system::instance();
$logooptions = \local_cohortbranding\manager::logo_filemanager_options();

if ($id) {
    $record = \local_cohortbranding\manager::get_branding($id);
    if (!$record) {
        throw new moodle_exception('invalidrecord');
    }
    $PAGE->set_title(get_string('edit', 'local_cohortbranding'));
    $PAGE->set_heading(get_string('edit', 'local_cohortbranding'));
} else {
    $record = new stdClass();
    $record->id = 0;
    $record->cohortid = 0;
    $record->logourl = '';
    $record->primarycolor = '#0073aa';
    $record->secondarycolor = '#23282d';
    $record->fontfamily = '';
    $record->fonturl = '';
    $record->priority = 0;
    $record->enabled = 1;
    $PAGE->set_title(get_string('add', 'local_cohortbranding'));
    $PAGE->set_heading(get_string('add', 'local_cohortbranding'));
}

// Handle form submission.
if (optional_param('submitbutton', false, PARAM_BOOL) && confirm_sesskey()) {
    $data = new stdClass();
    $data->id = $id;
    $data->cohortid = required_param('cohortid', PARAM_INT);
    // Logo is now an uploaded file. Keep any existing URL (e.g. from CSV import)
    // unless the admin chose to clear it.
    if (optional_param('clearlogourl', 0, PARAM_BOOL)) {
        $data->logourl = '';
    }
    $data->primarycolor = optional_param('primarycolor', '', PARAM_TEXT);
    $data->secondarycolor = optional_param('secondarycolor', '', PARAM_TEXT);
    $data->fontfamily = optional_param('fontfamily', '', PARAM_TEXT);
    $data->fonturl = optional_param('fonturl', '', PARAM_URL);
    $data->priority = optional_param('priority', 0, PARAM_INT);
    $data->enabled = optional_param('enabled', 0, PARAM_INT);

    // Validate hex colors.
    if (!empty($data->primarycolor) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data->primarycolor)) {
        $data->primarycolor = '';
    }
    if (!empty($data->secondarycolor) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data->secondarycolor)) {
        $data->secondarycolor = '';
    }

    // Check if cohort already has branding (for new records).
    if (!$id) {
        $existing = \local_cohortbranding\manager::get_branding_by_cohort($data->cohortid);
        if ($existing) {
            redirect(
                new moodle_url('/local/cohortbranding/edit.php'),
                get_string('cohorthasbranding', 'local_cohortbranding'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    $savedid = \local_cohortbranding\manager::save_branding($data);

    // Save the uploaded logo from the draft area.
    $draftitemid = required_param('logofile', PARAM_INT);
    file_save_draft_area_files(
        $draftitemid,
        $syscontext->id,
        'local_cohortbranding',
        \local_cohortbranding\manager::LOGO_FILEAREA,
        $savedid,
        $logooptions
    );
    \local_cohortbranding\manager::invalidate_cache();

    $message = $id ? get_string('brandingsaved', 'local_cohortbranding') : get_string('brandingcreated', 'local_cohortbranding');
    redirect(
        new moodle_url('/local/cohortbranding/index.php'),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Handle cancel.
if (optional_param('cancel', false, PARAM_BOOL)) {
    redirect(new moodle_url('/local/cohortbranding/index.php'));
}

// Prepare the logo draft area (copies any existing logo into a draft for editing).
$draftitemid = file_get_submitted_draft_itemid('logofile');
file_prepare_draft_area(
    $draftitemid,
    $syscontext->id,
    'local_cohortbranding',
    \local_cohortbranding\manager::LOGO_FILEAREA,
    $id ?: null,
    $logooptions
);

$fmoptions = new stdClass();
$fmoptions->maxbytes = $logooptions['maxbytes'];
$fmoptions->areamaxbytes = FILE_AREA_MAX_BYTES_UNLIMITED;
$fmoptions->maxfiles = $logooptions['maxfiles'];
$fmoptions->subdirs = $logooptions['subdirs'];
$fmoptions->accepted_types = $logooptions['accepted_types'];
$fmoptions->return_types = $logooptions['return_types'];
$fmoptions->itemid = $draftitemid;
$fmoptions->client_id = uniqid();
$fmoptions->target = 'id_logofile';
$fmoptions->context = $syscontext;
$fmoptions->mainfile = false;
$filemanagerhtml = $PAGE->get_renderer('core', 'files')->render(new form_filemanager($fmoptions));

$haslogofile = $id && \local_cohortbranding\manager::get_logo_file($id);

echo $OUTPUT->header();

// Get available cohorts for dropdown.
if ($id) {
    // Editing - show current cohort.
    $cohort = $DB->get_record('cohort', ['id' => $record->cohortid]);
    $cohorts = [$record->cohortid => $cohort];
} else {
    // Adding - show available cohorts.
    $cohorts = \local_cohortbranding\manager::get_available_cohorts();
}

if (empty($cohorts) && !$id) {
    echo $OUTPUT->notification(get_string('nocohorts', 'local_cohortbranding'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// Build cohort options.
$cohortoptions = [];
foreach ($cohorts as $c) {
    $cohortoptions[$c->id] = format_string($c->name);
    if (!empty($c->idnumber)) {
        $cohortoptions[$c->id] .= ' [' . $c->idnumber . ']';
    }
}

// Common font options.
$fontoptions = [
    '' => '-- Select font --',
    'Inter' => 'Inter',
    'Roboto' => 'Roboto',
    'Poppins' => 'Poppins',
    'Lato' => 'Lato',
    'Montserrat' => 'Montserrat',
    'Open Sans' => 'Open Sans',
    'Source Sans Pro' => 'Source Sans Pro',
    'Nunito' => 'Nunito',
    'Raleway' => 'Raleway',
    'Work Sans' => 'Work Sans',
];

?>

<form method="post" class="mform">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="cohortid" class="col-form-label">
                <?php echo get_string('cohort', 'local_cohortbranding'); ?>
                <span class="text-danger">*</span>
            </label>
        </div>
        <div class="col-md-9">
            <select name="cohortid" id="cohortid" class="form-control custom-select" <?php echo $id ? 'disabled' : 'required'; ?>>
                <?php foreach ($cohortoptions as $cid => $cname): ?>
                    <option value="<?php echo $cid; ?>" <?php echo ($record->cohortid == $cid) ? 'selected' : ''; ?>>
                        <?php echo s($cname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($id): ?>
                <input type="hidden" name="cohortid" value="<?php echo $record->cohortid; ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label class="col-form-label">
                <?php echo get_string('logofile', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <?php echo $filemanagerhtml; ?>
            <input type="hidden" name="logofile" id="id_logofile" value="<?php echo (int)$draftitemid; ?>">
            <small class="form-text text-muted"><?php echo get_string('logofile_desc', 'local_cohortbranding'); ?></small>
            <?php if (!empty($record->logourl)): ?>
                <div class="alert alert-info mt-2 mb-0 p-2">
                    <?php echo get_string($haslogofile ? 'logourl_overridden' : 'logourl_current', 'local_cohortbranding'); ?>
                    <br><code><?php echo s($record->logourl); ?></code>
                    <div class="form-check mt-1">
                        <input type="checkbox" name="clearlogourl" id="clearlogourl" value="1" class="form-check-input">
                        <label for="clearlogourl" class="form-check-label">
                            <?php echo get_string('clearlogourl', 'local_cohortbranding'); ?>
                        </label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="primarycolor" class="col-form-label">
                <?php echo get_string('primarycolor', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <div class="d-flex align-items-center">
                <input type="color" name="primarycolor" id="primarycolor" 
                       value="<?php echo s($record->primarycolor ?: '#0073aa'); ?>"
                       style="width: 50px; height: 38px; padding: 0; border: 1px solid #ced4da; cursor: pointer;">
                <input type="text" id="primarycolor_text" class="form-control ml-2" style="width: 100px;"
                       value="<?php echo s($record->primarycolor); ?>" 
                       pattern="^#[0-9A-Fa-f]{6}$" placeholder="#0073aa">
            </div>
            <small class="form-text text-muted"><?php echo get_string('primarycolor_desc', 'local_cohortbranding'); ?></small>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="secondarycolor" class="col-form-label">
                <?php echo get_string('secondarycolor', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <div class="d-flex align-items-center">
                <input type="color" name="secondarycolor" id="secondarycolor" 
                       value="<?php echo s($record->secondarycolor ?: '#23282d'); ?>"
                       style="width: 50px; height: 38px; padding: 0; border: 1px solid #ced4da; cursor: pointer;">
                <input type="text" id="secondarycolor_text" class="form-control ml-2" style="width: 100px;"
                       value="<?php echo s($record->secondarycolor); ?>" 
                       pattern="^#[0-9A-Fa-f]{6}$" placeholder="#23282d">
            </div>
            <small class="form-text text-muted"><?php echo get_string('secondarycolor_desc', 'local_cohortbranding'); ?></small>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="fontfamily" class="col-form-label">
                <?php echo get_string('fontfamily', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <select name="fontfamily" id="fontfamily" class="form-control custom-select">
                <?php foreach ($fontoptions as $fval => $fname): ?>
                    <option value="<?php echo s($fval); ?>" <?php echo ($record->fontfamily == $fval) ? 'selected' : ''; ?>>
                        <?php echo s($fname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted"><?php echo get_string('fontfamily_desc', 'local_cohortbranding'); ?></small>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="fonturl" class="col-form-label">
                <?php echo get_string('fonturl', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <input type="url" name="fonturl" id="fonturl" class="form-control" 
                   value="<?php echo s($record->fonturl); ?>"
                   placeholder="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
            <small class="form-text text-muted"><?php echo get_string('fonturl_desc', 'local_cohortbranding'); ?></small>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="priority" class="col-form-label">
                <?php echo get_string('priority', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <input type="number" name="priority" id="priority" class="form-control" style="width: 100px;"
                   value="<?php echo (int)$record->priority; ?>" min="0" max="99999">
            <small class="form-text text-muted"><?php echo get_string('priority_desc', 'local_cohortbranding'); ?></small>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3">
            <label for="enabled" class="col-form-label">
                <?php echo get_string('enabled', 'local_cohortbranding'); ?>
            </label>
        </div>
        <div class="col-md-9">
            <div class="form-check">
                <input type="checkbox" name="enabled" id="enabled" value="1" class="form-check-input"
                       <?php echo $record->enabled ? 'checked' : ''; ?>>
                <label for="enabled" class="form-check-label">
                    <?php echo get_string('enabled_desc', 'local_cohortbranding'); ?>
                </label>
            </div>
        </div>
    </div>

    <div class="fitem row form-group mb-3">
        <div class="col-md-3"></div>
        <div class="col-md-9">
            <button type="submit" name="submitbutton" value="1" class="btn btn-primary">
                <?php echo get_string('savechanges', 'local_cohortbranding'); ?>
            </button>
            <button type="submit" name="cancel" value="1" class="btn btn-secondary ml-2">
                <?php echo get_string('cancel', 'local_cohortbranding'); ?>
            </button>
        </div>
    </div>
</form>

<script>
// Sync color picker with text input.
document.getElementById('primarycolor').addEventListener('input', function () {
    document.getElementById('primarycolor_text').value = this.value;
});
document.getElementById('primarycolor_text').addEventListener('input', function () {
    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        document.getElementById('primarycolor').value = this.value;
    }
});
document.getElementById('secondarycolor').addEventListener('input', function () {
    document.getElementById('secondarycolor_text').value = this.value;
});
document.getElementById('secondarycolor_text').addEventListener('input', function () {
    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        document.getElementById('secondarycolor').value = this.value;
    }
});

// Auto-populate font URL based on selected font.
document.getElementById('fontfamily').addEventListener('change', function () {
    var fontUrls = {
        'Inter': 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
        'Roboto': 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
        'Poppins': 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
        'Lato': 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
        'Montserrat': 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap',
        'Open Sans': 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap',
        'Source Sans Pro': 'https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap',
        'Nunito': 'https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700&display=swap',
        'Raleway': 'https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700&display=swap',
        'Work Sans': 'https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700&display=swap'
    };
    var fonturl = document.getElementById('fonturl');
    // Always update font URL when font is selected
    if (fontUrls[this.value]) {
        fonturl.value = fontUrls[this.value];
    } else {
        fonturl.value = '';
    }
});
</script>

<?php
echo $OUTPUT->footer();
