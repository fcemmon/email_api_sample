<?php
  require_once('../settings.php');
  require_once('../db.php');
  require_once('../api.php');

  error_log(__FILE__);
  $params = array(
    'idCard' => (isset($_POST['idCard'])) ? $_POST['idCard'] : @$_GET['idCard'],
    'idBoard' => (isset($_POST['idBoard'])) ? $_POST['idBoard'] : @$_GET['idBoard'],
    'idList' => (isset($_POST['idList'])) ? $_POST['idList'] : @$_GET['idList'],
    'days' => (isset($_POST['days'])) ? $_POST['days'] : @$_GET['days'],
    'email' => (isset($_POST['email'])) ? $_POST['email'] : @$_GET['email']
  );

  error_log('REQUEST METHOD : ' . $_SERVER['REQUEST_METHOD']);
  error_log('PARAMS : ' . print_r($params, true));

  // CREATE NEW PERSON RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    if (!$params['idCard'] || !$params['idBoard'] || !$params['email']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $q = $db->prepare('insert into people (idCard, idBoard, idList, email) values (:idCard, :idBoard, :idList, :email)');
    $qp = array(
      ':idCard' => $params['idCard'],
      ':idBoard' => $params['idBoard'],
      ':idList' => $params['idList'],
      ':email' => $params['email']
    );
    $r = $q->execute($qp);
  }

  // UDPATE PERSON RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$params['idCard'] || (!$params['email'] && !$params['idList'] && !$params['days'])) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $q = $db->prepare('select * from people where idCard = :idCard');
    $qp = array(':idCard' => $params['idCard']);
    $q->execute($qp);
    $before = $q->fetch();
    
    $days = $params['days'];
    
    if (!$days >= 0) {
      $days = $before['days'];
    }
    
    if (!$days >= 0) {
      $days = 0;
    }
    
    $q = $db->prepare('update people set email = :email, idList = :idList, days = :days where idCard = :idCard');
    $qp = array(
      ':email' => ($params['email']) ? $params['email'] : $before['email'],
      ':idCard' => ($params['idCard']) ? $params['idCard'] : $before['idCard'],
      ':idList' => ($params['idList']) ? $params['idList'] : $before['idList'],
      ':days' => $days
    );
    $r = $q->execute($qp);
  }

  // DELETE PERSON RECORD
  if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    if (!$params['idCard']) {
      tcrm_api_bad_request('missing parameter');
    }
    
    $q = $db->prepare('delete from people where idCard = :idCard');
    $qp = array(
      ':idCard' => $params['idCard']
    );
    $r = $q->execute($qp);
  }

  // GET RECORDS
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if ($params['idCard']) {
      // GET ONE
      error_log('getting person ' . $params['idCard']);
      $q = $db->prepare("select * from people where idCard = :idCard");
      $qp = array(
        ':idCard' => $params['idCard']
      );
      $q->execute($qp);
      $r = $q->fetch();
    } elseif ($params['idBoard']) {
    // GET ALL
      error_log('getting all people from instance ' . $params['idBoard']);
      $q = $db->prepare("select * from people where idBoard = :idBoard");
      $qp = array(
        ':idBoard' => $params['idBoard']
      );
      $q->execute($qp);
      $r = $q->fetchAll();
    } else {
      tcrm_api_bad_request('missing parameter');
    }
  }

  // VERIFY OPERATIONS & OUTPUT RESULTS
  $e = $q->errorInfo();

  if ($e[0] != '00000' || $e[1]) {
    $dberror = array(
      "state" => $e[0],
      "code" => $e[1],
      "db_error" => $e[2]
    );

    tcrm_api_server_error($dberror);
  } else {
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
      tcrm_api_created();
    } else {
      tcrm_api_output_json($r);
    }
  }
?>