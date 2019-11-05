<?php
  require_once('../settings.php');
  require_once('../db.php');

  error_log('running daily label update');

  $output = array();

  $q = $db->prepare('select * from instances');
  $q->execute();

  $instances = $q->fetchAll();

  foreach ($instances as $i) {
    if (!$i['idMember']) {
      error_log('skipping instance without idMember');
      error_log(print_r($i, true));
      continue;
    }
    
    $q = $db->prepare('select * from users where idMember = :idMember');
    $qp = array(
      ':idMember' => $i['idMember']
    );
    $q->execute($qp);
    
    $user = $q->fetch();
    
    if (!$user['trello_token']) {
      error_log('skipping user account with missing trello token');
      continue;
    }
    
    if (!$user['active']) {
      error_log('skipping suspended user account');
      continue;
    }
    
    $ch = curl_init($settings['baseurl'] . 'api/labels/apply.php?tcrmkey=' . $settings['tcrmkey']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
      'idBoard' => $i['idBoard'],
      'token' => $user['trello_token']
    ));
    $cr = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $output[] = $cr;
    unset($cr);
    unset($user);
  }
  echo json_encode($output);
?>