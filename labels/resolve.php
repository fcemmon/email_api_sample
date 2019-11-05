<?php
  require_once('../settings.php');
  require_once('../api.php');

  error_log('initializing label resolver');

  $params = array(
    'idBoard' => @$_POST['idBoard'],
    'idList' => @$_POST['idList'],
    'idCard' => @$_POST['idCard'],
    'days' => @$_POST['days']
  );
  
  error_log('PARAMS : ' . print_r($params, true));

  if (!$params['idBoard'] || !$params['idList'] || !($params['days'] >= 0)) {
    tcrm_api_bad_request('missing parameter');
  }

  error_log('getting instance ' . $params['idBoard']);

  $instance = api_call('GET', $settings['baseurl'] . 'api/instances?tcrmkey=' . $settings['tcrmkey'] . '&idBoard=' . $params['idBoard'], true);

  if (!$instance || !@$instance['idMember']) {
    $e = array(
      'message' => 'instance record malformed',
      'result' => $instance
    ); 
    tcrm_api_server_error($e);
  }

  error_log('getting user : ' . $instance['idMember']);
  $user = api_call('GET', $settings['baseurl'] . 'api/users/?tcrmkey=' . $settings['tcrmkey'] . '&idMember=' . $instance['idMember'], true);

  if (!$user['idMember']) {
    $e = array(
      'message' => 'error getting user',
      'result' => $user
    );
    tcrm_api_server_error($e);
  }

  $instance['rules'] = json_decode(@$instance['rules'], true);
  
  error_log('available rulesets : ' . print_r($instance['rules'], true));

  $ruleset = @$instance['rules'][$params['idList']]['rules'];
  
  if (!is_array($ruleset) || !count($ruleset)) {
    error_log('no rules for ' . $params['idList']);
    error_log('falling back to default');
    $ruleset = @$instance['rules']['default']['rules'];
  }

  if (!is_array($ruleset) || !count($ruleset)) {
    $e = array(
      'message' => 'no rules to use',
      'rules' => $instance['rules']
    ); 
    tcrm_api_server_error($e);
  }

  
  ksort($ruleset);

  error_log('using ruleset : ' . print_r($ruleset, true));

  error_log('checking input against rules');
  
  foreach ($ruleset as $r) {
    if ($params['days'] >= $r['age']) {
      $newLabel = $r['label'];
    } else {
      break;
    }
  }
  
  if (!@$newLabel) {
    tcrm_api_server_error('no rules apply');
  }
  
  error_log('found a matching rule');

  error_log('unsetting existing labels');

  $r = api_call('POST', $settings['baseurl'] . 'api/labels/unset.php?tcrmkey=' . $settings['tcrmkey'], array(
    'idCard' => $params['idCard'],
    'token' => $user['trello_token']
  ));

  if (!$r) {
    tcrm_api_server_error('error unsetting label');
  }

  error_log('setting label ' . $newLabel);

  $r = api_call('POST', $settings['baseurl'] . 'api/labels/set.php', array(
    'idCard' => $params['idCard'],
    'newLabel' => $newLabel,
    'token' => $user['trello_token']
  ));

  if (!$r) {
    tcrm_api_server_error('error setting label');
  }
  
  tcrm_api_output_json(array(
    'idCard' => $params['idCard'],
    'newLabel' => $newLabel
  ));
?>