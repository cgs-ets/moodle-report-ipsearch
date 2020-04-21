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
$mform->display();
$mform = $mform->get_data();

echo $OUTPUT->box_end();

if (isset($mform->address) && !empty($mform->address)) {

    $datefrom = $mform->datefrom;
    $dateto = $mform->dateto;
    $ipadress = trim($mform->address);

    // Validate IP Address.
    if (!filter_var($ipadress, FILTER_VALIDATE_IP)) {
        display_alert_danger_message(get_string('incorrectipaddress', 'report_ipsearch'));
        return;
    }

    // Validate date range.
    $df = new \DateTime();
    $dt = new \DateTime();

    $df->setTimestamp($datefrom);

    // To validate days where the from and to date are the same, cover the entire range time for the day.
    $dt->setTimestamp($dateto);
    $dt->format('Y-m-d H : i : s');
    $dt->setTime(23, 59, 59);

    if (date_diff($df, $dt)->invert == 1) {
        display_alert_danger_message( get_string('incorrectdaterange', 'report_ipsearch'));
        return;
    }
    $results = get_results($mform, $dt);

    if (!isset($results)) { // Null value when the log store is an external DB.
        return;
    }

    if (empty($results)) {
        display_alert_info_message(get_string('noresults', 'report_ipsearch'));
        return;
    }

    $table = new html_table();
    $table->head = array( get_string('user', 'report_ipsearch'), get_string('ipaddress', 'report_ipsearch'), get_string('lastaccess', 'report_ipsearch'));

    foreach ($results as $i => $user) {
        $userprofile = new \moodle_url('/user/profile.php', array('id' => $user->id));
        $link = html_writer::tag('a', $user->firstname .' ' . $user->lastname, array('href' => $userprofile));
        $table->data [] = array( $link,  $ipadress,  userdate($user->lastaccess, get_string('strftimedaydate')));
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

// Helper functions.

/**
 * Returns results from one of the following cases:
 * Enabled stores: Legacy + Standard store, Only Standard store, Only Legacy store.
 * External database log not supported at this stage.

 * @global type $DB
 * @param moodleform $mform
 * @param DateTime $dt
 * @return SQL $result
 */
function get_results($mform, $dt) {
    global  $DB;
    // Get list of readers.
    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers();

    $uselegacyreader = false;
    $useinternalreader = false;
    $externaldb = false;

    // Get preferred reader.
    if (!empty($readers)) {

        foreach ($readers as $readerpluginname => $reader) {
            // If sql_internal_table_reader is preferred reader.
            if ($reader instanceof \core\log\sql_internal_table_reader) {
                $useinternalreader = true;
                $logtable = $reader->get_internal_log_table_name();
                $readername = $reader->get_name();
            }
            // If legacy reader is preferred reader.
            if ($readerpluginname == 'logstore_legacy') {
                $uselegacyreader = true;
                $legacyname = $reader->get_name();
            }

            if ($readerpluginname == 'logstore_database') {
                $externaldb = true;
                $extname = $reader->get_name();
            }

        }
    }

    // Only external DB log is enabled.
    if ($externaldb && !$uselegacyreader && !$useinternalreader) {
        display_alert_danger_message($extname .' '.  get_string('externalognotsupported', 'report_ipsearch'));
        return;
    }

    // If no legacy and no internal log then don't proceed.
    if (!$uselegacyreader && !$useinternalreader) {
        display_alert_danger_message(get_string('enabledlogstore', 'report_ipsearch'));
        return;

    } else if ( $uselegacyreader && $useinternalreader) { // Search in both tables.

        $query = 'SELECT distinct firstname,lastname, mdl_user.id, lastaccess
                   FROM mdl_user  INNER JOIN (SELECT userid FROM mdl_logstore_standard_log
                                              WHERE (timecreated BETWEEN ? AND ?) AND ip = ? AND origin = ?) as stdlog
                    ON   mdl_user.id = stdlog.userid
                    LEFT JOIN (SELECT userid
                               FROM mdl_log
                               WHERE  (time BETWEEN ? AND ? ) AND ip = ?) AS usr
                    ON  mdl_user.id = usr.userid
                    WHERE lastaccess != 0
                    ORDER BY firstname';

        $params = array ($mform->datefrom, $dt->getTimestamp(), $mform->address, "web",
        $mform->datefrom, $dt->getTimestamp(), $mform->address);

        return $DB->get_records_sql($query, $params);
    }

    if ($useinternalreader) {

        $query = 'SELECT distinct mdl_user.id, firstname, lastname, lastaccess
                    FROM mdl_user  INNER JOIN (SELECT distinct userid, origin
                                               FROM {' . $logtable . '}
                                               WHERE (timecreated BETWEEN ? AND ? ) AND ip = ? AND origin = ? ) AS users
                                      ON  mdl_user.id = users.userid
                    WHERE lastaccess != 0
                    ORDER BY firstname';

        $params = array ($mform->datefrom, $dt->getTimestamp(), $mform->address, "web");

        $r1 = $DB->get_records_sql($query, $params);
        return $DB->get_records_sql($query, $params);
    }

    if ($uselegacyreader) { // Legacy reader save records in log table.

        $query = 'SELECT distinct mdl_user.id, firstname, lastname, lastaccess
                    FROM mdl_user  INNER JOIN (SELECT distinct userid
                                               FROM mdl_log
                                               WHERE (time BETWEEN ? AND ? ) AND ip = ?) AS users
                                      ON  mdl_user.id = users.userid
                    WHERE lastaccess != 0
                    ORDER BY firstname';

        $params = array ($mform->datefrom, $dt->getTimestamp(), $mform->address);
        return $DB->get_records_sql($query, $params);
    }
}

/**
 * @global type $OUTPUT
 * @param type $message
 */
function display_alert_danger_message($message) {
    global $OUTPUT;
    echo html_writer::start_div('alert alert-danger');
    echo $message;
    echo html_writer::end_div();
    echo $OUTPUT->footer();
}

/**
 * @global type $OUTPUT
 * @param type $message
 */
function display_alert_info_message($message) {
    global $OUTPUT;
    echo html_writer::start_div('alert alert-info');
    echo $message;
    echo html_writer::end_div();
    echo $OUTPUT->footer();
}
