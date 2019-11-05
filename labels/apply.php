<?php
  require_once('../settings.php');
  require_once('../api.php');

  error_log('relabling all cards');

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    tcrm_api_bad_request('wrong request method');
  }
  
  $params = array(
    'idBoard' => @$_POST['idBoard'],
    'token' => @$_POST['token']
  );
  
  error_log('PARAMS : ' . print_r($params, true));  
  
  if (!$params['idBoard'] || !$params['token']) {
    tcrm_api_bad_request('missing parameter');
  }

  $people = api_call('GET', $settings['baseurl'] . 'api/people/?tcrmkey=' . $settings['tcrmkey'] . '&idBoard=' . $params['idBoard'], true);

  $results = array();

  foreach ($people as $p) {
    $labelParams = array(
      'idBoard' => $p['idBoard'],
      'idList' => $p['idList'],
      'idCard' => $p['idCard'],
      'days' => (int)(floor((time() - $p['timestamp']) / 60 / 60 / 24))
    );
    $results[] = api_call('POST', $settings['baseurl'] . 'api/labels/resolve.php?tcrmkey=' . $settings['tcrmkey'], $labelParams, true);
  }

  tcrm_api_output_json($results);
?>