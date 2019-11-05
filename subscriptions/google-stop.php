<?php
  require_once('../settings.php');
  require_once('../db.php');
  require_once('../api.php');

  if ($_SERVER['REQUEST_METHOD'] != 'DELETE') {
    tcrm_api_bad_request('wrong request type');
  }

  $params = array(
    'idMember' => (isset($_POST['idMember'])) ? $_POST['idMember'] : @$_GET['idMember']
  );

  $output = array(
    'idMember' => $params['idMember'],
    'action' => 'unsubscribe',
    'subscription' => 'google'
  );

  if (!$params['idMember']) {
    $output['message'] = 'missing parameter';
    tcrm_api_bad_request($output);
  }

  $user = api_call('GET', $settings['baseurl'] . 'api/users/?tcrmkey=' . $settings['tcrmkey'] . '&idMember=' . $params['idMember'], true);
  if (!$user) {
    $output['status'] = 'failed';
    $output['message'] = 'user does not exist';
    tcrm_api_not_found($output);
  }

  try {
    require_once('../../lib/google/php/autoload.php');

    $gapi = new Google_Client();
    $gapi->setAccessType('offline');
    $gapi->setApplicationName("TrelloCRM");

    $gapi->setClientId($settings['g']['client_id']);
    $gapi->setClientSecret($settings['g']['secret']);
    $gapi->setDeveloperKey("-----BEGIN PRIVATE KEY-----\nMIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDaEEgUquxbex7r\njyUfmbxY5JUNyO89l1tPqTSKyrplgNQyTovYYZAd47FzRq/O2Eeryegl+qnjpOzj\na/x7rr/S6k6GJrtPNAat4jssOAaE8SYnNYPRsiAKCQztR8DRaoLxZ17JF3Qgmgn1\nVsRfoBMSBOcEugSv+6jUFrDX+K6FG2utEiiGY1dhkVHPYwTwOTdn4kJcca4DIbml\n0HnqZ96SCwQMurI6COIAuQz+mAV5W+xaMMYElIsTUBgTpsWi8m3fnO1qj5k4uv3k\nNF+amZwZ0ZQ7efQ4wNPAWF/fvGkaRfwDQjljDA/0iY2DAB+3HmnWmVERqvc8P0TE\nbL+ocAunAgMBAAECggEBANhu5owU5ckal6LnjcoilY2JOuPWtpXkH04evDaxZnYO\new5/ZU/b7eWss9idpSthASnhmZfwhiSa8j/7UzsTQ/M8ebmuwaSoF6UIcQC/jsDu\ndJbgB7je2ev79aeg2yE/XT3rAmoG7EzbdtzaRMhAogz1brIDS1m8J2JyLw39FoKm\n0hMf7y3idysSQxYU6LYH4ATFt7LbGPFyu6OlFYkaHO0A4ZaBzf75Zq96xyWMNHxm\noAT1AVDv3jBpL17uyTSDcUGUXJ7V8vYGRhXiQDlQx1oCKTOuDmmiPVVgiR69fxXh\ncK+2CpQQ2cRnl3F8SRg2JC5PC75D6xWMv6CdphEFfXECgYEA+AuxEKZoOuicyzh0\nSV0q+1ow0520J8QUvrALFH+8xNY4Y6C9M1+kF+6mR++ceaUK1WoGSG+jaUNOSiXT\nKAHTRRm5M67kkFs7aDDtdl1zOOEuPQlDwXU225JYRYHsmxOhxFRfSS0scYLjjE8z\nRCkdwwc3eMD4zDmzWqGeo1MXjXkCgYEA4Q50b4SS3Uq7zhYowp+UkpxiQgnFC9Ck\naB7oP8+mV53pr+Eg2uRsjTz+SlAxwlnbDw+JY5RUyAg71P0x0GzZqkI0kfV+7M/w\ncy4uVzKfRnftuRFSHP5DfZY2S2IqgXP/lKJJJCT35f0N7t+2gkj8laYm3JiPx/no\nlPfBmsMsuh8CgYEAtuDv4GXATUZ5a7+oxPpjGUSq4SrRy8veu6TR1oBDbiC/HH1D\nYaAvPNHgWQNJq8pKTYTJMxjUM2TDURnIMCQAX18S1A8rR19sUmpYeb92l0Y2sBun\nj/faxVKFsGGVT9TOnRDT3ADpVpt5J1axZpyl68fjVy13giM8oCKu8p3trIkCgYAR\n4YsgvSDKEkD/q8ULSZCNYX1xD8OnH6mgWCxNvZrSxUom3jU1DwcM5baygtKhRXBh\nLvPUhJmD1xuh3YgSrkNRAreYjS/Lcu4AyL+H0A7Vk3vAw36JrS4BkWi47pC//k5l\nKcuz4ngLvuJXg1DF4zSmUzAtQLXTxqhTBahNOoqYMwKBgQCo9hfkuUaC1PGjuxJP\nn84rtVOIddUC3NapjnTi3/zriqfOKfin7Nbv44cko3863JQ3Qv8A7ElhyG6oqYNi\nuM6GesRhd26EZNzxduWtV5Aq3o9oWtGgE/WDuCmN7JwDmnnueboEQ2m8dMSdFspG\n5mNtRZh4WnS51yRUVwtymmOUfQ==\n-----END PRIVATE KEY-----\n");
  } catch (Exception $e) {
    tcrm_api_server_error('could not intialize google api client');
  }

  if ($gapi->isAccessTokenExpired()) {
    $gapi->refreshToken($user['google_refresh_token']);
  }

  $gtoken = json_decode($gapi->getAccessToken(), true);
  if (!$gtoken) {
    $output['status'] = 'failed';
    $output['message'] = 'problem with google auth token';
    tcrm_api_server_error($output);
  }

  try {
    $gmail = new Google_Service_Gmail($gapi);
    $gmail->users->stop('me');
  } catch (Exception $e) {
    $output['status'] = 'failed';
    $output['message'] = 'could not unsubscribe from google push notifications';
    $output['detail'] = $e;
    tcrm_api_server_error($output);
  }

  $output['status'] = 'success';
  $output['message'] = 'stopped subscribing to push notifications from google';

  tcrm_api_output_json($output);
?>