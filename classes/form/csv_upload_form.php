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

namespace local_cohortbranding\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Upload form for the CSV import (step 1 of the import wizard).
 *
 * @package    local_cohortbranding
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_upload_form extends \moodleform {
    /**
     * Defines the file picker, the hidden step and the buttons.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'step', 2);
        $mform->setType('step', PARAM_INT);
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('csv_upload', 'local_cohortbranding'),
            null,
            ['accepted_types' => ['.csv'], 'maxbytes' => 0]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement(
            'static',
            'csvhelp',
            '',
            'Maximum 1000 rows. File must be UTF-8 encoded CSV with header row.'
        );
        $this->add_action_buttons(true, get_string('csv_upload_scrape', 'local_cohortbranding'));
    }
}
