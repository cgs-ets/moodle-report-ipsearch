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
 * IP search form
 *
 * @package    report
 * @subpackage ipsearch
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/formslib.php");

class ip_search_form extends moodleform {

    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $mform->addElement('text', 'address', get_string('address', 'report_ipsearch'));
        $mform->setType('address', PARAM_RAW );
        $mform->addRule('address', get_string('missingipaddress', 'report_ipsearch'), 'required', null, 'server');

        $mform->addElement('date_selector', 'datefrom', get_string('from'), array('startyear' => 2020,
            'timezone'  => 99, 'optional'  => false));
        $mform->addElement('date_selector', 'dateto', get_string('to'), array('startyear' => 2020,
            'timezone'  => 99, 'optional'  => false));

        $this->add_action_buttons($cancel = false, $submitlabel = get_string('getreport', 'report_ipsearch'));

        //$mform->display();
    }
}
