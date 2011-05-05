#!/usr/bin/env php
<?php

/* Observium Network Management and Monitoring System
 * Copyright (C) 2006-2011, Observium Developers - http://www.observium.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See COPYING for more details.
 */

include("includes/defaults.inc.php");
include("config.php");
include("includes/functions.php");
include("includes/discovery/functions.inc.php");

$start = utime();
$runtime_stats = array();

### Observium Device Discovery

echo("Observium v".$config['version']." Discovery\n\n");

$options = getopt("h:m:i:n:d::a::");

if (isset($options['h']))
{
  if ($options['h'] == "odd")    { $options['n'] = "1"; $options['i'] = "2"; }
  elseif ($options['h'] == "even") { $options['n'] = "0"; $options['i'] = "2"; }
  elseif ($options['h'] == "all")  { $where = " "; $doing = "all"; }
  elseif ($options['h'] == "new")  { $where = "AND `last_discovered` IS NULL"; $doing = "new"; }
  elseif ($options['h'])
  {
    if (is_numeric($options['h']))
    {
      $where = "AND `device_id` = '".$options['h']."'";
      $doing = $options['h'];
    }
    else
    {
      $where = "AND `hostname` LIKE '".str_replace('*','%',mres($options['h']))."'";
      $doing = $options['h'];
    }
  }
}

if (isset($options['i']) && $options['i'] && isset($options['n']))
{
  $where = "AND MOD(device_id,".$options['i'].") = '" . $options['n'] . "'";
  $doing = $options['n'] ."/".$options['i'];
}

if (isset($options['d']))
{
  echo("DEBUG!\n");
  $debug = TRUE;
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  ini_set('log_errors', 1);
  ini_set('error_reporting', 1);
} else {
  $debug = FALSE;
  #  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
  ini_set('log_errors', 0);
  #  ini_set('error_reporting', 0);
}


if (!$where)
{
  echo("-h <device id> | <device hostname wildcard>  Poll single device\n");
  echo("-h odd                                       Poll odd numbered devices  (same as -i 2 -n 0)\n");
  echo("-h even                                      Poll even numbered devices (same as -i 2 -n 1)\n");
  echo("-h all                                       Poll all devices\n");
  echo("-h new                                       Poll all devices that have not had a discovery run before\n\n");
  echo("-i <instances> -n <number>                   Poll as instance <number> of <instances>\n");
  echo("                                             Instances start at 0. 0-3 for -n 4\n\n");
  echo("\n");
  echo("Debugging and testing options:\n");
  echo("-d                                           Enable debugging output\n");
  echo("-m                                           Specify single module to be run\n");
  echo("\n");
  echo("Invalid arguments!\n");
  exit;
}

if (file_exists('.svn'))
{
  list(,$dbu_rev) = preg_split('/: /',@shell_exec('svn info database-update.sql|grep ^Revision'));

  $device_query = mysql_query("SELECT revision FROM `dbSchema`");
  if ($rev = @mysql_fetch_assoc($device_query))
  {
    $db_rev = $rev['revision'];
  }
  else
  {
    $db_rev = 0;
  }

  if ($db_rev+0 < 1223)
  {
    include('upgrade-scripts/fix-events.php'); ## Fix events table (needs to copy some data around, so needs script)
  }

  if ($db_rev+0 < 1656)
  {
    include('upgrade-scripts/fix-port-rrd.php'); ## Rewrites all port RRDs. Nothing will work without this after 1656
  }

  if ($db_rev+0 < 1757)
  {
    include('upgrade-scripts/fix-sensor-rrd.php'); ## Rewrites all sensor RRDs. Nothing will work without this after 1757
  }

  if ($dbu_rev+0 > $db_rev)
  {
    echo("SVN revision changed.\n");
    if ($db_rev+0 < "1000")
    {
      echo("Running pre-revision 1000 SQL update script...\n");
      shell_exec("scripts/update-sql.php database-update-pre1000.sql");
    }
    if ($db_rev+0 < "1435")
    {
      echo("Running pre-revision 1435 SQL update script...\n");
      shell_exec("scripts/update-sql.php database-update-pre1435.sql");
    }
    if ($db_rev+0 < "2245")
    {
      echo("Running pre-revision 2245 (0.11.5) SQL update script...\n");
      shell_exec("scripts/update-sql.php database-update-pre2245.sql");
    }
    echo("Running development SQL update script to update from r$db_rev to r" . trim($dbu_rev) . "...\n");
    shell_exec("scripts/update-sql.php database-update.sql");
    if ($db_rev == 0)
    {
      mysql_query("INSERT INTO dbSchema VALUES ($dbu_rev)");
    }
    else
    {
      mysql_query("UPDATE dbSchema set revision=$dbu_rev");
    }
  }
}

$discovered_devices = 0;

$device_query = mysql_query("SELECT * FROM `devices` WHERE status = 1 AND disabled = 0 $where ORDER BY device_id DESC");
while ($device = mysql_fetch_assoc($device_query))
{
  discover_device($device, $options);
}

function discover_device($device, $options) 
{

  global $config;
  global $valid; $valid = array(); ## Reset $valid array

  $device_start = utime();  // Start counting device poll time

  echo($device['hostname'] . " ".$device['device_id']." ".$device['os']." ");
  if ($device['os'] != strtolower($device['os']))
  {
    mysql_query("UPDATE `devices` SET `os` = '".strtolower($device['os'])."' WHERE device_id = '".$device['device_id']."'");
    $device['os'] = strtolower($device['os']);
    echo("OS lowercased.");
  }
  if ($config['os'][$device['os']]['group'])
  {
    $device['os_group'] = $config['os'][$device['os']]['group'];
    echo("(".$device['os_group'].")");
  }

  echo("\n");

  #include("includes/discovery/os.inc.php");

  ### If we've specified a module, use that, else walk the modules array

  if ($options['m'])
  {
    if (is_file("includes/discovery/".$options['m'].".inc.php"))
    {
      include("includes/discovery/".$options['m'].".inc.php");
    }
  } else {
    foreach($config['discovery_modules'] as $module => $module_status)
    {
      if($module_status || $device_attribs['discovery_module'][$module])
      {
        include('includes/discovery/'.$module.'.inc.php');
      }
    }
  }

  ### Set type to a predefined type for the OS if it's not already set

  if ($device['type'] == "unknown" || $device['type'] == "")
  {
    if ($config['os'][$device['os']]['type'])
    {
      $device['type'] = $config['os'][$device['os']]['type'];
    }
  }

  $device_end = utime(); $device_run = $device_end - $device_start; $device_time = substr($device_run, 0, 5);

  $update_query  = "UPDATE `devices` SET ";
  $update_query .= " `last_discovered` = NOW(), `type` = '" . $device['type'] . "'";
  $update_query .= ", `last_discovered_timetaken` = '$device_time'";
  $update_query .= " WHERE `device_id` = '" . $device['device_id'] . "'";
  $update_result = mysql_query($update_query);

  echo("Discovered in $device_time seconds\n");

  global $discovered_devices;

  echo("\n"); $discovered_devices++;
}

$end = utime(); $run = $end - $start;
$proctime = substr($run, 0, 5);

if ($discovered_devices)
{
  mysql_query("INSERT INTO `perf_times` (`type`, `doing`, `start`, `duration`, `devices`)
    VALUES ('discover', '$doing', '$start', '$proctime', '$discovered_devices')");
}

$string = $argv[0] . " $doing " .  date("F j, Y, G:i") . " - $discovered_devices devices discovered in $proctime secs";
if ($debug) echo("$string\n");

logfile($string);

?>
