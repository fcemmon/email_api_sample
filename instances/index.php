<?php
  require_once('../settings.php');
  require_once('../db.php');
  require_once('../api.php');

  $params = array(
    'idBoard' => (isset($_POST['idBoard'])) ? $_POST['idBoard'] : @$_GET['idBoard'],
    'idMember' => (isset($_POST['idMember'])) ? $_POST['idMember'] : @$_GET['idMember'],
    'idHook' => (isset($_POST['idHook'])) ? $_POST['idHook'] : @$_GET['idHook'],
    'rules' => (isset($_POST['rules'])) ? $_POST['rules'] : @$_GET['rules']
  );

  error_log('REQUEST METHOD : ' . $_SERVER['REQUEST_METHOD']);
  error_log('PARAMS : ' . print_r($params, true));

  // CREATE NEW INSTANCE
  if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    if (!$params['idBoard'] || !$params['idMember']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    if (!$params['rules']) {
      $params['rules'] = '{}';
    }
    
    $r = tcrm_api_instances_create($params['idBoard'], $params['idMember'], $params['rules']);
  }

  // DELETE INSTANCE
  if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    if (!$params['idBoard']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $r = tcrm_api_instances_delete($params['idBoard']);
  }

  // GET RECORDS
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if ($params['idBoard']) {
      // GET ONE
      $r = tcrm_api_instances_get($params['idBoard']);
    } elseif ($params['idMember']) {
      // GET ALL INSTANCES BELONGING TO SPECIFIED TRELLO ACCOUNT
      $r = tcrm_api_instances_getallbymember($params['idMember']);
    } else {
      tcrm_api_bad_request('missing parameter');
    }
    
    if ($r === false) {
      tcrm_api_server_error('could not get instance data');
    }
  }

  // UDATE RULESETS
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$params['idBoard'] || !$params['rules']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $r = tcrm_api_instances_updateRules($params['idBoard'], $params['rules']);
  }

  // VERIFY OPERATIONS & OUTPUT RESULTS
  $lastop = db_verify_last_operation();
  if ($lastop !== true) {
    tcrm_api_server_error($lastop);
  } else {
    tcrm_api_output_json($r);
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
      tcrm_api_created();
    }
  }

  function tcrm_api_instances_create($idBoard, $idMember, $rules) {
    global $db, $settings;
    
    $user = api_call('GET', $settings['baseurl'] . 'api/users/?tcrmkey=' . $settings['tcrmkey'] . '&idMember=' . $idMember, true);
    
    error_log('creating trello webhook');
    
    $hook = api_call(
      'POST',
      'https://trello.com/1/tokens/' . $user['trello_token'] . '/webhooks/?key=' . $settings['t']['key'],
      array(
        'description' => 'TrelloCRM',
        'callbackURL' => 'https://my.trellocrm.com/api/subscriptions/trello.php',
        // 'callbackURL' => $settings['baseurl'] . 'https://my.trellocrm.com/api/subscriptions/trello.php',
        'idModel' => $idBoard
      ),
      true
    );

    if (!$hook['id']) {
      error_log('/////////////////////////////////////');
      error_log(print_r($hook, true));
      tcrm_api_server_error('could not create Trello webhook');
    }
    
    error_log('creating instance record');
    $q = $db->prepare("insert into instances (idBoard, idMember, idHook, rules) values (:idBoard, :idMember, :idHook, :rules)");

    $qp = array(
      ":idBoard" => $idBoard,
      ":idMember" => $idMember,
      ":idHook" => $hook['id'],
      ":rules" => $rules
    );

    $q->execute($qp);
    $c = $q->rowCount();
    if (!$c) {
      api_call('DELETE', 'https://trello.com/1/webhooks/' . $hook['id'] . '?key=' . $settings['t']['key'] . '&token=' . $user['trello_token'], true);
      $output = array(
        'message' => 'failed to create instance',
        'dberror' => $db->errorInfo()
      );
      tcrm_api_server_error($output);
    }
    
    $r = array(
      'action' => 'create',
      'model' => 'instance',
      'status' => 'success',
      'id' => $db->lastInsertId()
    );
    
    return $r;
  }

  function tcrm_api_instances_get($idBoard) {
    global $db;
    
    if (!$idBoard) {
      return false;
    }
    
    error_log('getting instance ' . $idBoard);
    
    $q = $db->prepare("select * from instances where idBoard = :idBoard");
    $qp = array(
      ':idBoard' => $idBoard
    );
    $q->execute($qp);
    $r = $q->fetch();
    
    return $r;
  }

  function tcrm_api_instances_getallbymember($idMember) {
    global $db;
    
    if (!$idMember) {
      return false;
    }
    
    error_log('getting list of all instances for trello user ' . $idMember);
    
    $q = $db->prepare("select * from instances where idMember = :idMember");
    $qp = array(
      ':idMember' => $idMember
    );
    $q->execute($qp);
    $r = $q->fetchAll();
    
    $output = array();
    
    foreach ($r as $i) {
      $output[$i['idBoard']] = $i;
    }
    
    return $output;
  }

  function tcrm_api_instances_updateRules($idBoard, $rules) {
    global $db;
    
    error_log('updating rules');
    
    if (!$rules) {
      tcrm_api_bad_request('missing parameter');
    } elseif (is_array($rules)) {
      error_log('RULES : ' . print_r($rules, true));
      $rules = json_encode($rules);
    } else {
      error_log('RULES : ' . $rules);
    }
    
    $q = $db->prepare('update instances set rules = :rules where idBoard = :idBoard');
    $qp = array(
      ':idBoard' => $idBoard,
      ':rules' => $rules
    );
    $q->execute($qp);
    
    if (!$q->rowCount()) {
      tcrm_api_server_error('could not update rules for instance : ' . $idBoard);
    }
  }

  function tcrm_api_instances_delete($idBoard) {
    global $db, $settings;
    
    if (!$idBoard) {
      return false;
    }
    
    $instance = tcrm_api_instances_get($idBoard);
    error_log('deleting instance : ' . print_r($instance, true));
    if (!$instance) {
      error_log('failed');
    }
    
    error_log('getting user record for ' . $instance['idMember']);
    $user = api_call('GET', $settings['baseurl'] . 'api/users/?tcrmkey=' . $settings['tcrmkey'] . '&idMember=' . $instance['idMember'], true);
    
    error_log('deleting trello webhook ' . $instance['idHook']);
    $hook = api_call('DELETE', 'https://trello.com/1/webhooks/' . $instance['idHook'] . '?key=' . $settings['t']['key'] . '&token=' . $user['trello_token'], true);
    
    if ($hook === false) {
      tcrm_api_server_error('could not delete trello webhook');
    }
    
    $qp = array(
      ':idBoard' => $idBoard
    );

    $q = $db->prepare("delete from people where idBoard = :idBoard");
    $r = $q->execute($qp);
    
    $v = db_verify_last_operation();
    if ($v !== true) {
      tcrm_api_server_error($v);
    }
    
    $q = $db->prepare("delete from instances where idBoard = :idBoard");
    $r = $q->execute($qp);
    
    return $r;
  }
?>