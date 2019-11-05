<?php
  require_once('../settings.php');
  require_once('../db.php');
  require_once('../api.php');

  $params = array(
    'userid' => (isset($_POST['userid'])) ? $_POST['userid'] : @$_GET['userid'],
    'active' => (isset($_POST['active'])) ? $_POST['active'] : @$_GET['active'],
    'invite_code' => (isset($_POST['invite_code'])) ? $_POST['invite_code'] : @$_GET['invite_code'],
    'idMember' => (isset($_POST['idMember'])) ? $_POST['idMember'] : @$_GET['idMember'],
    'email' => (isset($_POST['email'])) ? $_POST['email'] : @$_GET['email'],
    'grefresh' => (isset($_POST['grefresh'])) ? $_POST['grefresh'] : @$_GET['grefresh'],
    'token' => (isset($_POST['token'])) ? $_POST['token'] : @$_GET['token'],
    'historyId' => (isset($_POST['historyId'])) ? $_POST['historyId'] : @$_GET['historyId']
  );

  error_log('REQUEST METHOD : ' . $_SERVER['REQUEST_METHOD']);
  error_log('PARAMS : ' . print_r($params, true));

  // CREATE NEW USER RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    if (!$params['invite_code'] && !$params['idMember']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $q = $db->prepare('insert into users (invite_code, idMember, email, google_refresh_token, trello_token, historyId) values (:code, :idMember, :email, :refresh, :token, :historyId)');
    $qp = array(
      ':code' => $params['invite_code'],
      ':idMember' => $params['idMember'],
      ':email' => $params['email'],
      ':refresh' => $params['grefresh'],
      ':token' => $params['token'],
      'historyId' => $params['historyId']
    );
    $q->execute($qp);
    if (!$q->rowCount()) {
      tcrm_api_server_error('could not create user');
    }
    $r = array(
      'status' => 'created',
      'userid' => $db->lastInsertId()
    );
  }

  // DELETE USER RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    if (@$_GET['tcrmkey'] != $settings['adminkey']) {
      tcrm_api_unauthorized();
    }
    
    if (!$params['idMember']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    api_call('DELETE', $settings['baseurl'] . 'api/subscriptions/google-stop.php?tcrmkey=' . $settings['tcrmkey'] . '&idMember=' . $params['idMember']);
    
    $q = $db->prepare('delete from users where idMember = :idMember');
    $qp = array(
      ':idMember' => $params['idMember']
    );
    $q->execute($qp);
    if (!$q->rowCount()) {
      tcrm_api_server_error('could not delete user record for ' . $params['idMember']);
    }
    $r = true;
  }

  // GET USER RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if ($params['userid']) {
      $q = $db->prepare('select * from users where userid = :userid');
      $qp = array(
        ':userid' => $params['userid']
      );
      $q->execute($qp);
      $r = $q->fetch();
    } elseif ($params['idMember']) {
      $q = $db->prepare('select * from users where idMember = :idMember');
      $qp = array(
        ':idMember' => $params['idMember']
      );
      $q->execute($qp);
      $r = $q->fetch();
    } elseif ($params['email']) {
      $q = $db->prepare('select * from users where email = :email');
      $qp = array(
        ':email' => $params['email']
      );
      $q->execute($qp);
      $r = $q->fetch();
    } elseif ($params['invite_code']) {
      $q = $db->prepare('select * from users where invite_code = :code');
      $qp = array(
        ':code' => $params['invite_code']
      );
      $q->execute($qp);
      $r = $q->fetchAll();
    } else {
      if (@$_GET['tcrmkey'] == $settings['adminkey']) {
        $q = $db->prepare('select * from users');
        $q->execute();
        $r = $q->fetchAll();
      } else {
        error_log('unauthorized access prevented');
        tcrm_api_unauthorized();
      }
    }
    
    if ($r === false) {
      tcrm_api_not_found('could not get data for user ' . $params['userid']);
    }
  }

  // UDPATE USER RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$params['userid']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $q = $db->prepare('select * from users where userid = :userid');
    $qp = array(':userid' => $params['userid']);
    $q->execute($qp);
    $before = $q->fetch();
    
    error_log('BEFORE : ' . print_r($before, true));
    
    $q = $db->prepare('update users set idMember = :idMember, active = :active, email = :email, google_refresh_token = :refresh, trello_token = :token, historyId = :historyId where userid = :userid');
    $qp = array(
      ':userid' => $params['userid'],
      ':idMember' => ($params['idMember']) ? $params['idMember'] : $before['idMember'],
      ':active' => ($params['active'] != '') ? $params['active'] : $before['active'],
      ':email' => ($params['email']) ? $params['email'] : $before['email'],
      ':refresh' => ($params['grefresh']) ? $params['grefresh'] : $before['google_refresh_token'],
      ':token' => ($params['token']) ? $params['token'] : $before['trello_token'],
      ':historyId' => ($params['historyId']) ? $params['historyId'] : $before['historyId']
    );
    $q->execute($qp);
    if (!$q->rowCount()) {
      tcrm_api_server_error('could not update user ' . $params['userid']);
    }
    $r = true;
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