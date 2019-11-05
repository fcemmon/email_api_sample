<?php
  require_once('../settings.php');
  
  if (!isset($_POST['code'])) {
    http_response_code(400);
    error_log('missing parameter');
    die();
  }

  $authURL = 'https://accounts.google.com/o/oauth2/token';
  $ch = curl_init($authURL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, array(
    'code' => $_POST['code'],
    'client_id' => $settings['g']['client_id'],
    'client_secret' => $settings['g']['secret'],
    'redirect_uri' => 'postmessage',
    'grant_type' => 'authorization_code'
  ));
  $result = curl_exec($ch);
  curl_close($ch);
  
  if ($result === false) {
    error_log('google auth failed completely');
    http_response_code(500);
    die();
  } else {
    echo $result;
  }
?>