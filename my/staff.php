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
 * My Moodle -- a user's personal dashboard
 *
 * - each user can currently have their own page (cloned from system and then customised)
 * - only the user can see their own dashboard
 * - users can add any blocks they want
 * - the administrators can define a default site dashboard for users who have
 *   not created their own dashboard
 *
 * This script implements the user's view of the dashboard, and allows editing
 * of the dashboard.
 *
 * @package    moodlecore
 * @subpackage my
 * @copyright  2010 Remote-Learner.net
 * @author     Hubert Chathi <hubert@remote-learner.net>
 * @author     Olav Jordan <olav.jordan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include(dirname(__FILE__) . '/../config.php');
if (strpos($USER->email, '@student.conel.ac.uk') === false) {
} else {
    header('location: student.php');
    exit;
}
include_once($CFG->dirroot . '/my/lib.php');

redirect_if_major_upgrade_required();

// TODO Add sesskey check to edit
$edit   = optional_param('edit', null, PARAM_BOOL);    // Turn editing on and off

require_login();

/* Banners */
include($CFG->dirroot . '/theme/conel/banners/Banners.class.php');
$audience = 1; // staff
$banners = new Banners($audience); 
$banners_exist = $banners->bannersExist();
$banners_found = $banners->getBanners();
$audience_name = ucfirst($banners->getAudiencePath($audience));
/* //Banners */

//$strmymoodle = get_string('myhome');
$strmymoodle = 'My home - Staff';

if (isguestuser()) {  // Force them to see system default, no editing allowed
    $userid = NULL; 
    $USER->editing = $edit = 0;  // Just in case
    $context = get_context_instance(CONTEXT_SYSTEM);
    $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');  // unlikely :)
    $header = "$SITE->shortname: $strmymoodle (GUEST)";

} else {        // We are trying to view or edit our own My Moodle page
    $userid = $USER->id;  // Owner of the page
    $context = get_context_instance(CONTEXT_USER, $USER->id);
    $PAGE->set_blocks_editing_capability('moodle/my:manageblocks');
    $header = "$SITE->shortname: $strmymoodle";
}

// Get the My Moodle page info.  Should always return something unless the database is broken.
if (!$currentpage = my_get_page($userid, MY_PAGE_PRIVATE)) {
    print_error('mymoodlesetup');
}

if (!$currentpage->userid) {
    $context = get_context_instance(CONTEXT_SYSTEM);  // So we even see non-sticky blocks
}

// Start setting up the page
$params = array();
$PAGE->set_context($context);
$PAGE->set_url('/my/staff.php', $params);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_pagetype('my-index');
$PAGE->blocks->add_region('content');
$PAGE->set_subpage($currentpage->id);
$PAGE->set_title($header);
$PAGE->set_heading($header);

/* Banners */
$PAGE->requires->css('/lib/jquery/rotator/wt-rotator.css', true);
$PAGE->requires->js('/lib/jquery/jquery-1.7.2.min.js', true);
$PAGE->requires->js('/lib/jquery/jquery.easing.1.3.min.js', true);
$PAGE->requires->js('/lib/jquery/rotator/js/jquery.wt-rotator.min.js', true);
$PAGE->requires->js('/theme/conel/banners/js/config.js', true);

//if (!isguestuser()) {   // Skip default home page for guests
    if (get_home_page() != HOMEPAGE_MY) {
        if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
            set_user_preference('user_home_page_preference', HOMEPAGE_MY);
        } else if (!empty($CFG->defaulthomepage) && $CFG->defaulthomepage == HOMEPAGE_USER) {
            $PAGE->settingsnav->get('usercurrentsettings')->add(get_string('makethismyhome'), new moodle_url('/my/', array('setdefaulthome'=>true)), navigation_node::TYPE_SETTING);
        }
    }
//}

// Toggle the editing state and switches
if ($PAGE->user_allowed_editing()) {
    if ($edit !== null) {             // Editing state was specified
        $USER->editing = $edit;       // Change editing state
        if (!$currentpage->userid && $edit) {
            // If we are viewing a system page as ordinary user, and the user turns
            // editing on, copy the system pages as new user pages, and get the
            // new page record
            if (!$currentpage = my_copy_page($USER->id, MY_PAGE_PRIVATE)) {
                print_error('mymoodlesetup');
            }
            $context = get_context_instance(CONTEXT_USER, $USER->id);
            $PAGE->set_context($context);
            $PAGE->set_subpage($currentpage->id);
        }
    } else {                          // Editing state is in session
        if ($currentpage->userid) {   // It's a page we can edit, so load from session
            if (!empty($USER->editing)) {
                $edit = 1;
            } else {
                $edit = 0;
            }
        } else {                      // It's a system page and they are not allowed to edit system pages
            $USER->editing = $edit = 0;          // Disable editing completely, just to be safe
        }
    }

    // Add button for editing page
    $params = array('edit' => !$edit);

    if (!$currentpage->userid) {
        // viewing a system page -- let the user customise it
        $editstring = get_string('updatemymoodleon');
        $params['edit'] = 1;
    } else if (empty($edit)) {
        $editstring = get_string('updatemymoodleon');
    } else {
        $editstring = get_string('updatemymoodleoff');
    }

    $url = new moodle_url("$CFG->wwwroot/my/staff.php", $params);
    $button = $OUTPUT->single_button($url, $editstring);
    $PAGE->set_button($button);

} else {
    $USER->editing = $edit = 0;
}

// HACK WARNING!  This loads up all this page's blocks in the system context
if ($currentpage->userid == 0) {
    $CFG->blockmanagerclass = 'my_syspage_block_manager';
}

echo $OUTPUT->header();

//echo $OUTPUT->blocks_for_region('content');
?>
<h2>News</h2>
<?php if ($banners_exist === true) { ?>
<div class="container">
    <div class="wt-rotator">
        <div class="screen"><noscript><img src="<?php echo $banners_found[0]['img_url']; ?>" alt="" /></noscript></div>
        <div class="c-panel">
            <div class="buttons"><div class="prev-btn"></div><div class="play-btn"></div><div class="next-btn"></div></div>
            <div class="thumbnails">
                <ul>
                <?php foreach ($banners_found as $ban) {
                    echo '<li><a href="'.$ban['img_url'].'"><img src="'.$ban['img_url'].'" alt="Banner" width="495" height="185" /></a><a href="'.$ban['link'].'"></a></li>' . PHP_EOL;
                } ?>
                </ul>
            </div>     
        </div><!-- // c-panel -->
    </div><!-- // wt-rotator -->
</div><!-- // container -->

<?php 
} else {
    echo '<p>No banners have been added yet.</p>';
}
if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
    echo '<p style="text-align:right;"><a href="/theme/conel/banners/index.php?audience=1">Edit '.$audience_name.' Banners</a></p>';
}
?>

<h2>Staff Links</h2>
<table id="staff_links" border="0" cellspacing="0" cellpadding="0">
    <tr>
    <td><a href="http://ebs4agent.conel.ac.uk/" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-ebs4', 'theme'); ?>" width="128" height="80" alt="EBS4" /><br />ebs4 Agent</a></td>
    <td><a href="https://clg.conel.ac.uk/" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-connect', 'theme'); ?>" width="128" height="80" alt="Connect" /><br />Connect</a></td>
    <td><a href="https://clg.conel.ac.uk/email" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-email', 'theme'); ?>" width="129" height="80" alt="Email" /><br />Email</a></td>
    <td><a href="http://rm.conel.ac.uk/index.asp" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-timetabling', 'theme'); ?>" width="117" height="80" alt="Timetabling" /><br />Timetabling</a></td>
    </tr>
    <tr>
    <!--td><a href="http://www.google.co.uk/" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-google', 'theme'); ?>" width="128" height="74" alt="Google" /><br />Google</a></td-->
    <td><a href="http://ldmis-app/ProMonitor/" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-promonitor', 'theme'); ?>" width="177" height="48" alt="Pro monitor" /><br />Pro monitor</a></td>
    <td><a href="http://www.conel.ac.uk/" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-conel', 'theme'); ?>" width="120" height="74" alt="College Website" /><br />College Website</a></td>
    <!--td><a href="/course/category.php?id=51" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-good-teaching', 'theme'); ?>" width="129" height="74" alt="Good Teaching and Learning" /><br />Good Teaching<br /> &amp; Learning</a></td-->
    <td><a href="/course/category.php?id=340" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-good-teaching', 'theme'); ?>" width="129" height="74" alt="Good Teaching and Learning" /><br />Good Teaching<br /> &amp; Learning</a></td>
    <td><a href="/course/category.php?id=343" target="_blank"><img src="<?php echo $OUTPUT->pix_url('staff/icon-staff-training2', 'theme'); ?>" width="117" height="74" alt="Staff Training Tutorials" /><br />Staff Training Tutorials</a></td>
    </tr>
</table>

<br />

<?php
echo $OUTPUT->footer();
