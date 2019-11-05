<?php
  $origin = @$_SERVER['HTTP_ORIGIN'];
  error_log('api access from : ' . $origin);
  if ($origin == "https://my.trellocrm.com" || $origin == "https://admin.trellocrm.com") {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Methods: HEAD, GET, POST, PUT, DELETE");
  }

//  if ($_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR'] && !isset($_GET['token'])){
//    error_log('unauthorized access prevented from : ' . $_SERVER['REMOTE_ADDR']);
//    tcrm_api_unauthorized();
//  }

  if (@$_GET['tcrmkey'] != $settings['tcrmkey'] && @$_GET['tcrmkey'] != $settings['adminkey']) {
    tcrm_api_unauthorized();
  }

  if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    $input = file_get_contents('php://input');
    mb_parse_str($input);
  }

  function api_call($method, $url, $params, $decode=false) {
    error_log('making an api call');
    error_log('method : ' . $method);
    error_log('url : ' . $url);
    error_log('params : ' . (is_array($params)) ? print_r($params, true) : $params);
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    switch ($method) {
      case 'POST' :
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        if (is_string($params)) {
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        }
        break;
      case 'PUT' :
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        break;
      case 'DELETE' :
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
      default :
        if ($method !== 'GET') {
          return false;
        }
    }
    
    $cr = curl_exec($ch);
    curl_close($ch);
    
    if ($decode === true || ($method == 'GET' && $params === true)) {
      error_log('decoding results');
      $r = json_decode($cr, true);
      error_log('result : ' . print_r($r, true));
      return $r;
    } else {
      error_log('result : ' . $cr);
      return $cr;
    }
  }

  function tcrm_api_unauthorized() {
    error_log('unauthorized');
    http_response_code(401);
    die();
  }

  function tcrm_api_bad_request($msg) {
    error_log('bad request');
    $response = array(
      'status' => 'failed',
      'code' => '400',
      'reason' => 'bad request',
      'detail' => @$msg
    );
    tcrm_api_output_json($response);
    http_response_code(400);
    die();
  }
  
  function tcrm_api_server_error($message) {
    $response = array(
      'status' => 'failed',
      'code' => '500',
      'reason' => 'server error',
      'detail' => $message
    );

    tcrm_api_output_json($response);
    http_response_code(500);
    die();
  }
  
  function tcrm_api_output_json($data) {
    if ($data === true) {
      $data = array(
        'status' => 'success'
      );
    }
    
    if (is_array($data)) {
      error_log(print_r($data, true));
      echo json_encode($data, JSON_PRETTY_PRINT);
    } else {
      echo $data;
    }
  }
  
  function tcrm_api_ok() {
    error_log('ok');
    http_response_code(200);
    die();
  }
        
  function tcrm_api_created($output=false) {
    error_log('resource created');
    if ($output) {
      if (is_array($output)) {
        error_log(print_r($output, true));
        echo json_encode($output);
      } else { 
        error_log($output);
        echo $output;
      }
    }
    http_response_code(201);
    die();
  }

  function tcrm_api_not_found($output=false) {
    error_log('resource not found');
    if ($output) {
      if (is_array($output)) {
        error_log(print_r($output, true));
        echo json_encode($output);
      } else { 
        error_log($output);
        echo $output;
      }
    }
    http_response_code(404);
    die();
  }
?>