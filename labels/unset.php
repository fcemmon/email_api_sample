<?php
  require_once('../settings.php');
  require_once('../api.php');

  error_log('initializing label unsetter');

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    tcrm_api_bad_request('wrong request method');
  }
  
  $params = array(
    'idCard' => @$_POST['idCard'],
    'token' => @$_POST['token']
  );
  
  if (!$params['idCard'] || !$params['token']) {
    tcrm_api_bad_request('missing parameter');
  }
  
  $tparams = 'key=' . $settings['t']['key'] . '&token=' . $params['token'];

  error_log('getting list of applied labels');

  $card = api_call('GET', 'https://api.trello.com/1/cards/' . $params['idCard'] . '?' . $tparams . '&fields=idLabels', true);
  
  $labelCount = count(@$card['idLabels']);
  
  error_log('unsetting ' . $labelCount . ' labels on ' . $params['idCard']);
  
  $errorCount = 0;
  
  foreach ($card['idLabels'] as $idLabel) {
    error_log('unsetting label : ' . $idLabel);
    
    $r = api_call('DELETE', 'https://api.trello.com/1/cards/' . $params['idCard'] . '/idLabels/' . $idLabel . '?' . $tparams, true);
    
    if (!$r) {
      error_log('error unsetting ' . $idLabel);
      $errorCount++;
    }
  }
  
  error_log('finished unsetting labels');
  
  if ($errorCount && $errorCount === $labelCount) {
    tcrm_api_server_error();
  } else {
    tcrm_api_output_json(true);
  }
?>