<?php

///FIXME. Mike: should be more checks, at least a confirmation click.
//if ($vars['action'] == "expunge" && $_SESSION['userlevel'] >= '10')
//{
//  dbFetchCell("TRUNCATE TABLE `syslog`");
//  print_message('Syslog truncated');
//}

print_optionbar_start();

$pagetitle[] = 'Syslog';

?>

<form method="post" action="" class="form-inline">
  <span style="font-weight: bold;">Syslog</span> &#187;

  <div class="input-prepend" style="margin-right: 3px;">
    <span class="add-on">Message</span>
    <input type="text" name="message" id="message" class="input" value="<?php echo($vars['message']); ?>" />
  </div>

    <div class="input-prepend" style="margin-right: 3px;">
    <span class="add-on">Priority</span>
    <select name="priority" id="priority" style="width: 140px;">
      <?php
      $priorities = syslog_priorities();
      $string = '      <option value="">All Priorities</option>';
      for($i = 0; $i <= 7; $i++)
      {
        $string .= '<option value="' . $i . '"';
        $string .= ($vars['priority'] === "$i") ? ' selected>' : '>';
        $string .= '(' . $i . ') ' . $priorities[$i]['name'] . '</option>' . PHP_EOL;
      }
      echo $string;
      ?>
    </select>
  </div>

  <div class="input-prepend" style="margin-right: 3px;">
    <span class="add-on">Program</span>
    <select name="program" id="program" style="width: 140px;">
      <option value="">All Programs</option>
      <?php
        $where = ($vars['device']) ? 'WHERE `device_id` = ' . $vars['device'] : '';
        foreach (dbFetchRows('SELECT `program` FROM `syslog` ' . $where . ' GROUP BY `program` ORDER BY `program`') as $data)
        {
          $data['program'] = ($data['program'] === '') ? '[[EMPTY]]' : $data['program'];
          echo('<option value="' . $data['program'] . '"');
          if ($data['program'] === $vars['program']) { echo(' selected'); }
          echo('>' . $data['program'] . '</option>');
        }
      ?>
    </select>
  </div>
  
  <div class="input-prepend" style="margin-right: 3px;">
    <span class="add-on">Device</span>
    <select name="device" id="device" style="width: 140px;">
      <option value="">All Devices</option>
      <?php
        // Show devices only with syslog messages
        $devices = dbFetchRows('SELECT S.`device_id` AS `device_id`, hostname FROM `syslog` AS S JOIN `devices` AS D ON S.device_id = D.device_id GROUP BY `hostname` ORDER BY `hostname`');
        foreach ($devices as $data)
        {
          if (!isset($cache['devices']['hostname'][$data['hostname']])) { continue; } // Hack for show only permitted devices
          if ($cache['devices']['id'][$data['device_id']]['disabled'] && !$config['web_show_disabled']) { continue; }
          echo('<option value="' . $data['device_id'] . '"');
          if ($data['device_id'] == $vars['device']) { echo('selected'); }
          echo('>' . $data['hostname'] . '</option>');
        }
      ?>
    </select>
  </div>
  <input type="hidden" name="pageno" value="1">
  <button type="submit" class="btn"><i class="icon-search"></i> Search</button>
</form>

<?php

print_optionbar_end();

// Pagination
$vars['pagination'] = TRUE;
if(!$vars['pagesize']) { $vars['pagesize'] = 100; }
if(!$vars['pageno']) { $vars['pageno'] = 1; }

// Print syslog
print_syslogs($vars);

?>
