<?php
/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tournaments.php');

$templatelist = "arcade_online,tournaments_waiting_bit,tournaments_waiting,tournaments_no_tournaments,tournaments_running,tournaments_running_bit,tournaments_finished_bit,tournaments_view_rounds_profile";
$templatelist .= ",arcade_menu,tournaments_view_rounds_champion,tournaments_view_rounds_bit_info,tournaments_view_rounds_bit,tournaments_view_join,tournaments_view_play,tournaments_view_cancel";
$templatelist .= ",tournaments_view,arcade_online_memberbit,tournaments_cancel_success,tournaments_cancelled,tournaments_cancelled_bit,tournaments_create_tries,tournaments_finished_champion_cancelled";
$templatelist .= ",tournaments_cancel_error_nomodal,tournaments_create_round,tournaments_create_days,tournaments_create_game,tournaments_create,tournaments_view_rounds,tournaments_finished_champion";
$templatelist .= ",arcade_online_memberbit_image_home,arcade_online_memberbit_image_game,tournaments_view_delete,tournaments_finished,tournaments_view_champion,tournaments_view_rounds_disqualify";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_arcade.php";
require_once MYBB_ROOT."inc/class_arcade.php";
$arcade = new Arcade;

// Load global language phrases
$lang->load("tournaments");

if($mybb->settings['enablearcade'] != 1)
{
	error($lang->arcade_disabled);
}

if($mybb->settings['enabletournaments'] != 1)
{
	error($lang->tournaments_disabled);
}

if($mybb->usergroup['canviewarcade'] == 0)
{
	error_no_permission();
}

if($mybb->usergroup['canviewtournaments'] == 0)
{
	error_no_permission();
}

$plugins->run_hooks("tournaments_start");

add_breadcrumb($lang->arcade, "arcade.php");

// Top Menu bar (for members only)
$menu = '';
if($mybb->user['uid'] != 0)
{
	eval("\$menu = \"".$templates->get("arcade_menu")."\";");
}

// Build Who's Online box
$online = '';
if($mybb->settings['arcade_whosonline'] != 0 && $mybb->usergroup['canviewonline'] == 1 && $mybb->user['whosonlinearcade'] == 1)
{
	if($mybb->settings['arcade_whosonline'] == 1 && ($mybb->usergroup['canmoderategames'] == 1 || $mybb->usergroup['cancp'] == 1))
	{
		$online = whos_online();
	}
	elseif($mybb->settings['arcade_whosonline'] == 2 && $mybb->user['uid'])
	{
		$online = whos_online();
	}
	elseif($mybb->settings['arcade_whosonline'] == 3)
	{
		$online = whos_online();
	}
}

// Gets only games this user can view (based on category group permission)
$unviewable = get_unviewable_categories($mybb->user['usergroup']);
if($unviewable)
{
	$cat_sql .= " AND cid NOT IN ($unviewable)";
	$cat_sql_game .= " AND g.cid NOT IN ($unviewable)";
}

// Creating the tournament
if($mybb->input['action'] == "do_create" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

	$plugins->run_hooks("tournaments_do_create_start");
	
	// Let's double check that this game can even be used
	if($game['tournamentselect'] != 1)
	{
		error($lang->error_notournament);
	}

	$category = explode(',', $unviewable);
	if(in_array($game['cid'], $category))
	{
		error($lang->error_nogamepermission);
	}

	if($game['active'] != 1 || !$game['gid'])
	{
		error($lang->error_invalidgame);
	}

	// Check group limits
	if($mybb->usergroup['maxtournamentsday'] > 0)
	{
		$query = $db->simple_select("arcadetournaments", "COUNT(*) AS create_count", "uid='{$mybb->user['uid']}' AND dateline >= '".(TIME_NOW - (60*60*24))."'");
		$create_count = $db->fetch_field($query, "create_count");
		if($create_count >= $mybb->usergroup['maxtournamentsday'])
		{
			$lang->error_max_tournaments_day = $lang->sprintf($lang->error_max_tournaments_day, $mybb->usergroup['maxtournamentsday']);
			error($lang->error_max_tournaments_day);
		}
	}

	// Check number of rounds - stop if number of players exceeds board membership
	$players = pow(2, $mybb->input['rounds']);

	$query = $db->simple_select("users", "COUNT(uid) AS member_count");
	$member_count = $db->fetch_field($query, "member_count");

	if($players > $member_count)
	{
		error($lang->error_toomanyplayers);
	}

	$insert_array = array(
		"gid" => $gid,
		"uid" => (int)$mybb->user['uid'],
		"dateline" => TIME_NOW,
		"status" => 1,
		"rounds" => $mybb->get_input('rounds', MyBB::INPUT_INT),
		"tries" => $mybb->get_input('tries', MyBB::INPUT_INT),
		"days" => $mybb->get_input('days', MyBB::INPUT_INT)
	);
	$tid = $db->insert_query("arcadetournaments", $insert_array);
	update_tournaments_stats();

	// Add creator as first player
	$insert_player = array(
		"tid" => $tid,
		"uid" => (int)$mybb->user['uid'],
		"username" => $db->escape_string($mybb->user['username']),
		"round" => 1
	);
	$db->insert_query("arcadetournamentplayers", $insert_player);

	$plugins->run_hooks("tournaments_do_create_end");
	
	redirect("tournaments.php?action=view&tid={$tid}", $lang->redirect_tournamentcreated);
}

// Creating a new tournament
if($mybb->input['action'] == "create")
{
	add_breadcrumb($lang->create_tournament, "tournaments.php?action=create");

	if($mybb->usergroup['cancreatetournaments'] == 0)
	{
		error($lang->error_nocreatetournaments);
	}

	$plugins->run_hooks("tournaments_create_start");

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);

	if($gid)
	{
		$tournament = get_game($gid);

		if($tournament['tournamentselect'] != 1)
		{
			error($lang->error_notournament);
		}
	}

	// Check group limits
	if($mybb->usergroup['maxtournamentsday'] > 0)
	{
		$query = $db->simple_select("arcadetournaments", "COUNT(*) AS create_count", "uid='{$mybb->user['uid']}' AND dateline >= '".(TIME_NOW - (60*60*24))."'");
		$create_count = $db->fetch_field($query, "create_count");
		if($create_count >= $mybb->usergroup['maxtournamentsday'])
		{
			$lang->error_max_tournaments_day = $lang->sprintf($lang->error_max_tournaments_day, $mybb->usergroup['maxtournamentsday']);
			error($lang->error_max_tournaments_day);
		}
	}

	$query = $db->simple_select("arcadegames", "gid, name", "active='1' AND tournamentselect='1'{$cat_sql}", array('order_by' => 'name', 'order_dir' => 'asc'));
	while($game = $db->fetch_array($query))
	{
		$selected = '';
		if($gid == $game['gid'])
		{
			$selected = "selected=\"selected\"";
		}

		$game['name'] = htmlspecialchars_uni($game['name']);
		eval("\$gameoptions .= \"".$templates->get("tournaments_create_game")."\";");
	}

	$explodedrounds = explode(",", $mybb->settings['tournaments_numrounds']);
	$roundoptions = '';
	if(is_array($explodedrounds))
	{
		foreach($explodedrounds as $key => $val)
		{
			$val = trim($val);
			$players = pow(2, $val);
			if($val != 1)
			{
				$round = $lang->sprintf($lang->games_rounds, $val, $players);
			}
			else
			{
				$round = $lang->sprintf($lang->games_round, $players);
			}

			eval("\$roundoptions .= \"".$templates->get("tournaments_create_round")."\";");
		}
	}

	$explodedtries = explode(",", $mybb->settings['tournaments_numtries']);
	$triesoptions = '';
	if(is_array($explodedtries))
	{
		foreach($explodedtries as $key => $val)
		{
			$val = trim($val);
			if($val != 1)
			{
				$tries = $lang->sprintf($lang->games_tries, $val);
			}
			else
			{
				$tries = $lang->games_try;
			}

			eval("\$triesoptions .= \"".$templates->get("tournaments_create_tries")."\";");
		}
	}

	$explodeddays = explode(",", $mybb->settings['tournaments_numdays']);
	$daysoptions = '';
	if(is_array($explodeddays))
	{
		foreach($explodeddays as $key => $val)
		{
			$val = trim($val);
			if($val != 1)
			{
				$days = $lang->sprintf($lang->games_days, $val);
			}
			else
			{
				$days = $lang->games_day;
			}

			eval("\$daysoptions .= \"".$templates->get("tournaments_create_days")."\";");
		}
	}

	$plugins->run_hooks("tournaments_create_end");

	eval("\$create = \"".$templates->get("tournaments_create")."\";");
	output_page($create);
}

// Viewing a tournament
if($mybb->input['action'] == "view")
{
	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

	$query = $db->query("
		SELECT t.*, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadetournaments t
		LEFT JOIN ".TABLE_PREFIX."users u ON (t.champion=u.uid)
		WHERE t.tid='{$tid}'
	");
	$tournament = $db->fetch_array($query);

	$plugins->run_hooks("tournaments_view_start");

	// Invalid tournament
	if(!$tournament['tid'])
	{
		error($lang->error_invalidtournament);
	}

	$game = get_game($tournament['gid']);

	// Invalid game
	if($game['active'] != 1 || !$game['gid'])
	{
		error($lang->error_invalidgame);
	}

	$category = explode(',', $unviewable);
	if(in_array($game['cid'], $category))
	{
		error($lang->error_nogamepermission);
	}

	add_breadcrumb($lang->viewing_tournament, "tournaments.php?action=view&tid={$tid}");

	$information = unserialize($tournament['information']);

	$dateline = my_date('relative', $tournament['dateline']);
	$lang->game_tournament_started_on = $lang->sprintf($lang->game_tournament_started_on, $game['name'], $dateline);

	$players = pow(2, $tournament['rounds']);
	$tournament_link = '';

	if($tournament['status'] == 1)
	{
		$status_message = $lang->open_for_members;

		$query = $db->simple_select("arcadetournamentplayers", "uid", "tid='{$tid}' AND uid='{$mybb->user['uid']}'");
		$player = $db->fetch_array($query);

		if(($players > $tournament['numplayers']) && $mybb->usergroup['canjointournaments'] == 1 && ($player['uid'] != $mybb->user['uid']))
		{
			$remainingspots = $players - $tournament['numplayers'];
			$lang->join_now = $lang->sprintf($lang->join_now, $remainingspots);

			eval("\$tournament_link = \"".$templates->get("tournaments_view_join")."\";");
		}
	}

	if($tournament['status'] == 2)
	{
		$status_message = $lang->tournament_active;

		$query = $db->simple_select("arcadetournamentplayers", "pid, attempts", "tid='{$tid}' AND round='{$tournament['round']}' AND uid='{$mybb->user['uid']}'");
		$player = $db->fetch_array($query);

		if($player['pid'] && $player['attempts'] < $tournament['tries'])
		{
			eval("\$tournament_link = \"".$templates->get("tournaments_view_play")."\";");
		}
	}

	if($tournament['status'] == 3)
	{
		$status_message = $lang->tournament_finished;
	}

	if($tournament['status'] == 4)
	{
		$status_message = $lang->tournament_cancelled;
	}

	// Does the current user have permission to cancel this tournament? Show cancel link
	$cancel_link = '';
	if($mybb->usergroup['canmoderategames'] == 1 && $tournament['status'] < 3)
	{
		eval("\$cancel_link = \"".$templates->get("tournaments_view_cancel")."\";");
	}

	// Does the current user have permission to delete this tournament? Show delete link
	$delete_link = '';
	if($mybb->usergroup['canmoderategames'] == 1)
	{
		eval("\$delete_link = \"".$templates->get("tournaments_view_delete")."\";");
	}

	$colspan = $players + 1;

	$champion = '';
	if($tournament['champion'])
	{
		$tournament['username'] = format_name(htmlspecialchars_uni($tournament['username']), $tournament['usergroup'], $tournament['displaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$tournament['champion']}";
		}
		else
		{
			$profilelink = get_profile_link($tournament['champion']);
		}

		eval("\$champion = \"".$templates->get('tournaments_view_champion')."\";");
	}
	else
	{
		$champion = $lang->na;
	}

	eval("\$rounds .= \"".$templates->get('tournaments_view_rounds_champion')."\";");

	for($rid = $tournament['rounds']; $rid > 0; $rid--)
	{
		$rounds_bit = '';
		$colspan_round = pow(2, ($rid - 1));
		$numplayers = $players / $colspan_round;

		$query = $db->query("
			SELECT p.*, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."arcadetournamentplayers p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.tid='{$tid}' AND p.status !='3' AND p.round='{$rid}'
			ORDER BY p.score {$game['sortby']}, p.attempts ASC
		");
		$players_count = $db->num_rows($query);

		while($player = $db->fetch_array($query))
		{
			$alt_bg = alt_trow();
			$width = floor(100/$numplayers);

			$player['username'] = format_name(htmlspecialchars_uni($player['username']), $player['usergroup'], $player['displaygroup']);

			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$player['uid']}";
			}
			else
			{
				$profilelink = get_profile_link($player['uid']);
			}

			eval("\$player['username'] = \"".$templates->get("tournaments_view_rounds_profile")."\";");

			$disqualifylink = '';
			if($mybb->usergroup['canmoderategames'] == 1 && $tournament['status'] < 3)
			{
				eval("\$disqualifylink = \"".$templates->get("tournaments_view_rounds_disqualify")."\";");
			}

			if($tournament['status'] == 3 || $tournament['status'] == 2)
			{
				$player['score'] = my_number_format((float)$player['score']);
				$lang->out_of_tries = $lang->sprintf($lang->out_of_tries, $tournament['tries']);
				if($player['scoreattempt'] == 1)
				{
					$tries_needed = $lang->try_needed;
				}
				else
				{
					$tries_needed = $lang->sprintf($lang->tries_needed, $player['scoreattempt']);
				}

				if($player['timeplayed'])
				{
					$dateline = my_date('relative', $player['timeplayed']);
				}
				else
				{
					$dateline = $lang->na;
				}

				eval("\$rounds_bit_info = \"".$templates->get('tournaments_view_rounds_bit_info')."\";");
			}

			eval("\$rounds_bit .= \"".$templates->get('tournaments_view_rounds_bit')."\";");
		}

		for($pid = $numplayers - $players_count; $pid > 0; $pid--)
		{
			$alt_bg = alt_trow();
			$width = floor(100/$numplayers);

			$player['username'] = $lang->na;
			$disqualifylink = '';

			eval("\$rounds_bit .= \"".$templates->get('tournaments_view_rounds_bit')."\";");
		}

		eval("\$rounds .= \"".$templates->get('tournaments_view_rounds')."\";");
	}

	$plugins->run_hooks("tournaments_view_end");

	eval("\$view = \"".$templates->get("tournaments_view")."\";");
	output_page($view);
}

// Joining a tournament
if($mybb->input['action'] == "join")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canjointournaments'] != 1)
	{
		error($lang->error_cannotjointournaments);
	}

	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	$tournament = get_tournament($tid);

	// Invalid tournament
	if(!$tournament['tid'])
	{
		error($lang->error_invalidtournament);
	}

	$plugins->run_hooks("tournaments_join_start");

	$game = get_game($tournament['gid']);

	// Invalid game
	if($game['active'] != 1 || !$game['gid'])
	{
		error($lang->error_invalidgame);
	}

	$category = explode(',', $unviewable);
	if(in_array($game['cid'], $category))
	{
		error($lang->error_nogamepermission);
	}

	// A tournament can only be joined if status is 1 (open for members)
	if($tournament['status'] != 1)
	{
		error($lang->error_notournamentopen);
	}

	// Check to make sure user isn't already in the tournament
	$query = $db->simple_select("arcadetournamentplayers", "pid", "tid='{$tid}' AND uid='{$mybb->user['uid']}'");
	$player = $db->fetch_array($query);

	if($player['pid'])
	{
		error($lang->error_alreadyjoined);
	}

	// Make sure this player isn't over the max allowed
	$players = pow(2, $tournament['rounds']);

	$query = $db->simple_select("arcadetournamentplayers", "*", "tid='{$tournament['tid']}'");
	$playersentered = $db->num_rows($query);

	if($players < $playersentered)
	{
		error($lang->error_jointoomanyplayers);
	}

	// Insert player
	$insert_player = array(
		"tid" => $tournament['tid'],
		"uid" => (int)$mybb->user['uid'],
		"username" => $db->escape_string($mybb->user['username']),
		"round" => 1
	);
	$db->insert_query("arcadetournamentplayers", $insert_player);

	// Update tournament
	$update_tournament = array(
		"numplayers" => $tournament['numplayers'] + 1
	);
	$db->update_query("arcadetournaments", $update_tournament, "tid='{$tournament['tid']}'");

	$starttournament = start_tournament($tournament['tid']);

	$plugins->run_hooks("tournaments_join_end");

	if($starttournament == true)
	{
		redirect("tournaments.php?action=view&tid={$tournament['tid']}", $lang->redirect_tournamentjoined_started);
	}
	else
	{
		redirect("tournaments.php?action=view&tid={$tournament['tid']}", $lang->redirect_tournamentjoined);
	}
}

// Tournaments waiting for players
if($mybb->input['action'] == "waiting")
{
	add_breadcrumb($lang->tournaments_waiting, "tournaments.php?action=waiting");

	$plugins->run_hooks("tournaments_waiting_start");

	// Fetch the tournaments which will be displayed on this page
	$query = $db->query("
		SELECT t.tid, t.dateline, t.rounds, t.numplayers, g.name
		FROM ".TABLE_PREFIX."arcadetournaments t
		LEFT JOIN ".TABLE_PREFIX."arcadetournamentplayers p ON (p.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
		WHERE t.status='1' AND g.active='1'{$cat_sql_game}
	");
	while($tournament = $db->fetch_array($query))
	{
		$tournament['name'] = htmlspecialchars_uni($tournament['name']);
		$dateline = my_date('relative', $tournament['dateline']);

		$players = pow(2, $tournament['rounds']);
		$remaining = $players-$tournament['numplayers'];

		$alt_bg = alt_trow();
		eval("\$tournament_bit .= \"".$templates->get("tournaments_waiting_bit")."\";");
	}

	if(!$tournament_bit)
	{
		$colspan = 3;
		eval("\$tournament_bit = \"".$templates->get("tournaments_no_tournaments")."\";");
	}

	$plugins->run_hooks("tournaments_waiting_end");

	eval("\$waiting = \"".$templates->get("tournaments_waiting")."\";");
	output_page($waiting);
}

// Tournaments running
if($mybb->input['action'] == "running")
{
	add_breadcrumb($lang->tournaments_running, "tournaments.php?action=running");

	$plugins->run_hooks("tournaments_running_start");

	// Fetch the tournaments which will be displayed on this page
	$query = $db->query("
		SELECT t.tid, t.numplayers, t.days, t.information, g.name
		FROM ".TABLE_PREFIX."arcadetournaments t
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
		WHERE t.status='2' AND g.active='1'{$cat_sql_game}
	");
	while($tournament = $db->fetch_array($query))
	{
		$information = unserialize($tournament['information']);

		$tournament['name'] = htmlspecialchars_uni($tournament['name']);
		$dateline = my_date('relative', $information['1']['starttime']);

		$alt_bg = alt_trow();
		eval("\$tournament_bit .= \"".$templates->get("tournaments_running_bit")."\";");
	}

	if(!$tournament_bit)
	{
		$colspan = 4;
		eval("\$tournament_bit = \"".$templates->get("tournaments_no_tournaments")."\";");
	}

	$plugins->run_hooks("tournaments_running_end");

	eval("\$running = \"".$templates->get("tournaments_running")."\";");
	output_page($running);
}

// Tournaments finished
if($mybb->input['action'] == "finished")
{
	add_breadcrumb($lang->tournaments_finished, "tournaments.php?action=finished");

	$plugins->run_hooks("tournaments_finished_start");

	// Fetch the tournaments which will be displayed on this page
	$query = $db->query("
		SELECT t.tid, t.champion, t.finishdateline, t.numplayers, g.name, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadetournaments t
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.champion)
		WHERE t.status='3' AND g.active='1'{$cat_sql_game}
	");
	while($tournament = $db->fetch_array($query))
	{
		$tournament['name'] = htmlspecialchars_uni($tournament['name']);
		$dateline = my_date('relative', $tournament['finishdateline']);

		$champion = '';
		if($tournament['champion'])
		{
			$tournament['username'] = format_name(htmlspecialchars_uni($tournament['username']), $tournament['usergroup'], $tournament['displaygroup']);

			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$tournament['champion']}";
			}
			else
			{
				$profilelink = get_profile_link($tournament['champion']);
			}

			eval("\$champion = \"".$templates->get("tournaments_finished_champion")."\";");
		}
		else
		{
			eval("\$champion = \"".$templates->get("tournaments_finished_champion_cancelled")."\";");
		}

		$alt_bg = alt_trow();
		eval("\$tournament_bit .= \"".$templates->get("tournaments_finished_bit")."\";");
	}

	if(!$tournament_bit)
	{
		$colspan = 4;
		eval("\$tournament_bit = \"".$templates->get("tournaments_no_tournaments")."\";");
	}

	$plugins->run_hooks("tournaments_finished_end");

	eval("\$finished = \"".$templates->get("tournaments_finished")."\";");
	output_page($finished);
}

// Tournaments cancelled
if($mybb->input['action'] == "cancelled")
{
	if($mybb->usergroup['canmoderategames'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->tournaments_cancelled, "tournaments.php?action=cancelled");

	$plugins->run_hooks("tournaments_cancelled_start");

	// Fetch the tournaments which will be displayed on this page
	$query = $db->query("
		SELECT t.tid, t.uid, t.finishdateline, g.name, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadetournaments t
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=t.gid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		WHERE t.status='4' AND g.active='1'{$cat_sql_game}
	");
	while($tournament = $db->fetch_array($query))
	{
		$tournament['name'] = htmlspecialchars_uni($tournament['name']);
		$tournament['username'] = format_name(htmlspecialchars_uni($tournament['username']), $tournament['usergroup'], $tournament['displaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$tournament['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($tournament['uid']);
		}

		$dateline = my_date('relative', $tournament['finishdateline']);

		$alt_bg = alt_trow();
		eval("\$tournament_bit .= \"".$templates->get("tournaments_cancelled_bit")."\";");
	}

	if(!$tournament_bit)
	{
		$colspan = 4;
		eval("\$tournament_bit = \"".$templates->get("tournaments_no_tournaments")."\";");
	}

	$plugins->run_hooks("tournaments_cancelled_end");

	eval("\$cancelled = \"".$templates->get("tournaments_cancelled")."\";");
	output_page($cancelled);
}

// Cancel a specific tournament
if($mybb->input['action'] == "do_cancel" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("tournaments_do_cancel_start");

	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	$tournament = get_tournament($tid);

	if($mybb->usergroup['canmoderategames'] == 0)
	{
		$message = $lang->error_cancel_nopermission;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	if(!$tournament['tid'])
	{
		$message = $lang->error_invalidtournament;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	$arcade->cancel_tournament($tournament['tid'], $mybb->input['cancel_reason']);
	log_arcade_action(array("gid" => $tournament['gid'], "tid" => $tournament['tid']), $lang->tournament_cancelled);
	update_tournaments_stats();

	$plugins->run_hooks("tournaments_do_cancel_end");

	eval("\$cancelsuccess = \"".$templates->get("tournaments_cancel_success", 1, 0)."\";");
	echo $cancelsuccess;
	exit;
}

// Cancel a tournament
if($mybb->input['action'] == "cancel")
{
	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	$tournament = get_tournament($tid);

	$plugins->run_hooks("tournaments_cancel_start");

	if($mybb->usergroup['canmoderategames'] == 0)
	{
		$message = $lang->error_cancel_nopermission;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	if(!$tournament['tid'])
	{
		$message = $lang->error_invalidtournament;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	if($tournament['status'] == 3)
	{
		$message = $lang->error_alreadyfinished;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("tournaments_cancel_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	$plugins->run_hooks("tournaments_cancel_end");

	eval("\$cancel = \"".$templates->get("tournaments_cancel", 1, 0)."\";");
	echo $cancel;
	exit;
}

// Disqualify a user from a tournament
if($mybb->input['action'] == "disqualify")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canmoderategames'] == 0)
	{
		error($lang->error_disqualify_nopermission);
	}

	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$query = $db->simple_select("arcadetournamentplayers", "*", "pid='{$pid}'");
	$player = $db->fetch_array($query);

	if(!$player['pid'])
	{
		error($lang->error_invalidplayer);
	}

	$tournament = get_tournament($player['tid']);
	$user = get_user($player['uid']);

	$plugins->run_hooks("tournaments_disqualify_start");

	if(!$user['uid'])
	{
		error($lang->error_invaliduser);
	}

	if(!$tournament['tid'])
	{
		error($lang->error_invalidtournament);
	}

	if($player['status'] == 3)
	{
		error($lang->error_alreadydisqualified);
	}

	if($tournament['status'] == 3)
	{
		error($lang->error_tournamentended);
	}

	$arcade->disqualify_user($tournament['tid'], $player['uid']);
	log_arcade_action(array("gid" => $tournament['gid'], "tid" => $tournament['tid'], "uid" => $user['uid'], "username" => $user['username']), $lang->user_disqualified);

	$plugins->run_hooks("tournaments_disqualify_end");

	redirect("tournaments.php?action=view&tid={$player['tid']}", $lang->redirect_userdisqualified);
}

// Delete a tournament
if($mybb->input['action'] == "delete")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->usergroup['canmoderategames'] == 0)
	{
		error_no_permission();
	}

	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	$tournament = get_tournament($tid);

	$plugins->run_hooks("tournaments_delete_start");

	if(!$tournament['tid'])
	{
		error($lang->error_invalidtournament);
	}

	$arcade->delete_tournament($tournament['tid']);
	log_arcade_action(array("gid" => $tournament['gid'], "tid" => $tournament['tid']), $lang->tournament_deleted);

	$plugins->run_hooks("tournaments_delete_end");

	redirect("arcade.php", $lang->redirect_tournamentdeleted);
}

if(!$mybb->input['action'])
{
	header("Location: arcade.php");
}
