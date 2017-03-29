<?php
/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

require_once MYBB_ROOT."inc/functions_arcade.php";

class Arcade
{
	/**
	 * Submits score (and update if new score is higher)
	 *
	 * @param array Score from game
	 * @param array Name of game
	 * @param array Arcade Session ID
	 * @return true
	 */
	function submit_score($score, $name, $sid)
	{
		global $db, $mybb, $lang, $plugins, $session, $arcade_session;
		$lang->load("arcade");

		$uid = (int)$mybb->user['uid'];
		$mybb->binary_fields["arcadescores"] = array('ipaddress' => true);

		if(!$name)
		{
			error($lang->no_name_input);
		}

		if(!$score)
		{
			error($lang->no_score_input);
		}

		if(!$sid)
		{
			error($lang->no_session_input);
		}

		// Double checking, to make sure this is a proper score (to prevent cross-scoring)
		$query = $db->simple_select("arcadesessions", "*", "sid='{$sid}'");
		$arcade_session = $db->fetch_array($query);

		$query = $db->simple_select("arcadegames", "*", "file='{$name}'");
		$gamecheck = $db->fetch_array($query);

		// Some games input their name by the title not file name, this exists to ensure no errors
		if(!$gamecheck['gid'])
		{
			$query = $db->simple_select("arcadegames", "*", "name='{$name}'");
			$gamecheck = $db->fetch_array($query);
		}

		if($arcade_session['ipaddress'] != $session->packedip)
		{
			error($lang->bad_input);
		}

		if($arcade_session['gid'] != $gamecheck['gid'])
		{
			error($lang->bad_input);
		}

		if($arcade_session['gname'] != $gamecheck['file'])
		{
			error($lang->bad_input);
		}

		if($arcade_session['gtitle'] != $gamecheck['name'])
		{
			error($lang->bad_input);
		}

		if($arcade_session['uid'] != $uid)
		{
			error($lang->bad_input);
		}

		// Looks clean, now time to get scoring
		$game = get_game($arcade_session['gid']);

		// Score check if IBProArcade v32 game
		if(is_file(MYBB_ROOT."arcade/gamedata/".$game['file']."/v32game.txt"))
		{
			$controlscore = floatval($score * $arcade_session['randchar1'] ^ $arcade_session['randchar2']);

			if($mybb->input['enscore'] != $controlscore || !isset($arcade_session['randchar1']) || !isset($arcade_session['randchar2']))
			{
				my_unsetcookie('arcadesession');

				error($lang->cheat_score);
			}
		}

		if($uid != 0)
		{
			// Check to see if this user already has a score
			$query = $db->simple_select("arcadescores", "*", "uid='{$uid}' AND gid='{$game['gid']}'");
			$current_score = $db->fetch_array($query);

			if($current_score['sid'])
			{
				if(($current_score['score'] < $score && $game['sortby'] == "desc") || ($current_score['score'] > $score && $game['sortby'] == "asc"))
				{
					$timeplayed = TIME_NOW - $arcade_session['dateline'];

					$update_score = array(
						"gid" => (int)$game['gid'],
						"uid" => (int)$uid,
						"username" => $db->escape_string($mybb->user['username']),
						"score" => $db->escape_string($score),
						"dateline" => TIME_NOW,
						"timeplayed" => (int)$timeplayed,
						"ipaddress" => $db->escape_binary($session->packedip)
					);
					$db->update_query("arcadescores", $update_score, "gid='{$game['gid']}' AND uid='{$uid}'");

					$arguments = array("gid" => $game['gid'], "score" => $score);
					$plugins->run_hooks("class_arcade_submit_score_update_score", $arguments);

					$message = $lang->redirect_score_updated;
				}
				else
				{
					$message = $lang->redirect_score_unchanged;
				}
			}
			else
			{
				$timeplayed = TIME_NOW - $arcade_session['dateline'];

				$new_score = array(
					"gid" => (int)$game['gid'],
					"uid" => (int)$uid,
					"username" => $db->escape_string($mybb->user['username']),
					"score" => $db->escape_string($score),
					"dateline" => TIME_NOW,
					"timeplayed" => (int)$timeplayed,
					"ipaddress" => $db->escape_binary($session->packedip)
				);
				$db->insert_query("arcadescores", $new_score);

				$arguments = array("gid" => $game['gid'], "score" => $score);
				$plugins->run_hooks("class_arcade_submit_score_new_score", $arguments);

				$message = $lang->redirect_score_added;
			}

			// Update Champion
			$query = $db->simple_select("arcadechampions", "*", "gid='{$game['gid']}'");
			$current_champion = $db->fetch_array($query);

			if($current_champion['cid'])
			{
				if(($current_champion['score'] < $score && $game['sortby'] == "desc") || ($current_champion['score'] > $score && $game['sortby'] == "asc"))
				{
					// Send old champion a notice (pm or email depending on setting) that they've lost their championship
					if($current_champion['uid'] != $mybb->user['uid'])
					{
						$champ = get_user($current_champion['uid']);
						if($champ['champnotify'] == 1)
						{
							$champ_pm = array(
								'subject' => array('champ_subject', $game['name']),
								'message' => array('champ_pm_message', $mybb->user['username'], $game['name'], $score),
								'touid' => $champ['uid'],
								'receivepms' => (int)$champ['receivepms'],
								'language' => $champ['language'],
								'language_file' => 'arcade'
							);

							send_pm($champ_pm);
						}
						else if($champ['champnotify'] == 2)
						{
							$emailsubject = $lang->sprintf($lang->champ_subject, $game['name']);
							$emailmessage = $lang->sprintf($lang->champ_email_message, $mybb->user['username'], $game['name'], $score);

							my_mail($champ['email'], $emailsubject, $emailmessage);
						}

						// My Alerts support
						if($db->table_exists("alert_types") && class_exists("MybbStuff_MyAlerts_AlertTypeManager"))
						{
							$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('arcade_championship');

							if ($alertType != null && $alertType->getEnabled()) {
								$alert = new MybbStuff_MyAlerts_Entity_Alert($current_champion['uid'], $alertType, $game['gid'], $mybb->user['uid']);
										$alert->setExtraDetails(
										array(
											'gid' 		=> $game['gid'],
											'g_name' => $db->escape_string($game['name'])
										));
								MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
							}
						}
					}

					$update_champ = array(
						"uid" => (int)$uid,
						"username" => $db->escape_string($mybb->user['username']),
						"score" => $db->escape_string($score),
						"dateline" => TIME_NOW
					);
					$db->update_query("arcadechampions", $update_champ, "gid='{$game['gid']}'");

					$arguments = array("gid" => $game['gid'], "score" => $score);
					$plugins->run_hooks("class_arcade_submit_score_update_champion", $arguments);
				}
			}
			else
			{
				$new_champion = array(
					"gid" => (int)$game['gid'],
					"uid" => (int)$uid,
					"username" => $db->escape_string($mybb->user['username']),
					"score" => $db->escape_string($score),
					"dateline" => TIME_NOW
				);
				$db->insert_query("arcadechampions", $new_champion);

				$arguments = array("gid" => $game['gid'], "score" => $score);
				$plugins->run_hooks("class_arcade_submit_score_new_champion", $arguments);
			}
		}
		else
		{
			// Scores are not kept for guests, so redirect them
			$message = $lang->redirect_score_guest;
		}

		// Delete session cookie only if not a tournament
		if($arcade_session['tid'] == 0)
		{
			my_unsetcookie('arcadesession');
		}

		return $message;
	}

	/**
	 * Submits tournament score (and updates if new score is higher)
	 *
	 * @param array Score from game
	 * @param array Name of game
	 * @param array Arcade Session ID
	 * @return true
	 */
	function submit_tournament($score, $name, $sid)
	{
		global $db, $mybb, $lang, $plugins, $arcade_session;
		$lang->load("arcade");

		$uid = (int)$mybb->user['uid'];

		// Submit score to high scores also (also checks for cheating/errors)
		$this->submit_score($score, $name, $sid);

		// Call necessary info
		$query = $db->simple_select("arcadesessions", "*", "sid='{$sid}'");
		$arcade_session = $db->fetch_array($query);

		$tournament = get_tournament($arcade_session['tid']);
		$game = get_game($tournament['gid']);
		$information = unserialize($tournament['information']);

		// Check to see if this user already has a score
		$query = $db->simple_select("arcadetournamentplayers", "*", "uid='{$uid}' AND tid='{$tournament['tid']}' AND round='{$tournament['round']}'");
		$current_score = $db->fetch_array($query);

		if(($current_score['score'] < $score && $game['sortby'] == "desc") || ($current_score['score'] > $score && $game['sortby'] == "asc"))
		{
			$update_score = array(
				"score" => $db->escape_string($score),
				"attempts" => $current_score['attempts'] + 1,
				"scoreattempt" => $current_score['attempts'] + 1,
				"timeplayed" => TIME_NOW
			);
			$db->update_query("arcadetournamentplayers", $update_score, "pid='{$current_score['pid']}'");

			$plugins->run_hooks("class_arcade_submit_tournament_update_score");

			$message = $lang->redirect_tournament_score_submitted;
		}
		else
		{
			$update_score = array(
				"attempts" => $current_score['attempts'] + 1
			);

			$db->update_query("arcadetournamentplayers", $update_score, "pid='{$current_score['pid']}'");

			$plugins->run_hooks("class_arcade_submit_tournament_update_player");

			$message = $lang->redirect_tournament_score_unchanged;
		}

		my_unsetcookie('arcadesession');

		return $message;
	}

	/**
	 * Delete a score
	 *
	 * @param array Score ID
	 * @param array Game ID
	 * @return boolean true
	 */
	function delete_score($sid, $gid)
	{
		global $db, $plugins;

		$arguments = array("sid" => $sid, "gid" => $gid);
		$plugins->run_hooks("class_arcade_delete_score", $arguments);

		$sid = (int)$sid;
		$db->delete_query("arcadescores", "sid='{$sid}'");

		update_champion($gid);

		return true;
	}

	/**
	 * Cancel a tournament
	 *
	 * @param array Tournaments ID
	 * @param array Reason for cancellation
	 * @return boolean true
	 */
	function cancel_tournament($tid, $cancel_reason)
	{
		global $db, $plugins;

		$tid = (int)$tid;

		$cancel_info = array(
			'cancel_uid' => (int)$mybb->user['uid'],
			'reason' => $cancel_reason
		);

		$cancel_tournament = array(
			"status" => 4,
			"finishdateline" => TIME_NOW,
			"information" => $db->escape_string(serialize($cancel_info))
		);
		$db->update_query("arcadetournaments", $cancel_tournament, "tid='{$tid}'");

		$cancel_tournament_players = array(
			"status" => 4
		);
		$db->update_query("arcadetournamentplayers", $cancel_tournament_players, "tid='{$tid}' AND status != '3'");
		update_tournaments_stats();

		$plugins->run_hooks("class_arcade_cancel_tournament", $tid);

		return true;
	}

	/**
	 * Disqualify user from tournament
	 *
	 * @param array Tournaments ID
	 * @param array User ID
	 * @return boolean true
	 */
	function disqualify_user($tid, $uid)
	{
		global $db, $plugins;

		$disqualify_user = array(
			"status" => 3
		);
		$db->update_query("arcadetournamentplayers", $disqualify_user, "tid='{$tid}' AND uid='{$uid}'");

		$tournament = get_tournament($tid);

		if($tournament['status'] != 2)
		{
			$update_tournament = array(
				"numplayers" => $tournament['numplayers'] - 1
			);
			$db->update_query("arcadetournaments", $update_tournament, "tid='{$tid}'");
		}

		update_tournaments_stats();

		$arguments = array("tid" => $tid, "uid" => $uid);
		$plugins->run_hooks("class_arcade_disqualify_user", $arguments);

		return true;
	}

	/**
	 * Delete a Tournament
	 *
	 * @param array Tournament ID
	 * @return boolean true
	 */
	function delete_tournament($tid)
	{
		global $db, $plugins;

		$tid = (int)$tid;
		$plugins->run_hooks("class_arcade_delete_tournament", $tid);

		$db->delete_query("arcadetournaments", "tid='{$tid}'");
		$db->delete_query("arcadetournamentplayers", "tid='{$tid}'");

		update_tournaments_stats();

		return true;
	}
}
