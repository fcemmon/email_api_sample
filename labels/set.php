<?php
  require_once('../settings.php');
  require_once('../db.php');

  error_log('initiating label setter');

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    error_log('wrong request method');
    http_response_code(400);
    die();
  }

  $idCard = @$_POST['idCard'];
  $newLabel = @$_POST['newLabel'];
  $token = @$_POST['token'];
  
  if (!$idCard || !$newLabel || !$token) {
    error_log('missing parameter');
    http_response_code(400);
    die();
  }

  error_log('setting label ' . $newLabel . ' on ' . $idCard);

  $tparams = 'key=' . $settings['t']['key'] . '&token=' . $token;
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, 'https://api.trello.com/1/cards/' . $idCard . '/labels/?color=' . $newLabel . '&' . $tparams);
  curl_setopt($ch, CURLOPT_POST, true);
  $cr = curl_exec($ch);
  $response = json_decode($cr, true);
  curl_close($ch);
  
  if ($response === false) {
    http_response_code(500);
    error_log('error setting label');
  } else {
    error_log('label set successfully');
    echo json_encode(array(
      'status' => 'success'
    ));
  }
?>