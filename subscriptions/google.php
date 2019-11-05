<?php
require_once('../settings.php');
require_once('../db.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    error_log('wrong request method');
    http_response_code('400');
    die();
}

error_log('Push notification received from Google');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log('Data : ' . print_r($data, true));
$message = json_decode(base64_decode(@$data['message']['data']), true);

if (!$message || !$message['historyId']) {
    error_log('expected a historyId but did not find one');
    error_log('REQUEST BODY : ' . print_r($input, true));
    die();
}

error_log('payload for ' . $message['emailAddress']);
error_log('historyId : ' . $message['historyId']);
error_log('looking for matching instance');

$q = $db->prepare('select * from users where email = :guser');
$qp = array(
    ':guser' => $message['emailAddress']
);
$q->execute($qp);
$user = $q->fetch();

if (!$user['idMember']) {
    error_log('no matching user');
    die();
}

error_log('matched user ' . $user['idMember']);

$grefresh = $user['google_refresh_token'];

if (!$grefresh) {
    error_log('no refresh token, quitting');
    die();
}

if (!$user['historyId']) {
    error_log('first push received');
    error_log('recording historyId');
    updateLastPushId($message['emailAddress'], $message['historyId']);
    die();
} else {
    $lastpush = $user['historyId'];
}

error_log('lastpush : ' . $lastpush);

if ($message['historyId'] > $lastpush) {
    error_log('historyId has increased');
    updateLastPushId($message['emailAddress'], $message['historyId']);

    if (!$user['active']) {
        error_log('user is suspened, ignoring remaining payload');
        die();
    }

    error_log('loading google api client');
    require_once('../../lib/google/php/autoload.php');

    $gapi = new Google_Client();
    $gapi->setAccessType('offline');
    $gapi->setApplicationName("TrelloCRM");

    $gapi->setClientId($settings['g']['client_id']);
    $gapi->setClientSecret($settings['g']['secret']);
    $gapi->setDeveloperKey("-----BEGIN PRIVATE KEY-----\nMIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDaEEgUquxbex7r\njyUfmbxY5JUNyO89l1tPqTSKyrplgNQyTovYYZAd47FzRq/O2Eeryegl+qnjpOzj\na/x7rr/S6k6GJrtPNAat4jssOAaE8SYnNYPRsiAKCQztR8DRaoLxZ17JF3Qgmgn1\nVsRfoBMSBOcEugSv+6jUFrDX+K6FG2utEiiGY1dhkVHPYwTwOTdn4kJcca4DIbml\n0HnqZ96SCwQMurI6COIAuQz+mAV5W+xaMMYElIsTUBgTpsWi8m3fnO1qj5k4uv3k\nNF+amZwZ0ZQ7efQ4wNPAWF/fvGkaRfwDQjljDA/0iY2DAB+3HmnWmVERqvc8P0TE\nbL+ocAunAgMBAAECggEBANhu5owU5ckal6LnjcoilY2JOuPWtpXkH04evDaxZnYO\new5/ZU/b7eWss9idpSthASnhmZfwhiSa8j/7UzsTQ/M8ebmuwaSoF6UIcQC/jsDu\ndJbgB7je2ev79aeg2yE/XT3rAmoG7EzbdtzaRMhAogz1brIDS1m8J2JyLw39FoKm\n0hMf7y3idysSQxYU6LYH4ATFt7LbGPFyu6OlFYkaHO0A4ZaBzf75Zq96xyWMNHxm\noAT1AVDv3jBpL17uyTSDcUGUXJ7V8vYGRhXiQDlQx1oCKTOuDmmiPVVgiR69fxXh\ncK+2CpQQ2cRnl3F8SRg2JC5PC75D6xWMv6CdphEFfXECgYEA+AuxEKZoOuicyzh0\nSV0q+1ow0520J8QUvrALFH+8xNY4Y6C9M1+kF+6mR++ceaUK1WoGSG+jaUNOSiXT\nKAHTRRm5M67kkFs7aDDtdl1zOOEuPQlDwXU225JYRYHsmxOhxFRfSS0scYLjjE8z\nRCkdwwc3eMD4zDmzWqGeo1MXjXkCgYEA4Q50b4SS3Uq7zhYowp+UkpxiQgnFC9Ck\naB7oP8+mV53pr+Eg2uRsjTz+SlAxwlnbDw+JY5RUyAg71P0x0GzZqkI0kfV+7M/w\ncy4uVzKfRnftuRFSHP5DfZY2S2IqgXP/lKJJJCT35f0N7t+2gkj8laYm3JiPx/no\nlPfBmsMsuh8CgYEAtuDv4GXATUZ5a7+oxPpjGUSq4SrRy8veu6TR1oBDbiC/HH1D\nYaAvPNHgWQNJq8pKTYTJMxjUM2TDURnIMCQAX18S1A8rR19sUmpYeb92l0Y2sBun\nj/faxVKFsGGVT9TOnRDT3ADpVpt5J1axZpyl68fjVy13giM8oCKu8p3trIkCgYAR\n4YsgvSDKEkD/q8ULSZCNYX1xD8OnH6mgWCxNvZrSxUom3jU1DwcM5baygtKhRXBh\nLvPUhJmD1xuh3YgSrkNRAreYjS/Lcu4AyL+H0A7Vk3vAw36JrS4BkWi47pC//k5l\nKcuz4ngLvuJXg1DF4zSmUzAtQLXTxqhTBahNOoqYMwKBgQCo9hfkuUaC1PGjuxJP\nn84rtVOIddUC3NapjnTi3/zriqfOKfin7Nbv44cko3863JQ3Qv8A7ElhyG6oqYNi\nuM6GesRhd26EZNzxduWtV5Aq3o9oWtGgE/WDuCmN7JwDmnnueboEQ2m8dMSdFspG\n5mNtRZh4WnS51yRUVwtymmOUfQ==\n-----END PRIVATE KEY-----\n");
    $gapi->refreshToken($grefresh);

    error_log('checking access token');
    if ($gapi->isAccessTokenExpired()) {
        $gapi->refreshToken($grefresh);
    }

    $gtoken = json_decode($gapi->getAccessToken(), true);
    if (!$gtoken) {
        error_log('problem with google access token');
        die();
    }

    $gmail = new Google_Service_Gmail($gapi);

    error_log('renewing watch request');
    $watchRequest = new Google_Service_Gmail_WatchRequest(array(
        'topicName' => 'projects/gmail-api-test-1265/topics/messages'
    ));

    $gmail->users->watch('me', $watchRequest);

    error_log('querying historyId');
    $history = $gmail->users_history->listUsersHistory('me', array(
        'startHistoryId' => $lastpush
    ));

    //error_log(print_r($history, true));

    if (!$history || !is_array($history['modelData']['history'])) {
        error_log('no new history found');
        error_log('nothing else to do');
        die();
    }

    $mlist = array();
    $dlist = array();

    foreach ($history['modelData']['history'] as $h) {
        $mcount = (isset($h['messages'])) ? count($h['messages']) : 0;
        $dcount = (isset($h['messagesDeleted'])) ? count($h['messagesDeleted']) : 0;

        if ($dcount > 0 && $mcount == $dcount) {
            continue;
        }

        if ($dcount) {
            foreach ($h['messagesDeleted'] as $d) {
                $dlist[] = $d['message']['id'];
            }
        }

        if ($mcount) {
            foreach ($h['messages'] as $m) {
                error_log(print_r($m, true));
                if (!isset($m['labelIds']) || !in_array('DRAFT', $m['labelIds'])) {
                    $mlist[] = $m['id'];
                }
            }
        }
    }

    $mlist = array_unique($mlist);
    $dlist = array_unique($dlist);

    error_log("DELETED MESSAGES : " . print_r($dlist, true));
    error_log("MESSAGES : " . print_r($mlist, true));

    $clist = array();

    foreach ($mlist as $m) {
        if (!in_array($m, $dlist)) {
            try {
                $clist[] = $gmail->users_messages->get('me', $m);
            } catch (Exception $e) {
                error_log('error getting message details, it has probably been deleted, skipping');
                unset($e);
            }
        }
    }

    //error_log("CONFIRMED MESSAGES : " . print_r($clist, true));

    $out = array();

    foreach ($clist as $mdata) {
        if (!isset($mdata['labelIds']) || !count($mdata['labelIds'])) {
            continue;
        } elseif (!in_array('INBOX', $mdata['labelIds']) && !in_array('SENT', $mdata['labelIds'])) {
            continue;
        }


        $from = null;
        $to = null;
        $subject = null;

        foreach ($mdata['modelData']['payload']['headers'] as $header) {
            if ($header['name'] == 'From') {
                preg_match('/<(.*)>/i', $header['value'], $matches);
                $from = (isset($matches[1])) ? $matches[1] : $header['value'];
                $from_name = '';
                if (isset($matches[1])) {
                    $from_name = trim(preg_replace('/<(.*)>/i', '', $header['value']));
                } else {
                    $from_name = explode('@', $header['value'])[0];
                }
                unset($matches);
            } elseif ($header['name'] == 'To') {
                preg_match('/<(.*)>/i', $header['value'], $matches);
                $to = (isset($matches[1])) ? $matches[1] : $header['value'];
                $to_name = '';
                if (isset($matches[1])) {
                    $to_name = trim(preg_replace('/<(.*)>/i', '', $header['value']));
                } else {
                    $to_name = explode('@', $header['value'])[0];
                }
                unset($matches);
            } elseif ($header['name'] == 'Subject') {
                $subject = $header['value'];
            }
        }

        $out[] = array(
            'id' => $mdata['id'],
            'from' => $from,
            'from_name' => $from_name,
            'to' => $to,
            'to_name' => $to_name,
            'subject' => $subject,
        );
    }

    error_log("OUTPUT : " . print_r($out, true));
    error_log('parsing ' . count($out) . ' messages');

    foreach ($out as $o) {
        $action = null;
        $card_message_email = null;
        if ($o['from'] == $message['emailAddress']) {
            $action = 'sent';
            $card_message_email = $o['to'];
            $card_message_name = $o['to_name'];
            $qp = array(
                ':email' => $o['to']
            );
        } elseif ($o['to'] == $message['emailAddress']) {
            $action = 'received';
            $card_message_email = $o['from'];
            $card_message_name = $o['from_name'];
            $qp = array(
                ':email' => $o['from']
            );
        } else {
            continue;
        }

        $q = $db->prepare("select * from people where email like '%{$qp[':email']}%'");
        $q->execute();
        $r = $q->fetch();

        error_log(print_r($r, true));

        if (isset($r['idCard'])) {

            // If already added message to Trello, Skip
            $q = $db->prepare('select * from card_messages where idCard = :idCard AND idEmail = :idEmail');
            $qp = array(
                ':idCard' => $r['idCard'],
                ':idEmail' => $o['id'],
            );
            $q->execute($qp);
            error_log('Check Card Message:' . print_r($qp, true));
            $cardMessages = $q->fetch();
            error_log(print_r($cardMessages, true));
            if (empty($cardMessages)) {
                $msg = '**Email ' . $action . "**\n\nSubject: " . $o['subject'];
                error_log("Post Comment: {$msg}");
                $ch = curl_init('https://api.trello.com/1/cards/' . $r['idCard'] . '/actions/comments?key=' . $settings['t']['key'] . '&token=' . $user['trello_token']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array(
                    'text' => $msg
                ));

                if (!$result = curl_exec($ch)) {
                    error_log(curl_error($ch));
                } else {
                    $query = $db->prepare('INSERT INTO card_messages (idCard, idEmail, email, name, message, createdAt) values (:idCard, :idEmail, :email, :name, :message, :createdAt)');
                    $qparams = array(
                        ':idCard' => $r['idCard'],
                        ':idEmail' => $o['id'],
                        ':email' => $card_message_email,
                        ':name' => $card_message_name,
                        ':message' => $msg,
                        ':createdAt' => date('Y-m-d H:i:s'),
                    );
                    $query->execute($qparams);

                    error_log(print_r($result, true));
                }

                curl_close($ch);
            }
        }
    }
} else {
    error_log('nothing to do');
}

function updateLastPushId($guser, $historyId)
{
    global $db;

    error_log('updating historyId');

    $query = $db->prepare('update users set historyId = :historyId where email = :guser');
    $qparams = array(
        ':guser' => $guser,
        ':historyId' => $historyId
    );
    $query->execute($qparams);
}
