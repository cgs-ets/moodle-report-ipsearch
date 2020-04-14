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
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/report/log/locallib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

// Print the header & check permissions.
admin_externalpage_setup('reportipsearch', '', null, '', array('pagelayout' => 'report'));
echo $OUTPUT->header();

$url = new moodle_url("/report/ipsearch/index.php");

// Load IP addresses.
$sql = "SELECT distinct ip FROM {logstore_standard_log}  "
      . "WHERE timecreated >= " . get_string('startofyear', 'report_ipsearch')
      . " order by ip asc";

$requestedip = optional_param('address', '', PARAM_TEXT);

$ipaddresses = $DB->get_records_sql($sql);
$selectoptions = array();
foreach ($ipaddresses as $i => $ip) {
    $selectoptions [$i] = $ip->ip;
}

// Print the settings form.
echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

echo html_writer::tag('h1', get_string('pluginname', 'report_ipsearch'), array('class' => 'title'));

echo '<form method = "post" action =' .$url .' id = "settingsform">';

echo html_writer::start_div('inputdata');

echo html_writer::label(get_string('address', 'report_ipsearch'), 'table');
echo html_writer::select($selectoptions, 'address', $requestedip);

$sql = "SELECT distinct timecreated FROM {logstore_standard_log}"
    . " WHERE timecreated >=" . get_string('startofyear', 'report_ipsearch')
    . "  GROUP BY timecreated";

// Dates from and to.
$timecreated = $DB->get_records_sql($sql);
$requestedtimefrom = optional_param('table', '', PARAM_TEXT);
$requestedtimeto = optional_param('table', '', PARAM_TEXT);
$datefrom = array();
$dateto = array();

foreach ($timecreated as $i => $ip) {
    if (!in_array(userdate($ip->timecreated, get_string('strftimedaydate')), $datefrom)) {
        $datefrom [$i] = userdate($ip->timecreated, get_string('strftimedaydate'));
        $dateto [$i] = userdate($ip->timecreated, get_string('strftimedaydate'));
    }

}

echo html_writer::label(get_string('from', 'report_ipsearch'), 'datefrom');
echo html_writer::select($datefrom, 'datefrom', $requestedtimefrom);
echo html_writer::label(get_string('to', 'report_ipsearch'), 'dateto');
echo html_writer::select($dateto, 'dateto', $requestedtimeto);

echo '<input type="submit" class="btn btn-secondary" id="settingssubmit" value="' .get_string('getreport', 'report_ipsearch').'"/>';

echo html_writer::end_div();
echo '</form>';

echo $OUTPUT->box_end();


if (isset($_POST["address"]) && $_POST["address"] === '') {
    echo html_writer::start_div('alert alert-danger');
    echo get_string('selectaddress', 'report_ipsearch');
    echo html_writer::end_div();
}

if ($requestedip) {
    get_ip_search_results($_POST["address"], (int) $_POST["datefrom"], (int) $_POST["dateto"]);
}

function get_ip_search_results($ipadress, $datefrom, $dateto) {

    global $DB, $PAGE, $OUTPUT;

    // If any of the dates is not selected. Find the logs from the past two weeks.
    // $dateFrom = last two weeks $dateto = today.

    if (empty($datefrom) || empty($dateto)) {

        $date = new \DateTime("now");
        $dateto = $date->getTimestamp();

        $date->modify('-14 day');
        $datefrom = $date->getTimestamp();
    }

    $df = new \DateTime();
    $df->setTimestamp($datefrom);
    $dt = new \DateTime();
    $dt->setTimestamp($dateto);

    if (date_diff($df, $dt)->invert == 1) {
        echo html_writer::start_div('alert alert-danger');
        echo get_string('incorrectdaterange', 'report_ipsearch');
        echo html_writer::end_div();
        return;
    }
    // Get the user  information.
    $query = 'SELECT distinct firstname, lastname
              FROM mdl_user  inner join (SELECT userid, origin
                                         FROM mdl_logstore_standard_log
                                         WHERE (timecreated BETWEEN ? AND ?) AND ip = ? ) as users
             ON  mdl_user.id = users.userid';

    $params = array ($datefrom, $dateto, $ipadress);
    $results = $DB->get_records_sql($query, $params);

    if (empty($results)) {
        echo html_writer::start_div('alert alert-info');
        echo get_string('noresults', 'report_ipsearch');
        echo html_writer::end_div();
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
    $page    = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', $page, PARAM_INT); // How many per page.
    echo $OUTPUT->paging_bar(count($results), $page, $perpage, $url);
}


// Footer.
echo $OUTPUT->footer();
