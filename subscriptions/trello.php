<?php
  require_once('../settings.php');
//   require_once('../api.php');
  require_once('../db.php');

  $terms_detected_regex = array('/phone call(.*)/i', '/in-person(.*)/i', '/text message(.*)/i');

  error_log('callback from Trello');

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    error_log('payload saved as debug.out');
    file_put_contents('debug.out', print_r($payload, true));
    
    if (!isset($payload['model']['id'])) {
      error_log('invalid payload');
      die();
    }
    
    error_log('payload from : ' . $payload['model']['id']);
    
    $idBoard = @$payload['model']['id'];
    $idList = @$payload['action']['data']['list']['id'];
    $idCard = @$payload['action']['data']['card']['id'];
    $action = @$payload['action']['type'];
    $idAction = @$payload['action']['id'];
    $q = $db->prepare('select * from instances where idBoard = :idBoard');
    $qp = array(
      ':idBoard' => $idBoard 
    );
    $q->execute($qp);
    $instance = $q->fetch();
    
    if (!$instance) {
      error_log('no instance exists for this board');
      error_log('nothing to do');
      die();
    }
    
    error_log('got instance : ' . print_r($instance, true));
    
    if (!$instance['idMember']) {
      error_log('fatal error: missing idMember for this instance');
      die();
    }
    
    error_log('getting user ' . $instance['idMember']);
    
    $q = $db->prepare('select * from users where idMember = :idMember');
    $qp = array(
      ':idMember' => $instance['idMember']
    );
    $q->execute($qp);
    $user = $q->fetch();
    
    if (!$user['idMember']) {
      error_log('failed to get user details');
      die();
    }
    
    if (!$user['active']) {
      error_log('user is suspened, ignoring payload');
      die();
    }
    
    error_log('got user : ' . print_r($user, true));
    
//    $q = $db->prepare('select * from people where idCard = :idCard');
//    $qp = array(
//      'idCard' => $idCard
//    );
//    $q->execute($qp);
//    
//    $person = $q->fetch();
//    
//    if (!$person) {
//      error_log('this card does not match any people on record');
//    }
    
    error_log('action : ' . $action);
    
    if ($action == 'updateCard') {
      if (@$payload['action']['data']['card']['closed'] == 1) {
        error_log('card was archved');
        error_log('removing person from database');
        
        $q = $db->prepare('delete from people where idCard = :idCard');
        $qp = array(
          ':idCard' => $payload['action']['data']['card']['id']
        );
        $q->execute($qp);
      } elseif ($listAfter = @$payload['action']['data']['listAfter']['id']) {
        error_log('card moved between lists');
        
        $ch = curl_init($settings['baseurl'] . 'api/subscriptions/trello-lastcomment.php?tcrmkey=' . $settings['tcrmkey'] . '&idCard=' . $idCard . '&token=' . $user['trello_token']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        $lastcomment = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        if (!$lastcomment || !isset($lastcomment['idAction'])) {
          error_log('no comments found');
          error_log('nothing to do');
          die();
        }
        
        error_log('getting timestamp of last contact');
        $q = $db->prepare('select * from people where idCard = :idCard');
        $qp = array(
          ':idCard' => $idCard
        );
        $q->execute($qp);
        $person = $q->fetch();
        
        if (!$person['timestamp']) {
          error_log('timestamp missing');
          error_log('this *should* never happen - needs to be handled anyway though...');
          die();
        }
        
        error_log('resolving label');
        $ch = curl_init($settings['baseurl'] . 'api/labels/resolve.php?tcrmkey=' . $settings['tcrmkey']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
          'idCard' => $idCard,
          'idBoard' => $idBoard,
          'idList' => $listAfter,
          'days' => (int)(floor((time() - $person['timestamp']) / 60 / 60 / 24))
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        
      error_log('updating record');
      $q = $db->prepare('update people set idList = :idList where idCard = :idCard');
      $qp = array(
        ':idCard' => $idCard,
        ':idList' => $listAfter
      );
      $q->execute($qp);
      } else {
        error_log('parsing');
        $ch = curl_init($settings['baseurl'] . 'api/parser/?tcrmkey=' . $settings['tcrmkey']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
          "idCard" => $idCard,
          "idBoard" => $idBoard
        ));
        $result = curl_exec($ch);
        curl_close($ch);
      }
    } elseif ($action == 'commentCard') {
      $days = false;
      $encoded_payload_text = urlencode(@$payload['action']['data']['text']);
      $text_array = explode('%0A', $encoded_payload_text);
      error_log('text array======================================');
      error_log($encoded_payload_text);
      error_log(count($text_array));

      for ($i=0;$i<count($text_array);$i++) {
        for ($j=0; $j <count($terms_detected_regex) ; $j++) {
          if (preg_match($terms_detected_regex[$j], urldecode($text_array[$i])) == 1) {
            $text_array[$i] = '**'.$text_array[$i].'**';
            break;
          }
        }
      }
      
      @$payload['action']['data']['text'] = implode('%0A',$text_array);
      error_log('text======================================');
      error_log($payload['action']['data']['text']);
      // $payload['action']['data']['text'] = urlencode('**'.$payload['action']['data']['text'].'**');
      $tparams = 'key=' . $settings['t']['key'] . '&token=' . $user['trello_token'];
      $put_comment_url = 'https://api.trello.com/1/cards/' . $idCard . '/actions/' . $idAction . '/comments?text=' . @$payload['action']['data']['text'] . '&' . $tparams;
      $ch_comment = curl_init();
      curl_setopt($ch_comment, CURLOPT_URL, $put_comment_url);
      curl_setopt($ch_comment, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch_comment, CURLOPT_FRESH_CONNECT, true);
      curl_setopt($ch_comment, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch_comment, CURLOPT_CUSTOMREQUEST, 'PUT');
      $cr_comment = curl_exec($ch_comment);
      $put_comment_response = json_decode($cr_comment, true);
      curl_close($ch_comment);
      if ($put_comment_response === false) {
        error_log('Error updating comment');
      } else {
        error_log('Comment is updated successfully');
        $days = 0;
      }
      
      if (preg_match('/\*\*([0-9]+)\sdays\*\*/is', @$payload['action']['data']['text'], $dmatch)) {
        $days = $dmatch[1];
      }

      if ($days === false &&
          ( preg_match('/^\*\*email\s(sent|received)\*\*/is', @$payload['action']['data']['text'])
         || preg_match('/^\*\*call\s(to|from)\*\*/is', @$payload['action']['data']['text'])
          )
         ) {
        $days = 0;
      }
      
      if ($days === false) {
        error_log('comment did not match any rules');
        die();
      }
      
      error_log('formatted comment detected');
      error_log('resolving label for ' . $days . ' days');
      
      $ch = curl_init($settings['baseurl'] . 'api/labels/resolve.php?tcrmkey=' . $settings['tcrmkey']);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'idBoard' => $idBoard,
        'idList' => $idList,
        'idCard' => $idCard,
        'days' => $days
      ));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $result = curl_exec($ch);
      curl_close($ch);
      
      error_log($result);
      
      error_log('updating record');
      $q = $db->prepare('update people set timestamp = :timestamp, idList = :idList where idCard = :idCard');
      $qp = array(
        ':idCard' => $idCard,
        ':idList' => $idList,
        ':timestamp' => time() - ($days * 60 * 60 * 24)
      );
      $q->execute($qp);
    } elseif ($action == 'deleteCard') {
      error_log('idCard : ' . $idCard);
      $ch = curl_init($settings['baseurl'] . 'api/people/?tcrmkey=' . $settings['tcrmkey'] . '&idCard=' . $idCard);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
      $cr = curl_exec($ch);
      curl_close($ch);
    } else {
      error_log('ignored');
    }
  } else {
    error_log($_SERVER['REQUEST_METHOD']);
  }
?>