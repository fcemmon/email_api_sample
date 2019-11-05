<?php
  require_once('../settings.php');
  require_once('../db.php');
  require_once('../api.php');

  error_log('initiating card parser');

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    tcrm_api_bad_request('wrong request method');
  }

  $params = array(
    'idCard' => @$_POST['idCard'],
    'idBoard' => @$_POST['idBoard']
  );
  
  if (!$params['idCard'] || !$params['idBoard'] ) {
    tcrm_api_bad_request('missing parameter');
  }
  
  error_log('getting instance ' . $params['idBoard']);

  $instance = api_call('GET', $settings['baseurl'] . 'api/instances/?tcrmkey=' . $settings['tcrmkey'] . '&idBoard=' . $params['idBoard'], true);

  if (!is_array($instance) || !count($instance) || !@$instance['idMember']) {
    $e = array(
      'msg' => 'error getting instance data',
      'result' => $instance
    );
    tcrm_api_server_error($e);
  }

  $user = api_call('GET', $settings['baseurl'] . 'api/users/?tcrmkey=' . $settings['tcrmkey'] . '&idMember=' . $instance['idMember'], true);

  error_log('getting data for card ' . $params['idCard']);

  $tparams = 'key=' . $settings['t']['key'] . '&token=' . $user['trello_token'];

  $card = api_call('GET', 'https://api.trello.com/1/cards/' . $params['idCard'] . '?actions=commentCard,updateCard:idList&' . $tparams, true);

  if (!is_array($card) || !count($card) || !@$card['id']) {
    $e = array(
      'msg' => 'error getting card data',
      'result' => $card
    );
    tcrm_api_server_error($e);
  }

  if (!$card['desc']) {
    error_log('card description empty');
    error_log('checking to see if we are tracking this card');
    
    $q = $db->prepare('select * from people where idCard = :idCard');
    $qp = array(
      ':idCard' => $params['idCard']
    );
    $q->execute($qp);
    $c = $q->fetch();
    
    if ($c['email']) {
      error_log('record exists for this card, deleting');
      $q = $db->prepare('delete from people where idCard = :idCard');
      $q->execute($qp);
    } else {
      error_log('no record found, nothing to do');
    }
    
    die();
  }

  $validated = false;
  $newDesc = '';

  $descTest = preg_match('/##TCRM##\n?(.*)\n?##----##/s', $card['desc'], $dmatch);

  if ($descTest) {
    error_log('found formatted data');
    $raw_data = explode("\n", $dmatch[1]);
    $crm_data = array();
    foreach ($raw_data as $r) {
      $d = strpos($r, ':');
      $k = substr($r, 0, $d);
      $v = substr($r, $d+1);
      $crm_data[$k] = $v;
    }
    if (!array_key_exists('email', $crm_data)) {
      error_log('email field missing');
    } else {
      $eTest = preg_match('/^(\S*@{1}\S*)$/is', trim($crm_data['email']), $ematch);
      if (!isset($ematch[1])) {
        error_log('email field does not contain a valid email address');
      } else {
        $validated = true;
        
        error_log('email field exists and contains a valid address');
        tcrm_parser_addorupdate($ematch[1]);
      }
    }
  } else {
    error_log('formatted data was not found, looking for email addresses');
    $newTest = preg_match('/^(\S*@{1}\S*)$/is', $card['desc'], $nmatch);

//    error_log($card['desc']);
//    error_log(print_r($matches, true));
    
    if (isset($nmatch[1])) {
      error_log('found an email address');

      tcrm_parser_addorupdate($nmatch[1]);
      
      $formattedData = "##TCRM##\nemail: ".$nmatch[1]."\nhistory: [show](https://mail.google.com/mail/u/0/#search/from:".urlencode($nmatch[1]).")\n##----##";
      $validated = true;
    } else {
      error_log('did not find an email address');
      $formattedData = "";
    }
    
    if ($newTest) {
      $newDesc = $formattedData;
    } else {
      $newDesc = $formattedData . $card['desc'];
    }
  }

  if (!$validated) {
    error_log('card did not pass validation');
//    tcrm_parser_setLabel('red');
  } else {
    error_log('card passed validation');
    if ($newDesc != "" && $newDesc != $card['desc']) {
      error_log('updating card description');
      $updateResult = api_call('PUT', 'https://api.trello.com/1/cards/' . $params['idCard'] .'/desc?' . $tparams . '&value=' . urlencode($newDesc), true);

      if (!$updateResult) {
//        tcrm_parser_setLabel('red');
        $e = array(
          'msg' => 'error trying to update description',
          'result' => $updateResult
        );
        tcrm_api_server_error($e);
      } else {
        error_log('description updated');
      }
    }
  }
  
//  function tcrm_parser_setLabel($newLabel) {
//    global $settings, $params, $instance;
//    
//    error_log('unsetting labels');
//    $ch = curl_init($settings['baseurl'] . 'api/labels/unset.php');
//    curl_setopt($ch, CURLOPT_POST, true);
//    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
//      'idCard' => $params['idCard'],
//      'token' => $instance['token']
//    ));
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//    $cr = curl_exec($ch);
//    curl_close($ch);
//    unset($cr);
//    
//    error_log('setting label ' . $newLabel);
//    $ch = curl_init($settings['baseurl'] . 'api/labels/set.php');
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//    curl_setopt($ch, CURLOPT_POST, true);
//    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
//      'idCard' => $params['idCard'],
//      'newLabel' => $newLabel,
//      'token' => $instance['token']
//    ));
//    $cr = curl_exec($ch);
//    curl_close($ch);
//    unset($cr);
//  }
                            
  function tcrm_parser_addorupdate($newEmail) {
    global $settings, $validated, $params, $instance, $tparams, $card, $user;
    
    error_log('checking for existing person record');
    
    $ch = curl_init($settings['baseurl'] . 'api/people/?tcrmkey=' . $settings['tcrmkey'] . '&idCard=' . $params['idCard']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, false);

    if( ! $cr = curl_exec($ch)) {
      error_log('CURL ERROR ('.curl_error($ch).')' );
    }
    curl_close($ch);

    $person = json_decode($cr, true);
    error_log(print_r($person, true));
    unset($cr);

    if (!isset($person['idCard'])) {
      error_log('person record does not exist');
      error_log('creating new record');
      $changed = true;

      $ch = curl_init($settings['baseurl'] . 'api/people/?tcrmkey=' . $settings['tcrmkey'] . '&idCard=' . $params['idCard'] . '&idBoard=' . $params['idBoard'] . '&idList=' . $card['idList'] . '&email=' . $newEmail);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
      $cr = curl_exec($ch);
      curl_close($ch);
      unset($cr);
    } else {
      error_log('person record exists');
      error_log(print_r($person, true));

      if ($person['email'] == $newEmail) {
        error_log('email address on card matches address on record');
        $validated = true;
        $changed = false;
      } else {
        error_log('email address on card is different from the one on record');
        error_log('updating record');
        $changed = true;

        $ch = curl_init($settings['baseurl'] . 'api/people/?tcrmkey=' . $settings['tcrmkey']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
          'idCard' => $params['idCard'],
          'idList' => $card['idList'],
          'email' => $newEmail
        ));
        $cr = curl_exec($ch);
        curl_close($ch);
        unset($cr);
      }
    }
    
    if ($changed) {
      error_log('getting timestamp of last message');
      $lastmessage = api_call('GET', $settings['baseurl'] . 'api/subscriptions/google-lastmessage.php?tcrmkey=' . $settings['tcrmkey'] . '&guser=' . $user['email'] . '&grefresh=' . $user['google_refresh_token'] . '&contact=' . $newEmail, true);

      if (!$lastmessage || !isset($lastmessage['days']) || $lastmessage['days'] == -1) {
        error_log('no message found');
        $commentData = array(
          'text' => 'Started tracking contact history with ' . $newEmail . "\n\nYou have never had a gmail conversation with this address."
        );
      } else {
        error_log('got timestamp');
        error_log('adding comment');

        $commentData = array(
          'text' => 'Started tracking contact history with ' . $newEmail . "\n\nIt has been **" . $lastmessage['days'] . ' days** since your last gmail conversation with this address.'
        );

      }
      
      api_call('POST', 'https://api.trello.com/1/cards/' . $params['idCard'] . '/actions/comments?' . $tparams, $commentData);
    }
  }
?>