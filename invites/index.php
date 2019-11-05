<?php
  require_once('../settings.php');
  require_once('../db.php');
  require_once('../api.php');

//  if ($origin != "https://admin.trellocrm.com") {
  if (@$_GET['tcrmkey'] != $settings['adminkey']) {
    error_log('unauthorized access prevented');
    tcrm_api_unauthorized();
  }

  $params = array(
    'code' => (isset($_POST['code'])) ? $_POST['code'] : @$_GET['code'],
    'count' => (isset($_POST['count'])) ? $_POST['count'] : @$_GET['count']
  );

  error_log('REQUEST METHOD : ' . $_SERVER['REQUEST_METHOD']);
  error_log('PARAMS : ' . print_r($params, true));

  if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    tcrm_api_bad_request('wrong request type');
  }

  // REVOKE REMAINING INVITES FOR SPECIFIED CODE
  if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    if (!$params['code']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $q = $db->prepare('delete from users where invite_code = :code and email is null and google_refresh_token is null and trello_token is null');
    $qp = array(
      ':code' => $params['code']
    );
    $q->execute($qp);
    $c = $q->rowCount();
    if (!$c) {
      tcrm_api_server_error('no unused accounts found for invite code ' . $params['code']);
    }
    $r = array(
      'status' => 'success',
      'message' => 'successfully revoked ' . $c . ' remaining invites for ' . $params['code'],
      'code' => $params['code'],
      'count'=> $c
    );
  }

  // GENERATE RANDOM INVITE CODE
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $x = array();
    do {
      $code = md5(rand());
      $q = $db->prepare('select * from users where invite_code = :code');
      $qp = array(':code' => $code);
      $q->execute($qp);
      $x = $q->fetchAll();
    } while (count($x));
    
    $r = array(
      'code' => $code
    );
  }

  // CREATE X ACCOUNTS WITH SPECIFIED CODE
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$params['code']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    if (!$params['count']) {
      $params['count'] = 1;
    }
    
    $users = array();
    $i = 1;
    
    while ($i <= $params['count']) {
      $user = api_call('PUT', $settings['baseurl'] . 'api/users?tcrmkey=' . $settings['tcrmkey'] . '&invite_code=' . $params['code'], null, true);
      $users[] = array(
        'userid' => $user['userid']
      );
      $i++;
    }
    
    if (!count($users)) {
      tcrm_api_server_error('could not activate invite code ' . $params['code']);
    }
    
    $r = array(
      'created' => $users
    );
  }

  // VERIFY OPERATIONS & OUTPUT RESULTS
  $lastop = db_verify_last_operation();
  if ($lastop !== true) {
    tcrm_api_server_error($lastop);
  } else {
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
      tcrm_api_created($r);
    } else {
      tcrm_api_output_json($r);
    }
  }
?>