<?php

// Test the database connection to ebs and lists the content of the FES.MOODLE_CURRENT_ENROLMENTS table
// which holds the enrolments that needs to be imported automatically into moodle by the Moodle Enrolments Sync
// scheduled task. It also holds the information for the automatic new course creation into the Created Courses 
// category done by the same scheduled task.

require('../../config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once($CFG->libdir.'/adodb/adodb.inc.php');

// Connect to the external database (forcing new connection).
$extdb = ADONewConnection('oci8');

$extdb->debug = true;

// The dbtype my contain the new connection URL, so make sure we are not connected yet.
if (!$extdb->IsConnected()) {
	$result = $extdb->Connect('ebs.conel.ac.uk', 'ebsmoodle', '82814710', 'fs1', true);
	if (!$result) {
		exit('Failed to connect to database.');
	}
}

$extdb->SetFetchMode(ADODB_FETCH_ASSOC);

//print_r($extdb);

$sql = "select * from FES.MOODLE_CURRENT_ENROLMENTS";

//print_r ($rs);

$count = 0;

if ($rs = $extdb->Execute($sql)) {
	if (!$rs->EOF) {
		while ($fields = $rs->FetchRow()) {
			print '<pre>'; 
			print_r($fields);
			print '</pre>';
			print '<br><br><br>-------------------------------------------<br><br><br>';
			$count++;
		}
	}
} else {
    $extdb->Close();
	print 'Error reading data from the external course table';
}

print "result count: $count";
