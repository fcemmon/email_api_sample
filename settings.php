<?php

  $continous = true;

  if (!@$continous) {
    if (file_put_contents('error_log', '')) {
      $errorlog = realpath('error_log');
      ini_set('error_log', $errorlog);
      ini_set('log_errors', 1);
    }
  }
  date_default_timezone_set("UTC");
  $settings = json_decode(file_get_contents('/Volumes/Data/git_projects/TrelloCRM/trellocrm/my/res/settings.json'), true);
  
  $settings['t']['secret'] = file_get_contents('/Volumes/Data/git_projects/TrelloCRM/trellocrm/secret/trello');
  $settings['g']['secret'] = file_get_contents('/Volumes/Data/git_projects/TrelloCRM/trellocrm/secret/google');
  
  $settings['tcrmkey'] = 'russellisagod';
  $settings['adminkey'] = sha1('supersecretadminkeyfortrellocrmusermanagementconsole');
  // $settings['baseurl'] = 'https://my.trellocrm.com/';
  $settings['baseurl'] = 'http://dev.trellocrm.com/my/';
?>