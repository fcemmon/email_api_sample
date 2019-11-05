<?php
	require_once('../settings.php');
	require_once('../db.php');
	
	// Declare Vars
	$t_key = $settings['t']['key'];
	$u_boards = array();
	// Get valid users(idMember!=null, email!=null, trello_token!=null, active=1)
	$q = $db->prepare("SELECT * FROM users WHERE `idMember` IS NOT NULL AND `email` IS NOT NULL AND `trello_token` IS NOT NULL AND `active`=1");
	
	$q->execute();
	$users = $q->fetchAll();
	if (count($users) > 0) {
		for ($i=0; $i <count($users); $i++) {
			/**
			 * Get active boards by idMember
			**/
			$user = $users[$i];
			$idMember = $user['idMember'];
			$trello_token = $user['trello_token'];
			$userEmail = $user['email'];
			// $y_timestamp = time() - (24 * 60 * 60);
			// $s_date = date('Y-m-d', $y_timestamp) . ' 10-00-00';
			// $e_date = date('Y-m-d') . ' 10-00-00';

			$cur_time = time();

			// ===============================
			// $idMember = '5935832fe1c50c7730767803';
			// $trello_token = '4ae1c4f19990a7f9f200ca2ba7abb0df21dc04a7ec2328947d7196b5de1db83c';
			// $userEmail = 'mobileweb1989@gmail.com';
			// $s_date = '2017-06-12 00-00-00';
			// $e_date = '2017-06-13 00-00-00';
			// ===============================

			$q = $db->prepare("SELECT `idBoard`, `rules` FROM instances WHERE `idMember` = :idMember");
			$qp = array(
				':idMember' => $idMember
			);
			$q->execute($qp);
			$u_boards = $q->fetchAll();
			if (count($u_boards) > 0) {
				for ($bi=0; $bi < count($u_boards); $bi++) {
					// $received = array();
					// $sent = array();
					$contacts1 = array();
					$contacts2 = array();
					$tmp_board = $u_boards[$bi];
					$board_rules = json_decode($tmp_board['rules'], true);
					$c_url = 'https://api.trello.com/1/boards/'.$tmp_board['idBoard'].'/cards?fields=id,name,shortUrl,idList&key='.$t_key.'&token='.$trello_token;
					$c_ch = curl_init();
					curl_setopt($c_ch, CURLOPT_URL, $c_url);
					curl_setopt($c_ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($c_ch, CURLOPT_SSL_VERIFYPEER, false);
					$c_cards = curl_exec($c_ch);
					$error = curl_error($c_ch);
					curl_close($c_ch);
					$cards = json_decode($c_cards, true);
					
					if (count($cards) < 1) {
						continue;
					}

					// Get Board url
					$b_url = 'https://api.trello.com/1/boards/'.$tmp_board['idBoard'].'?fields=shortUrl&key='.$t_key.'&token='.$trello_token;
					$b_ch = curl_init();
					curl_setopt($b_ch, CURLOPT_URL, $b_url);
					curl_setopt($b_ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($b_ch, CURLOPT_SSL_VERIFYPEER, false);
					$b_board = curl_exec($b_ch);
					$error = curl_error($b_ch);
					curl_close($b_ch);
					$board_url = json_decode($b_board, true);

					foreach ($cards as $key => $card) {
						// $c_q = $db->prepare("SELECT * FROM card_messages WHERE `idCard` = :idCard AND `createdAt` BETWEEN :sDate AND :eDate");
						// $c_qp = array(
						// 	':idCard' => $card['id'],
						// 	':sDate' => $s_date,
						// 	':eDate' => $e_date
						// );
						$c_q = $db->prepare("SELECT * FROM card_messages WHERE `idCard` = :idCard");
						$c_qp = array(
							':idCard' => $card['id']
						);
						$c_q->execute($c_qp);
						$card_messages = $c_q->fetchAll();
						if (count($card_messages) > 0) {
							for ($j=0; $j < count($card_messages); $j++) { 
								$c_m = $card_messages[$j];
								
								$card_createdAt_time = strtotime($c_m['createdAt']);
								$list_id = $card['idList'];
								$tmp_rules = (count($board_rules[$list_id]['rules']) > 0) ? $board_rules[$list_id]['rules'] : $board_rules['default']['rules'];
								$min_rule_age = get_min_age($tmp_rules);
								if ($min_rule_age == 0) $min_rule_age = 1;
								$dif_time = $cur_time - $card_createdAt_time;

								$tmp_data = array('email' => $c_m['email'], 'name'=>$c_m['name'], 'cardlink' => $card['shortUrl']);
								if ($c_m['name'] && $c_m['email']) {
									if (($dif_time > ($min_rule_age-1)*24*60*60) && ($dif_time < $min_rule_age*24*60*60)) {
										if (!array_deep_search($contacts1, $tmp_data)) {
											array_push($contacts1, $tmp_data);
										}
									} else {
										if (!array_deep_search($contacts2, $tmp_data)) {
											array_push($contacts2, $tmp_data);
										}
									}
								}

								// if (stripos($c_m['message'], '**email sent**') !== false) {
								// 	if (!array_deep_search($sent, $tmp_data)) {
								// 		array_push($sent, $tmp_data);
								// 	}
								// } else if(stripos($c_m['message'], '**email received**') !== false) {
								// 	if (!array_deep_search($received, $tmp_data)) {
								// 		array_push($received, $tmp_data);
								// 	}
								// } else {
								// 	continue;
								// }
							}
						}
					}
					// Send email
					$to = $userEmail;
					$subject = "Contact Today";
					
					$headers = "MIME-Version: 1.0" . "\r\n";
					$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
					$headers .= 'From: <admin@trellocrm.com>' . "\r\n";
					// $headers .= 'Cc: myboss@example.com' . "\r\n";

					$message = create_email_temp($contacts1, $contacts2, $board_url['shortUrl']);
					echo $to.'<br/>';
                    print_r($message);
                    echo '<br/>================================================================================<br/>';
                    
					mail($to,$subject,$message,$headers);
				}
				sendSlackMessage($to);
			}

		}
	}

	/**
	 * Send slack message
	**/
	function sendSlackMessage($to) {
		$channel = "https://hooks.slack.com/services/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";
		// $channel = "https://hooks.slack.com/services/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";
		$ch = curl_init($channel);
		$data = "payload=" . json_encode(array(
			"text" => "Send an E-mail to ".$to,
		));
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	/**
	 * Get min age from Instance's rules
	**/
	function get_min_age($rules) {
		$min_rule = min($rules);
		$min_age = $min_rule['age'];
		return intval($min_age);
	}
	/**
	 * Create email Template
	**/
	function create_email_temp($sent, $received, $board_url) {
		$sent_html = "";
		$received_html = "";
		if (count($sent) > 0) {
			foreach ($sent as $k => $v) {
				$sent_html .= "<li>".$v['name']." - <a href='mailto:".$v['email']."'>Compose Email</a> OR <a href='".$v['cardlink']."'>Record a Touch</a></li>";
			}
		} else {
			$sent_html = "<p>There is no activity today</p>";
		}
		if (count($received)>0) {
			foreach ($received as $k => $v) {
				$received_html .= "<li>".$v['name']." - <a href='mailto:".$v['email']."'>Compose Email</a> OR <a href='".$v['cardlink']."'>Record a Touch</a></li>";
			}
		}  else {
			$received_html = "<p>There is no activity today</p>";
		}
		$message = "
			<html>
			<head>
				<title>Contact Today</title>
			</head>
			<body>
				<p>Good morning networking wizard!</p>
				<p>Start your day out right and touch base with the folks below. Even a short note or nudge is better than falling off of the radar. Go go go!</p>
				<p style='font-weight: bold'>People to email today:</p>
				<ul>
					".$sent_html."
				</ul>
				<p style='font-weight: bold'>People you are falling out of touch with:</p>
				<ul>
					".$received_html."
				</ul>
				<p>Does anything above look wrong? If so, you might want to move your contacts to more appropriate lists in your CRM board <a href='".$board_url."'>here (".$board_url.")</a> or even update the timing thresholds for your lists in your TrelloCRM settings <a href='https://my.trellocrm.com'>here (https://my.trellocrm.com)</a></p>
			</body>
			</html>
		";
		return $message;
	}

	function array_deep_search($array, $search) {
		$r_val = false;
		foreach ($array as $k => $v) {
			if ($v === $search) {
				$r_val = true;
				break;
			}
		}
		return $r_val;
	}
?>