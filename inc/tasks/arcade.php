<?php
/**
 * My Arcade
 * Copyright 2012 Starpaul20
 */

function task_arcade($task)
{
	global $mybb, $db, $lang, $Alerts;
	$lang->load("arcade");

	require_once MYBB_ROOT."inc/functions_arcade.php";
	require_once MYBB_ROOT."inc/class_arcade.php";
	$arcade = new Arcade;

	// MyAlerts support
	if($db->table_exists("alerts"))
	{
		require_once MYBB_ROOT.'inc/plugins/MyAlerts/Alerts.class.php';
		$Alerts = new Alerts($mybb, $db);
	}

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
				SELECT p.tid, u.*
				FROM ".TABLE_PREFIX."arcadetournamentplayers p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE p.tid='{$open['tid']}'
			");
			while($player = $db->fetch_array($query))
			{
				if($player['tournamentnotify'] == 1 && $mybb->settings['enablepms'] == 1 && $player['receivepms'] != 0)
				{
					// Bring up the PM handler
					require_once MYBB_ROOT."inc/datahandlers/pm.php";
					$pmhandler = new PMDataHandler();

					$pm_subject = $lang->sprintf($lang->tournament_subject, $mybb->settings['bbname']);
					$pm_message = $lang->sprintf($lang->tournament_message, $open['name'], $open['days'], $open['tries']);

					$pm = array(
						"subject" => $pm_subject,
						"message" => $pm_message,
						"fromid" => $open['uid'],
						"toid" => array($player['uid'])
					);

					$pm['options'] = array(
						"signature" => 1,
						"disablesmilies" => 0,
						"savecopy" => 1,
						"readreceipt" => 0
					);

					$pmhandler->set_data($pm);

					// Now let the pm handler do all the hard work.
					if(!$pmhandler->validate_pm())
					{
						$pm_errors = $pmhandler->get_friendly_errors();
						{
							$errors = $pm_errors;
						}
					}
					else
					{
						$pminfo = $pmhandler->insert_pm();
					}
				}

				else if($player['tournamentnotify'] == 2)
				{
					$emailsubject = $lang->sprintf($lang->tournament_email_subject, $mybb->settings['bbname']);
					$emailmessage = $lang->sprintf($lang->tournament_message, $open['name'], $open['days'], $open['tries']);

					my_mail($player['email'], $emailsubject, $emailmessage);
				}

				// MyAlerts support
				if($db->table_exists("alerts") && $mybb->settings['myalerts_enabled'])
				{
					$Alerts->addAlert($player['uid'], 'arcade_newround', 0, $open['uid'], array('tid' => $open['tid'], 'name' => $open['name']));
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

						if($player['tournamentnotify'] == 1 && $mybb->settings['enablepms'] == 1 && $player['receivepms'] != 0)
						{
							// Bring up the PM handler
							require_once MYBB_ROOT."inc/datahandlers/pm.php";
							$pmhandler = new PMDataHandler();

							$pm_subject = $lang->sprintf($lang->tournament_subject, $mybb->settings['bbname']);
							$pm_message = $lang->sprintf($lang->tournament_message, $round['name'], $round['days'], $round['tries']);

							$pm = array(
								"subject" => $pm_subject,
								"message" => $pm_message,
								"fromid" => $round['uid'],
								"toid" => array($player['uid'])
							);

							$pm['options'] = array(
								"signature" => 1,
								"disablesmilies" => 0,
								"savecopy" => 1,
								"readreceipt" => 0
							);

							$pmhandler->set_data($pm);

							// Now let the pm handler do all the hard work.
							if(!$pmhandler->validate_pm())
							{
								$pm_errors = $pmhandler->get_friendly_errors();
								{
									$errors = $pm_errors;
								}
							}
							else
							{
								$pminfo = $pmhandler->insert_pm();
							}
						}

						else if($player['tournamentnotify'] == 2)
						{
							$emailsubject = $lang->sprintf($lang->tournament_email_subject, $mybb->settings['bbname']);
							$emailmessage = $lang->sprintf($lang->tournament_message, $round['name'], $round['days'], $round['tries']);

							my_mail($player['email'], $emailsubject, $emailmessage);
						}

						// My Alerts support
						if($db->table_exists("alerts") && $mybb->settings['myalerts_enabled'])
						{
							$Alerts->addAlert($player['uid'], 'arcade_newround', 0, $round['uid'], array('tid' => $round['tid'], 'name' => $round['name']));
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
			}
		}
	}
	update_tournaments_stats();

	add_task_log($task, $lang->arcade_task_ran);
}
?>