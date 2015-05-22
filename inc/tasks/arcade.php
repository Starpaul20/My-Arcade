<?php
/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

function task_arcade($task)
{
	global $mybb, $db, $lang;
	$lang->load("arcade", true);

	require_once MYBB_ROOT."inc/functions_arcade.php";
	require_once MYBB_ROOT."inc/class_arcade.php";
	$arcade = new Arcade;

	// Delete sessions older than 36 hours
	$cut = TIME_NOW-(60*60*36);
	$db->delete_query("arcadesessions", "dateline < '{$cut}'");

	// Tournament updating
	if($mybb->settings['enabletournaments'] == 1)
	{
		// Change waiting status to running
		$query = $db->query("
			SELECT t.*, g.name, g.active
			FROM ".TABLE_PREFIX."arcadetournaments t
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
			WHERE t.status='1' AND t.numplayers=POW(2, t.rounds) AND g.active='1'
		");
		while($open = $db->fetch_array($query))
		{
			$information = array();
			$information['1']['starttime'] = TIME_NOW;

			$update_tournament = array(
				"status" => 2,
				"round" => 1,
				"information" => serialize($information)
			);

			$db->update_query("arcadetournaments", $update_tournament, "tid='{$open['tid']}'");

			$query = $db->query("
				SELECT p.uid, u.tournamentnotify, u.receivepms, u.language, u.email
				FROM ".TABLE_PREFIX."arcadetournamentplayers p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.tid='{$open['tid']}'
			");
			while($player = $db->fetch_array($query))
			{
				if($player['tournamentnotify'] == 1)
				{
					$player_pm = array(
						'subject' => 'tournament_subject',
						'message' => array('tournament_message', $open['name'], $open['days'], $open['tries']),
						'touid' => $player['uid'],
						'receivepms' => (int)$player['receivepms'],
						'language' => $player['language'],
						'language_file' => 'arcade'
					);

					send_pm($player_pm, $open['uid']);
				}

				else if($player['tournamentnotify'] == 2)
				{
					$emailsubject = $lang->sprintf($lang->tournament_email_subject, $mybb->settings['bbname']);
					$emailmessage = $lang->sprintf($lang->tournament_message, $open['name'], $open['days'], $open['tries']);

					my_mail($player['email'], $emailsubject, $emailmessage);
				}

				// My Alerts support
				if($db->table_exists("alert_types") && class_exists("MybbStuff_MyAlerts_AlertTypeManager"))
				{
					$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('arcade_newround');

					if ($alertType != null && $alertType->getEnabled()) {
						$alert = new MybbStuff_MyAlerts_Entity_Alert($player['uid'], $alertType, $open['tid'], $open['uid']);
								$alert->setExtraDetails(
								array(
									'tid' 		=> $open['tid'],
									'g_name' => $db->escape_string($open['name'])
								));
						MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
					}
				}
			}
		}

		// Changing round
		$query2 = $db->query("
			SELECT t.*, g.*, COUNT(p.pid) AS roundplayers
			FROM ".TABLE_PREFIX."arcadetournaments t
			LEFT JOIN ".TABLE_PREFIX."arcadetournamentplayers p ON (p.tid=t.tid AND t.round=p.round)
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
			WHERE t.status='2' AND t.rounds != t.round AND g.active='1' AND p.attempts != '0'
		");
		while($round = $db->fetch_array($query2))
		{
			$playersneeded = pow(2, $round['rounds'] - $round['round']);
			$roundtime = $round['days']*60*60*24;
			$information = unserialize($round['information']);

			if(($information[$round['round']]['starttime'] + $roundtime) <= TIME_NOW)
			{
				// There are enough players to go to the next round
				if($round['roundplayers'] >= $playersneeded)
				{
					$information[$round['round']]['endtime'] = TIME_NOW;
					$information[($round['round']+1)]['starttime'] = TIME_NOW;

					$update_tournament = array(
						"round"			=> $round['round'] + 1,
						"information"	=> serialize($information)
					);

					$db->update_query("arcadetournaments", $update_tournament, "tid='{$round['tid']}'");

					$query3 = $db->query("
						SELECT p.*, u.*, p.uid AS player, p.username AS playerusername
						FROM ".TABLE_PREFIX."arcadetournamentplayers p
						LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
						WHERE p.tid='{$round['tid']}' AND p.round='{$round['round']}' AND p.attempts != '0'
						ORDER BY p.score {$round['sortby']}, p.scoreattempt ASC, p.attempts ASC, p.timeplayed ASC
						LIMIT 0, {$playersneeded}
					");
					while($player = $db->fetch_array($query3))
					{
						$insert_player = array(
							"tid" => intval($round['tid']),
							"uid" => intval($player['player']),
							"username" => $db->escape_string($player['playerusername']),
							"round" => $round['round'] + 1
						);
						$db->insert_query("arcadetournamentplayers", $insert_player);

						if($player['tournamentnotify'] == 1)
						{
							$round_pm = array(
								'subject' => 'tournament_subject',
								'message' => array('tournament_message', $round['name'], $round['days'], $round['tries']),
								'touid' => $player['uid'],
								'receivepms' => (int)$player['receivepms'],
								'language' => $player['language'],
								'language_file' => 'arcade'
							);

							send_pm($round_pm, $round['uid']);
						}

						else if($player['tournamentnotify'] == 2)
						{
							$emailsubject = $lang->sprintf($lang->tournament_email_subject, $mybb->settings['bbname']);
							$emailmessage = $lang->sprintf($lang->tournament_message, $round['name'], $round['days'], $round['tries']);

							my_mail($player['email'], $emailsubject, $emailmessage);
						}

						// My Alerts support
						if($db->table_exists("alert_types") && class_exists("MybbStuff_MyAlerts_AlertTypeManager"))
						{
							$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('arcade_newround');

							if ($alertType != null && $alertType->getEnabled()) {
								$alert = new MybbStuff_MyAlerts_Entity_Alert($player['uid'], $alertType, $round['tid'], $round['uid']);
										$alert->setExtraDetails(
										array(
											'tid' 		=> $round['tid'],
											'g_name' => $db->escape_string($round['name'])
										));
								MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
							}
						}
					}
				}

				// Only one played, so declare them champion and end tournament
				else if($round['roundplayers'] == 1)
				{
					$query4 = $db->query("
						SELECT *
						FROM ".TABLE_PREFIX."arcadetournamentplayers
						WHERE tid='{$round['tid']}' AND round='{$round['round']}' AND attempts != '0'
						ORDER BY score {$round['sortby']}, scoreattempt ASC, attempts ASC, timeplayed ASC
						LIMIT 1
					");
					$champ = $db->fetch_array($query4);
				
					$information[$round['round']]['endtime'] = TIME_NOW;
					$information['reason'] = $lang->not_enough_played;

					$update_tournament = array(
						"status" => 3,
						"champion" => intval($champ['uid']),
						"finishdateline" => TIME_NOW,
						"information" => serialize($information)
					);
					$db->update_query("arcadetournaments", $update_tournament, "tid='{$round['tid']}'");
				}

				// Nobody played, so end the tournament
				else if($round['roundplayers'] == 0)
				{
					$information[$round['round']]['endtime'] = TIME_NOW;
					$information['reason'] = $lang->no_players_played;

					$update_tournament = array(
						"status" => 3,
						"finishdateline" => TIME_NOW,
						"information" => serialize($information)
					);
					$db->update_query("arcadetournaments", $update_tournament, "tid='{$round['tid']}'");
				}
			}
		}

		// Change running status to finished
		$query5 = $db->query("
			SELECT t.*, g.sortby, g.active, COUNT(p.pid) AS roundplayers
			FROM ".TABLE_PREFIX."arcadetournaments t
			LEFT JOIN ".TABLE_PREFIX."arcadetournamentplayers p ON (p.tid=t.tid AND t.round=p.round)
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
			WHERE t.status='2' AND t.rounds=t.round AND g.active='1'
			GROUP BY t.tid
		");
		while($finished = $db->fetch_array($query5))
		{
			$roundtime = $finished['days']*60*60*24;
			$information = unserialize($finished['information']);

			if(($information[$finished['round']]['starttime'] + $roundtime) <= TIME_NOW)
			{
				$information[$finished['round']]['endtime'] = TIME_NOW;
				$information['reason'] = $lang->finished_playing;

				$query6 = $db->query("
					SELECT *
					FROM ".TABLE_PREFIX."arcadetournamentplayers
					WHERE tid='{$finished['tid']}' AND round='{$finished['round']}' AND attempts != '0'
					ORDER BY score {$finished['sortby']}, scoreattempt ASC, attempts ASC, timeplayed ASC
					LIMIT 1
				");
				$champ = $db->fetch_array($query6);

				$update_tournament = array(
					"status" => 3,
					"champion" => intval($champ['uid']),
					"finishdateline" => TIME_NOW,
					"information" => serialize($information)
				);
				$db->update_query("arcadetournaments", $update_tournament, "tid='{$finished['tid']}'");
			}
		}

		// Cancel tournaments if they don't get enough players
		if($mybb->settings['tournaments_canceltime'] != 0)
		{
			$tourcut = TIME_NOW-($mybb->settings['tournaments_canceltime']*60*60*24);

			$query6 = $db->query("
				SELECT *
				FROM ".TABLE_PREFIX."arcadetournaments
				WHERE status='1' AND numplayers != POW(2, rounds) AND dateline < '{$tourcut}'
			");
			while($cancelled = $db->fetch_array($query6))
			{
				$information = unserialize($cancelled['information']);

				$information['reason'] = $lang->lack_of_players;

				$update_tournament = array(
					"status" => 4,
					"finishdateline" => TIME_NOW,
					"information" => serialize($information)
				);
				$db->update_query("arcadetournaments", $update_tournament, "tid='{$cancelled['tid']}'");

				$cancel_tournament_players = array(
					"status" => 4
				);
				$db->update_query("arcadetournamentplayers", $cancel_tournament_players, "tid='{$cancelled['tid']}' AND status != '3'");
			}
		}
	}
	update_tournaments_stats();

	add_task_log($task, $lang->arcade_task_ran);
}
?>