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
 * The main group management user interface.
 *
 * @copyright 2006 The Open University, N.D.Freear AT open.ac.uk, J.White AT open.ac.uk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   core_group
 */
require_once('../config.php');
require_once('lib.php');

//nkowald - 2010-01-20
/**
 * Create groups in a meta course from its child courses, filled with users of passed role id(s)
 * Created groups get their name from the child course (fullname)
 * This can be run from the cron to daily update members/create groups for all meta courses
 *
 * @param object    $course    course object
 * @param int/array $roles     role id(s) to set which users are added to group - defaults to students
 * @return boolean
 * @since Moodle 1.9.5 (Build 20090819)
 */
function groups_create_from_children($course, $roles = 5) {
	
	// get all child courses for current meta
	//$child_courses = get_courses_in_metacourse($course->id);
	//select customint1 as parent from mdl_enrol where enrol='meta' and courseid=3598 limit 10;
	global $DB;
	
	//print_object($course);
	
	// they are rather parent_courses
	$child_course_ids = $DB->get_records('enrol', array('enrol' => 'meta', 'courseid' => $course->id), '', 'customint1');
	
	//print_object(implode(',',array_keys($child_course_ids)));
	
	$child_courses = $DB->get_records_select('course', "id in (" . implode(',',array_keys($child_course_ids)) . ")");
	
	//print_object($child_courses);

	// delete all members of current "autocreate" groups, leave manual groups alone
	// "autocreate" = if group exists with same name as child course, remove members from these groups only
	$del_group_ids = array();
	
	foreach ($child_courses as $child) {
		
		// nkowald - 2012-02-28 - Escape single quotes
		
		//$mdl_group = $DB->get_record_select('groups', "courseid = ".$course->id." AND name = '".addslashes($child->fullname)."' " );
		
		//print "child id: $child->id<br>";
		
		$mdl_group = $DB->get_record('groups', array('courseid' => $child->id));
		
		if (is_object($mdl_group)) {
			$del_group_ids[] = $mdl_group->id;
		}
	}
	
	// if autocreate groups found
	if (count($del_group_ids) > 0) {
		$del_group_ids_csv = implode(',',$del_group_ids);
		// Group ids to exclude from member deletion
		groups_delete_group_members2($course->id, 0, false, $del_group_ids_csv);
	}

	foreach ($child_courses as $child) {
		
		// Check if groups exist with these names in this course?
		// nkowald - 2012-02-28 - Escape single quotes
		$mdl_group = $DB->get_record_select('groups', "courseid = ".$course->id." AND name = '".addslashes($child->fullname)."' " );
		
		// If group does not exist, create it
		if (!is_object($mdl_group)) {
			
			$mdl_group = new StdClass;
			
			// nkowald - 2012-02-28 - Escape single quotes
			$mdl_group->name = addslashes($child->fullname);
			$mdl_group->description = '';
			$mdl_group->courseid = $course->id;

			$group_id = groups_create_group($mdl_group);

			if (!$mdl_group = $DB->get_record('groups', array('id' => $group_id))){
				
				$error_creating_group = 'Creating group '.$child->fullname.' failed';
				
				error_log($error_creating_group);
				debugging($error_creating_group);
				continue;
			}
		}

		$context = get_context_instance(CONTEXT_COURSE, $child->id);
		
		$users_of_role = get_role_users($roles, $context, false, '', 'u.firstname ASC');
				
		// If user of given role type exist in child course add them to new group - sort by firstname
		if ($users_of_role = get_role_users($roles, $context, false, '', 'u.firstname ASC')) {
			foreach ($users_of_role as $user) {
				groups_add_member($mdl_group->id, $user->id);
			}
		}
		
	} //foreach child course
	
	return true;
}

$courseid = required_param('id', PARAM_INT);
$groupid  = optional_param('group', false, PARAM_INT);
$userid   = optional_param('user', false, PARAM_INT);
$action   = groups_param_action();
// Support either single group= parameter, or array groups[]
if ($groupid) {
    $groupids = array($groupid);
} else {
    $groupids = optional_param_array('groups', array(), PARAM_INT);
}
$singlegroup = (count($groupids) == 1);

$returnurl = $CFG->wwwroot.'/group/index.php?id='.$courseid;

// Get the course information so we can print the header and
// check the course id is valid

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

$url = new moodle_url('/group/index.php', array('id'=>$courseid));
if ($userid) {
    $url->param('user', $userid);
}
if ($groupid) {
    $url->param('group', $groupid);
}
$PAGE->set_url($url);

// Make sure that the user has permissions to manage groups.
require_login($course);

$context = context_course::instance($course->id);
require_capability('moodle/course:managegroups', $context);

$PAGE->requires->js('/group/clientlib.js');

// Check for multiple/no group errors
if (!$singlegroup) {
    switch($action) {
        case 'ajax_getmembersingroup':
        case 'showgroupsettingsform':
        case 'showaddmembersform':
        case 'updatemembers':
            print_error('errorselectone', 'group', $returnurl);
    }
}

switch ($action) {
    case false: //OK, display form.
        break;

    case 'ajax_getmembersingroup':
        $roles = array();
        if ($groupmemberroles = groups_get_members_by_role($groupids[0], $courseid, 'u.id, u.firstname, u.lastname')) {
            foreach($groupmemberroles as $roleid=>$roledata) {
                $shortroledata = new stdClass();
                $shortroledata->name = $roledata->name;
                $shortroledata->users = array();
                foreach($roledata->users as $member) {
                    $shortmember = new stdClass();
                    $shortmember->id = $member->id;
                    $shortmember->name = fullname($member, true);
                    $shortroledata->users[] = $shortmember;
                }
                $roles[] = $shortroledata;
            }
        }
        echo json_encode($roles);
        die;  // Client side JavaScript takes it from here.

    case 'deletegroup':
        if (count($groupids) == 0) {
            print_error('errorselectsome','group',$returnurl);
        }
        $groupidlist = implode(',', $groupids);
        redirect(new moodle_url('/group/delete.php', array('courseid'=>$courseid, 'groups'=>$groupidlist)));
        break;

    case 'showcreateorphangroupform':
        redirect(new moodle_url('/group/group.php', array('courseid'=>$courseid)));
        break;

    case 'showautocreategroupsform':
        redirect(new moodle_url('/group/autogroup.php', array('courseid'=>$courseid)));
        break;

    case 'showimportgroups':
        redirect(new moodle_url('/group/import.php', array('id'=>$courseid)));
        break;

    case 'showgroupsettingsform':
        redirect(new moodle_url('/group/group.php', array('courseid'=>$courseid, 'id'=>$groupids[0])));
        break;

    case 'updategroups': //Currently reloading.
        break;

	// nkowald - 2010-01-20 - This code will trigger function to set up groups from child courses
	case 'setupgroups':
		groups_create_from_children($course, 5);
		break;
		
    case 'removemembers':
        break;

    case 'showaddmembersform':
        redirect(new moodle_url('/group/members.php', array('group'=>$groupids[0])));
        break;

    case 'updatemembers': //Currently reloading.
        break;

    default: //ERROR.
        print_error('unknowaction', '', $returnurl);
        break;
}

// Print the page and form
$strgroups = get_string('groups');
$strparticipants = get_string('participants');

/// Print header
$PAGE->set_title($strgroups);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('admin');
echo $OUTPUT->header();

// Add tabs
$currenttab = 'groups';
require('tabs.php');

$disabled = 'disabled="disabled"';
if (ajaxenabled()) {
    // Some buttons are enabled if single group selected
    $showaddmembersform_disabled = $singlegroup ? '' : $disabled;
    $showeditgroupsettingsform_disabled = $singlegroup ? '' : $disabled;
    $deletegroup_disabled = count($groupids)>0 ? '' : $disabled;
} else {
    // Do not disable buttons. The buttons work based on the selected group,
    // which you can change without reloading the page, so it is not appropriate
    // to disable them if no group is selected.
    $showaddmembersform_disabled = '';
    $showeditgroupsettingsform_disabled = '';
    $deletegroup_disabled = '';
}

echo $OUTPUT->heading(format_string($course->shortname, true, array('context' => $context)) .' '.$strgroups, 3);
echo '<form id="groupeditform" action="index.php" method="post">'."\n";
echo '<div>'."\n";
echo '<input type="hidden" name="id" value="' . $courseid . '" />'."\n";

echo '<table cellpadding="6" class="generaltable generalbox groupmanagementtable boxaligncenter" summary="">'."\n";
echo '<tr>'."\n";


echo "<td>\n";
echo '<p><label for="groups"><span id="groupslabel">'.get_string('groups').':</span><span id="thegrouping">&nbsp;</span></label></p>'."\n";

if (ajaxenabled()) { // TODO: move this to JS init!
    $onchange = 'M.core_group.membersCombo.refreshMembers();';
} else {
    $onchange = '';
}

echo '<select name="groups[]" multiple="multiple" id="groups" size="15" class="select" onchange="'.$onchange.'"'."\n";
echo ' onclick="window.status=this.selectedIndex==-1 ? \'\' : this.options[this.selectedIndex].title;" onmouseout="window.status=\'\';">'."\n";

$groups = groups_get_all_groups($courseid);
$selectedname = '&nbsp;';
$preventgroupremoval = array();

if ($groups) {
    // Print out the HTML
    foreach ($groups as $group) {
        $select = '';
        $usercount = $DB->count_records('groups_members', array('groupid'=>$group->id));
        $groupname = format_string($group->name).' ('.$usercount.')';
        if (in_array($group->id,$groupids)) {
            $select = ' selected="selected"';
            if ($singlegroup) {
                // Only keep selected name if there is one group selected
                $selectedname = $groupname;
            }
        }
        if (!empty($group->idnumber) && !has_capability('moodle/course:changeidnumber', $context)) {
            $preventgroupremoval[$group->id] = true;
        }

        echo "<option value=\"{$group->id}\"$select title=\"$groupname\">$groupname</option>\n";
    }
} else {
    // Print an empty option to avoid the XHTML error of having an empty select element
    echo '<option>&nbsp;</option>';
}

echo '</select>'."\n";
echo '<p><input type="submit" name="act_updatemembers" id="updatemembers" value="'
        . get_string('showmembersforgroup', 'group') . '" /></p>'."\n";
echo '<p><input type="submit" '. $showeditgroupsettingsform_disabled . ' name="act_showgroupsettingsform" id="showeditgroupsettingsform" value="'
        . get_string('editgroupsettings', 'group') . '" /></p>'."\n";
echo '<p><input type="submit" '. $deletegroup_disabled . ' name="act_deletegroup" id="deletegroup" value="'
        . get_string('deleteselectedgroup', 'group') . '" /></p>'."\n";

echo '<p><input type="submit" name="act_showcreateorphangroupform" id="showcreateorphangroupform" value="'
        . get_string('creategroup', 'group') . '" /></p>'."\n";

echo '<p><input type="submit" name="act_showautocreategroupsform" id="showautocreategroupsform" value="'
        . get_string('autocreategroups', 'group') . '" /></p>'."\n";

echo '<p><input type="submit" name="act_showimportgroups" id="showimportgroups" value="'
        . get_string('importgroups', 'core_group') . '" /></p>'."\n";

// sszabo - 12/07/2013
// Check if current course is a "meta" course (so it has no idnumber)
$tst = $DB->count_records("course", array("id" => $course->id, "idnumber" => ''));
if ($tst===1) {	 
	echo '<p><input type="submit" name="act_setupgroups" id="setupgroups" value="Create groups from child courses" /></p>';
}
// sszabo - 12/07/2013

echo '</td>'."\n";
echo '<td>'."\n";

echo '<p><label for="members"><span id="memberslabel">'.
    get_string('membersofselectedgroup', 'group').
    ' </span><span id="thegroup">'.$selectedname.'</span></label></p>'."\n";
//NOTE: the SELECT was, multiple="multiple" name="user[]" - not used and breaks onclick.
echo '<select name="user" id="members" size="15" class="select"'."\n";
echo ' onclick="window.status=this.options[this.selectedIndex].title;" onmouseout="window.status=\'\';">'."\n";

$member_names = array();

$atleastonemember = false;
if ($singlegroup) {
    if ($groupmemberroles = groups_get_members_by_role($groupids[0], $courseid, 'u.id, u.firstname, u.lastname')) {
        foreach($groupmemberroles as $roleid=>$roledata) {
            echo '<optgroup label="'.s($roledata->name).'">';
            foreach($roledata->users as $member) {
                echo '<option value="'.$member->id.'">'.fullname($member, true).'</option>';
                $atleastonemember = true;
            }
            echo '</optgroup>';
        }
    }
}

if (!$atleastonemember) {
    // Print an empty option to avoid the XHTML error of having an empty select element
    echo '<option>&nbsp;</option>';
}

echo '</select>'."\n";

echo '<p><input type="submit" ' . $showaddmembersform_disabled . ' name="act_showaddmembersform" '
        . 'id="showaddmembersform" value="' . get_string('adduserstogroup', 'group'). '" /></p>'."\n";
echo '</td>'."\n";
echo '</tr>'."\n";
echo '</table>'."\n";

//<input type="hidden" name="rand" value="om" />
echo '</div>'."\n";
echo '</form>'."\n";

if (ajaxenabled()) {
    $PAGE->requires->js_init_call('M.core_group.init_index', array($CFG->wwwroot, $courseid));
    $PAGE->requires->js_init_call('M.core_group.groupslist', array($preventgroupremoval));
}

echo $OUTPUT->footer();

/**
 * Returns the first button action with the given prefix, taken from
 * POST or GET, otherwise returns false.
 * @see /lib/moodlelib.php function optional_param().
 * @param string $prefix 'act_' as in 'action'.
 * @return string The action without the prefix, or false if no action found.
 */
function groups_param_action($prefix = 'act_') {
    $action = false;
//($_SERVER['QUERY_STRING'] && preg_match("/$prefix(.+?)=(.+)/", $_SERVER['QUERY_STRING'], $matches)) { //b_(.*?)[&;]{0,1}/

    if ($_POST) {
        $form_vars = $_POST;
    }
    elseif ($_GET) {
        $form_vars = $_GET;
    }
    if ($form_vars) {
        foreach ($form_vars as $key => $value) {
            if (preg_match("/$prefix(.+)/", $key, $matches)) {
                $action = $matches[1];
                break;
            }
        }
    }
    if ($action && !preg_match('/^\w+$/', $action)) {
        $action = false;
        print_error('unknowaction');
    }
    ///if (debugging()) echo 'Debug: '.$action;
    return $action;
}
