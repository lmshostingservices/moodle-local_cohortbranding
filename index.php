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
 * Cohort Branding - Management page
 *
 * @package    local_cohortbranding
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/local/cohortbranding/classes/manager.php');

require_login();
require_capability('local/cohortbranding:manage', context_system::instance());

// Check unlock status
if (class_exists('\local_cohortbranding\unlock_verifier')) {
    if (!\local_cohortbranding\unlock_verifier::check_and_notify()) {
        $PAGE->set_url('/local/cohortbranding/index.php');
        $PAGE->set_context(context_system::instance());
        $PAGE->set_title(get_string('pluginname', 'local_cohortbranding'));
        $PAGE->set_heading(get_string('pluginname', 'local_cohortbranding'));
        $PAGE->set_pagelayout('admin');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('pluginname', 'local_cohortbranding'));
        echo $OUTPUT->notification(get_string('unlock_required', 'local_cohortbranding'), 'warning');
        echo $OUTPUT->footer();
        die();
    }
}

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url('/local/cohortbranding/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_cohortbranding'));
$PAGE->set_heading(get_string('pluginname', 'local_cohortbranding'));
$PAGE->set_pagelayout('admin');

// Handle delete.
if ($delete && $confirm && confirm_sesskey()) {
    \local_cohortbranding\manager::delete_branding($delete);
    redirect(
        new moodle_url('/local/cohortbranding/index.php'),
        get_string('brandingdeleted', 'local_cohortbranding'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managebranding', 'local_cohortbranding'));

// Add new button and CSV import button.
$addurl = new moodle_url('/local/cohortbranding/edit.php');
$importurl = new moodle_url('/local/cohortbranding/csv_import.php');
$availablecohorts = \local_cohortbranding\manager::get_available_cohorts();
echo html_writer::start_div('', ['style' => 'display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;']);
if (!empty($availablecohorts)) {
    echo html_writer::link($addurl, get_string('add', 'local_cohortbranding'), [
        'class' => 'btn btn-primary'
    ]);
}
echo html_writer::link($importurl, get_string('csv_import', 'local_cohortbranding'), [
    'class' => 'btn btn-outline-primary'
]);
echo html_writer::end_div();

// Get all brandings.
$brandings = \local_cohortbranding\manager::get_all_brandings();

if (empty($brandings)) {
    echo $OUTPUT->notification(get_string('nobrandings', 'local_cohortbranding'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('cohort', 'local_cohortbranding'),
        get_string('primarycolor', 'local_cohortbranding'),
        get_string('secondarycolor', 'local_cohortbranding'),
        get_string('fontfamily', 'local_cohortbranding'),
        get_string('priority', 'local_cohortbranding'),
        get_string('enabled', 'local_cohortbranding'),
        ''
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($brandings as $b) {
        $editurl = new moodle_url('/local/cohortbranding/edit.php', ['id' => $b->id]);
        $deleteurl = new moodle_url('/local/cohortbranding/index.php', [
            'delete' => $b->id,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);

        $primaryswatch = '';
        if (!empty($b->primarycolor)) {
            $primaryswatch = html_writer::tag('span', '', [
                'style' => "display:inline-block;width:20px;height:20px;background:{$b->primarycolor};border:1px solid #ccc;border-radius:3px;margin-right:5px;vertical-align:middle;"
            ]) . $b->primarycolor;
        }

        $secondaryswatch = '';
        if (!empty($b->secondarycolor)) {
            $secondaryswatch = html_writer::tag('span', '', [
                'style' => "display:inline-block;width:20px;height:20px;background:{$b->secondarycolor};border:1px solid #ccc;border-radius:3px;margin-right:5px;vertical-align:middle;"
            ]) . $b->secondarycolor;
        }

        $enabledbadge = $b->enabled
            ? html_writer::tag('span', 'Yes', ['class' => 'badge badge-success bg-success'])
            : html_writer::tag('span', 'No', ['class' => 'badge badge-secondary bg-secondary']);

        $actions = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-outline-primary mr-1']) . ' ';
        $actions .= html_writer::link($deleteurl, get_string('delete'), [
            'class' => 'btn btn-sm btn-outline-danger',
            'onclick' => "return confirm('" . get_string('confirmdelete', 'local_cohortbranding') . "');"
        ]);

        $table->data[] = [
            format_string($b->cohortname),
            $primaryswatch,
            $secondaryswatch,
            s($b->fontfamily),
            $b->priority,
            $enabledbadge,
            $actions
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
