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
 * For a given string, content areas with links including it
 *
 * @package    report
 * @subpackage ipsearch
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/report/ipsearch/classes/searchform.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

// Print the header & check permissions.
admin_externalpage_setup('reportipsearch', '', null, '', array('pagelayout' => 'report'));
echo $OUTPUT->header();

// Print the settings form.
echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

echo html_writer::tag('h1', get_string('pluginname', 'report_ipsearch'), array('class' => 'title'));

$mform = new ip_search_form();

echo $OUTPUT->box_end();

if (isset($_POST["address"]) && !empty($_POST["address"])) {

    $datefrom = new DateTime ($_POST['datefrom']['year'] . '-' . $_POST['datefrom']['month'] . '-' . $_POST['datefrom']['day'] . ' 00:00:00.000');
    $dateto = new DateTIme ($_POST['dateto']['year'] . '-' . $_POST['dateto']['month'] . '-' . $_POST['dateto']['day'] );
    $ipadress = $_POST["address"];  

    // When searching on the same day, change time to to get the time range up to now.    
    if( $datefrom->getTimestamp() == $dateto->getTimestamp()) {
        $dateto = new DateTime('now');
    }
   

    // Validate IP Address.
    if (!filter_var($ipadress, FILTER_VALIDATE_IP)) {
        echo html_writer::start_div('alert alert-danger');
        echo get_string('incorrectipaddress', 'report_ipsearch');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        return;
    }
    // Validate date range.
    if (date_diff($datefrom, $dateto)->invert == 1) {
        echo html_writer::start_div('alert alert-danger');
        echo get_string('incorrectdaterange', 'report_ipsearch');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        return;
    }

    $query = 'SELECT distinct firstname, lastname
               FROM mdl_user  inner join (SELECT userid, origin
                                          FROM mdl_logstore_standard_log
                                          WHERE (timecreated BETWEEN ? AND ?) AND ip = ? ) as users
               ON  mdl_user.id = users.userid
               ORDER BY firstname';

    $params = array ($datefrom->getTimestamp(), $dateto->getTimestamp(), $_POST["address"]);
    $results = $DB->get_records_sql($query, $params);


    if (empty($results)) {
        echo html_writer::start_div('alert alert-info');
        echo get_string('noresults', 'report_ipsearch');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        return;
    }

    $table = new html_table();
    $table->head = array( get_string('firstname', 'report_ipsearch'),  get_string('lastname', 'report_ipsearch'),
        get_string('ipaddress', 'report_ipsearch'));

    foreach ($results as $i => $user) {
        $table->data [] = array( $user->firstname, $user->lastname, $ipadress);
    }

    echo html_writer::table($table);

    // Add Navigation.
    $url  = new moodle_url("/report/ipsearch/index.php");
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', $page, PARAM_INT); // How many per page.

    echo $OUTPUT->paging_bar(count($results), $page, $perpage, $url);
}

// Footer.
echo $OUTPUT->footer();
