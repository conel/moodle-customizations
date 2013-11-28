<?php

/**
 * Clean running status of automated backup i
 * if it execution was interrupted
 * (for example using ctrl + c from command line)
 *
 * @package    core
 * @subpackage backup
 * @copyright  2013 Szilard Szabo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->libdir.'/cronlib.php');
require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot.'/backup/util/helper/backup_cron_helper.class.php');

backup_cron_automated_helper::set_state_running(false);
