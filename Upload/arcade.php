<?php
/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

define("IN_MYBB", 1);
define("IGNORE_CLEAN_VARS", "sid");
define('THIS_SCRIPT', 'arcade.php');

$templatelist = "arcade,arcade_categories,arcade_category_bit,arcade_category_bit_image,arcade_search_catagory,arcade_statistics_bestplayers_bit,arcade_statistics_gamebit,arcade_statistics_scorebit,arcade_tournaments";
$templatelist .= ",arcade_menu,multipage_page_current,multipage_page,multipage_nextpage,multipage_prevpage,multipage_start,multipage_end,multipage,arcade_gamebit_rating,arcade_online_memberbit,arcade_online,arcade_settings";
$templatelist .= ",arcade_champions_bit,arcade_scoreboard_bit,arcade_stats_details,arcade_stats_tournaments,arcade_tournaments_create,arcade_tournaments_user,arcade_tournaments_user_game,arcade_search_catagory_option";
$templatelist .= ",arcade_play_guest,arcade_play_rating,arcade_play_tournament,arcade_gamebit_score,arcade_gamebit_new,arcade_gamebit,arcade_gamebit_favorite,arcade_gamebit_tournaments,arcade_tournaments_waiting,arcade_rating";
$templatelist .= ",arcade_tournaments_cancelled,arcade_scores_delete,arcade_scores_edit,arcade_statistics,arcade_statistics_bestplayers,arcade_stats_bit,arcade_edit_error_nomodal,arcade_online_memberbit_image_home";
$templatelist .= ",arcade_champions,arcade_settings_gamesselect,arcade_settings_scoreselect,arcade_settings_whosonline,arcade_settings_tournamentnotify,arcade_settings_champpostbit,arcade_statistics_bestplayers_avatar";
$templatelist .= ",arcade_scores_play,arcade_scores_rating,arcade_scores_no_scores,arcade_no_display,arcade_scores,arcade_scores_bit,arcade_favorite,arcade_scoreboard,arcade_no_games,arcade_play,arcade_fullscreen,arcade_stats";
$templatelist .= ",arcade_gamebit_champ,arcade_settings_gamesselect_option,arcade_settings_scoreselect_option,arcade_statistics_no_games,arcade_statistics_no_champs,arcade_statistics_no_scores,arcade_favorites,arcade_search";
$templatelist .= ",arcade_gamebit_fullscreen,arcade_scores_bit_ipaddress,arcade_scores_ipaddress,arcade_stats_userinput,arcade_catinput,arcade_play_champion,arcade_gamebit_lastplayed,arcade_online_memberbit_image_game";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_arcade.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;
require_once MYBB_ROOT."inc/class_arcade.php";
$arcade = new Arcade;

// Load global language phrases
$lang->load("arcade");

// Check if the arcade system is globally disabled or not.
if($mybb->settings['enablearcade'] != 1)
{
	error($lang->arcade_disabled);
}

if($mybb->usergroup['canviewarcade'] != 1)
{
	error_no_permission();
}

$plugins->run_hooks("arcade_start");

add_breadcrumb($lang->arcade, "arcade.php");

// Find out the games per page preference.
if($mybb->user['gamesperpage'])
{
	$mybb->settings['gamesperpage'] = $mybb->user['gamesperpage'];
}

// Find out the games sort by and ordering preference.
if($mybb->user['gamessortby'])
{
	$mybb->settings['gamessortby'] = $mybb->user['gamessortby'];
}

if($mybb->user['gamesorder'])
{
	$mybb->settings['gamesorder'] = $mybb->user['gamesorder'];
}

// Find out the scores per page preference.
if($mybb->user['scoresperpage'])
{
	$mybb->settings['scoresperpage'] = $mybb->user['scoresperpage'];
}

if(!$mybb->settings['scoresperpage'] || (int)$mybb->settings['scoresperpage'] < 1)
{
	$mybb->settings['scoresperpage'] = 10;
}

if(!$mybb->settings['gamesperpage'] || (int)$mybb->settings['gamesperpage'] < 1)
{
	$mybb->settings['gamesperpage'] = 10;
}

if(!$mybb->settings['gamessortby'])
{
	$mybb->settings['gamessortby'] = "name";
}

if(!$mybb->settings['gamesorder'])
{
	$mybb->settings['gamesorder'] = "asc";
}

$errors = '';

// Top Menu bar (for members only)
$menu = '';
if($mybb->user['uid'] != 0)
{
	eval("\$menu = \"".$templates->get("arcade_menu")."\";");
}

// Gets only games this user can view (based on category group permission)
$cat_sql_cat = $cat_sql_game = $cat_sql = '';
$unviewable = get_unviewable_categories();
if($unviewable)
{
	$cat_sql_cat .= " AND c.cid NOT IN ($unviewable)";
	$cat_sql_game .= " AND g.cid NOT IN ($unviewable)";
	$cat_sql .= " AND cid NOT IN ($unviewable)";
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

// V3Arcade insert of a score
switch($mybb->get_input('sessdo'))
{
	case 'sessionstart':
		$gamerand = rand(1,20);
		$gametime = microtime();
		$lastid = $mybb->input['gamename'];
		echo "&connStatus=1&initbar=$gamerand&gametime=$gametime&lastid=$lastid&result=OK";
	break;
	case 'permrequest':
		$microone = microtime();
		my_setcookie('v3score', $mybb->input['score']);
		echo "&validate=1&microone=$microone&result=OK";
	break;
	case 'burn':
		$perpage = (int)$mybb->settings['scoresperpage'];

		$score = $mybb->cookies['v3score'];
		$name = $mybb->input['id'];
		$sid = $mybb->cookies['arcadesession'];

		$query = $db->query("
			SELECT s.gid, s.tid, g.sortby
			FROM ".TABLE_PREFIX."arcadesessions s
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
			WHERE s.sid='{$sid}'
		");
		$game = $db->fetch_array($query);

		if($game['tid'])
		{
			$message = $arcade->submit_tournament($score, $name, $sid);

			my_unsetcookie('v3score');
			redirect("tournaments.php?action=view&tid={$game['tid']}", $message);
		}
		else
		{
			$message = $arcade->submit_score($score, $name, $sid);

			$rank = get_rank($mybb->user['uid'], $game['gid'], $game['sortby']);
			$pagenum = ceil($rank/$perpage);

			$page = '';
			if($pagenum > 1)
			{
				$page = "&page={$pagenum}";
			}

			my_unsetcookie('v3score');
			redirect("arcade.php?action=scores&gid={$game['gid']}&newscore=1{$page}", $message);
		}
	break;
}

$mybb->input['action'] = $mybb->get_input('action');

// Playing a game
if($mybb->input['action'] == "play")
{
	if($mybb->usergroup['canplayarcade'] != 1)
	{
		error_no_permission();
	}

	$mybb->binary_fields["arcadesessions"] = array('ipaddress' => true);

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

	if($mybb->settings['enabletournaments'] == 1 && !empty($mybb->input['tid']))
	{
		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

		$query = $db->query("
			SELECT t.tid, t.gid, t.information, t.round, t.tries, p.uid, p.attempts, p.score
			FROM ".TABLE_PREFIX."arcadetournaments t
			LEFT JOIN ".TABLE_PREFIX."arcadetournamentplayers p ON (t.tid=p.tid AND p.round=t.round)
			WHERE t.tid='{$tid}' AND t.status='2' AND p.uid='{$mybb->user['uid']}'
		");
		$tournament = $db->fetch_array($query);

		$game = get_game($tournament['gid']);

		$information = unserialize($tournament['information']);

		// Invalid tournament
		if(!$tournament['tid'])
		{
			error($lang->error_invalidtournament);
		}

		if(!$tournament['uid'])
		{
			error($lang->error_notjoined);
		}

		if($tournament['attempts'] >= $tournament['tries'])
		{
			error($lang->error_maxattemptsreached);
		}
	}

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

	// Check group limits
	if($mybb->usergroup['maxplaysday'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "uid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("arcadesessions", "COUNT(*) AS play_count", "{$user_check} AND dateline >= '".(TIME_NOW - (60*60*24))."'");
		$play_count = $db->fetch_field($query, "play_count");
		if($play_count >= $mybb->usergroup['maxplaysday'])
		{
			$lang->error_max_plays_day = $lang->sprintf($lang->error_max_plays_day, $mybb->usergroup['maxplaysday']);
			error($lang->error_max_plays_day);
		}
	}

	$plugins->run_hooks("arcade_play_start");

	my_unsetcookie('arcadesession');

	$game['name'] = htmlspecialchars_uni($game['name']);
	$game['about'] = nl2br(htmlspecialchars_uni($game['about']));
	$game['controls'] = nl2br(htmlspecialchars_uni($game['controls']));

	add_breadcrumb($game['name'], "arcade.php?action=play&gid={$game['gid']}");

	// Load Tournament info if inputted
	$tournaments = '';
	if($mybb->settings['enabletournaments'] == 1 && !empty($mybb->input['tid']))
	{
		// Create an arcade session (to ensure proper submitting and scoring)
		$sid = md5(uniqid(microtime(), 3));
		$new_session = array(
			"sid" => $db->escape_string($sid),
			"uid" => (int)$mybb->user['uid'],
			"gid" => (int)$game['gid'],
			"tid" => (int)$tid,
			"dateline" => TIME_NOW,
			"gname" => $db->escape_string($game['file']),
			"gtitle" => $db->escape_string($game['name']),
			"ipaddress" => $db->escape_binary($session->packedip)
		);
		$db->insert_query("arcadesessions", $new_session);

		my_setcookie('arcadesession', $sid, 21600);

		$startedon = $information[$tournament['round']]['starttime'];
		$roundstartedon = my_date($mybb->settings['dateformat'], $startedon).", ".my_date($mybb->settings['timeformat'], $startedon);
		$triesleft = ($tournament['tries'] - $tournament['attempts']);
		$hightournamentscore = my_number_format((float)$tournament['score']);

		eval("\$tournaments = \"".$templates->get("arcade_play_tournament")."\";");
	}
	else
	{
		// Create an arcade session (to ensure proper submitting and scoring)
		$sid = md5(uniqid(microtime(), 3));
		$new_session = array(
			"sid" => $db->escape_string($sid),
			"uid" => (int)$mybb->user['uid'],
			"gid" => (int)$game['gid'],
			"dateline" => TIME_NOW,
			"gname" => $db->escape_string($game['file']),
			"gtitle" => $db->escape_string($game['name']),
			"ipaddress" => $db->escape_binary($session->packedip)
		);
		$db->insert_query("arcadesessions", $new_session);

		my_setcookie('arcadesession', $sid, 21600);
	}

	$query = $db->query("
		SELECT c.*, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadechampions c
		LEFT JOIN ".TABLE_PREFIX."users u ON (c.uid=u.uid)
		WHERE c.gid='{$game['gid']}'
	");
	$champ = $db->fetch_array($query);

	if(isset($champ['score']))
	{
		$champ['score'] = my_number_format((float)$champ['score']);
		$lang->current_champion = $lang->sprintf($lang->current_champion, $champ['score']);
	}
	else
	{
		$lang->current_champion = $lang->sprintf($lang->current_champion, $lang->na);
	}

	if(isset($champ['username']))
	{
		$champ['username'] = format_name(htmlspecialchars_uni($champ['username']), $champ['usergroup'], $champ['displaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$champ['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($champ['uid']);
		}

		eval("\$champusername = \"".$templates->get("arcade_play_champion")."\";");
	}
	else
	{
		$champusername = $lang->na;
	}

	// User's best score
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("arcadescores", "score", "gid='{$game['gid']}' AND uid='".(int)$mybb->user['uid']."'");
		$score = $db->fetch_array($query);

		if(isset($score['score']))
		{
			$userbestscore = my_number_format((float)$score['score']);
		}
		else
		{
			$userbestscore = $lang->na;
		}
	}

	// Favorite check
	$add_remove_favorite = '';
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("arcadefavorites", "gid", "gid='".(int)$game['gid']."' AND uid='".(int)$mybb->user['uid']."'", array('limit' => 1));
		if($db->fetch_field($query, 'gid'))
		{
			$add_remove_favorite_type = 'remove';
			$add_remove_favorite_text = $lang->remove_from_favorites;
		}
		else
		{
			$add_remove_favorite_type = 'add';
			$add_remove_favorite_text = $lang->add_to_favorites;
		}

		eval("\$add_remove_favorite = \"".$templates->get("arcade_favorite")."\";");
	}

	// Work out the rating for this game.
	$rating = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		if($game['numratings'] <= 0)
		{
			$game['rating_width'] = 0;
			$game['averagerating'] = 0;
			$game['numratings'] = 0;
		}
		else
		{
			$game['averagerating'] = (float)round($game['totalratings']/$game['numratings'], 2);
			$game['rating_width'] = (int)round($game['averagerating'])*20;
			$game['numratings'] = (int)$game['numratings'];
		}

		$rated = '';
		if($game['numratings'])
		{
			// At least >someone< has rated this game, was it me?
			// Check if we have already voted on this game - it won't show hover effect then.
			$query = $db->simple_select("arcaderatings", "uid", "gid='{$game['gid']}' AND uid='".(int)$mybb->user['uid']."'");
			$rated = $db->fetch_field($query, 'uid');
		}

		$not_rated = '';
		if(!$rated)
		{
			$not_rated = ' star_rating_notrated';
		}

		$ratingvotesav = $lang->sprintf($lang->rating_average, $game['numratings'], $game['averagerating']);
		eval("\$gamerating = \"".$templates->get("arcade_play_rating")."\";");
	}

	$lang->object_of_game = $lang->sprintf($lang->object_of_game, $game['name']);

	$guestmessage = '';
	if($mybb->user['uid'] == 0)
	{
		eval("\$guestmessage = \"".$templates->get("arcade_play_guest")."\";");
	}

	// Increment play views, last play time and last play uid.
	$update_game = array(
		"plays" => (int)$game['plays'] + 1,
		"lastplayed" => TIME_NOW,
		"lastplayeduid" => (int)$mybb->user['uid']
	);
	$db->update_query("arcadegames", $update_game, "gid='{$game['gid']}'");

	$plugins->run_hooks("arcade_play_end");

	eval("\$play = \"".$templates->get("arcade_play")."\";");
	output_page($play);
}

// Playing a game full screen
if($mybb->input['action'] == "fullscreen")
{
	if($mybb->usergroup['canplayarcade'] != 1)
	{
		error_no_permission();
	}

	$mybb->binary_fields["arcadesessions"] = array('ipaddress' => true);

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

	if($mybb->settings['enabletournaments'] == 1 && !empty($mybb->input['tid']))
	{
		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

		$query = $db->query("
			SELECT t.tid, t.gid, t.tries, p.uid, p.attempts
			FROM ".TABLE_PREFIX."arcadetournaments t
			LEFT JOIN ".TABLE_PREFIX."arcadetournamentplayers p ON (t.tid=p.tid AND p.round=t.round)
			WHERE t.tid='{$tid}' AND t.status='2' AND p.uid='{$mybb->user['uid']}'
		");
		$tournament = $db->fetch_array($query);

		$game = get_game($tournament['gid']);

		// Invalid tournament
		if(!$tournament['tid'])
		{
			error($lang->error_invalidtournament);
		}

		if(!$tournament['uid'])
		{
			error($lang->error_notjoined);
		}

		if($tournament['attempts'] >= $tournament['tries'])
		{
			error($lang->error_maxattemptsreached);
		}
	}

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

	// Check group limits
	if($mybb->usergroup['maxplaysday'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "uid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("arcadesessions", "COUNT(*) AS play_count", "{$user_check} AND dateline >= '".(TIME_NOW - (60*60*24))."'");
		$play_count = $db->fetch_field($query, "play_count");
		if($play_count >= $mybb->usergroup['maxplaysday'])
		{
			$lang->error_max_plays_day = $lang->sprintf($lang->error_max_plays_day, $mybb->usergroup['maxplaysday']);
			error($lang->error_max_plays_day);
		}
	}

	$plugins->run_hooks("arcade_fullscreen_start");

	my_unsetcookie('arcadesession');

	// Load Tournament info if inputted
	if($mybb->settings['enabletournaments'] == 1 && !empty($mybb->input['tid']))
	{
		// Create an arcade session (to ensure proper submitting and scoring)
		$sid = md5(uniqid(microtime(), 3));
		$new_session = array(
			"sid" => $db->escape_string($sid),
			"uid" => (int)$mybb->user['uid'],
			"gid" => (int)$game['gid'],
			"tid" => (int)$tid,
			"dateline" => TIME_NOW,
			"gname" => $db->escape_string($game['file']),
			"gtitle" => $db->escape_string($game['name']),
			"ipaddress" => $db->escape_binary($session->packedip)
		);
		$db->insert_query("arcadesessions", $new_session);

		my_setcookie('arcadesession', $sid, 21600);
	}
	else
	{
		// Create an arcade session (to ensure proper submitting and scoring)
		$sid = md5(uniqid(microtime(), 3));
		$new_session = array(
			"sid" => $db->escape_string($sid),
			"uid" => (int)$mybb->user['uid'],
			"gid" => (int)$game['gid'],
			"dateline" => TIME_NOW,
			"gname" => $db->escape_string($game['file']),
			"gtitle" => $db->escape_string($game['name']),
			"ipaddress" => $db->escape_binary($session->packedip)
		);
		$db->insert_query("arcadesessions", $new_session);

		my_setcookie('arcadesession', $sid, 21600);
	}

	// Increment play views, last play time and last play uid.
	$update_game = array(
		"plays" => (int)$game['plays'] + 1,
		"lastplayed" => TIME_NOW,
		"lastplayeduid" => (int)$mybb->user['uid']
	);
	$db->update_query("arcadegames", $update_game, "gid='{$game['gid']}'");

	$plugins->run_hooks("arcade_fullscreen_end");

	eval("\$fullscreen = \"".$templates->get("arcade_fullscreen")."\";");
	output_page($fullscreen);
}

// High scores for a game
if($mybb->input['action'] == "scores")
{
	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

	// Invalid game
	if($game['active'] != 1 || !$game['gid'])
	{
		error($lang->error_invalidgame);
	}

	$collapsed['game_info_e'] = '';
	$collapsedimg['game_info'] = '';

	$category = explode(',', $unviewable);
	if(in_array($game['cid'], $category))
	{
		error($lang->error_nogamepermission);
	}

	$plugins->run_hooks("arcade_scores_start");

	$game['name'] = htmlspecialchars_uni($game['name']);
	$game['description'] = htmlspecialchars_uni($game['description']);

	$lang->play_game = $lang->sprintf($lang->play_game, $game['name']);
	$lang->play_game_fullscreen = $lang->sprintf($lang->play_game_fullscreen, $game['name']);
	$lang->highest_scores_of = $lang->sprintf($lang->highest_scores_of, $game['name']);

	add_breadcrumb($lang->highest_scores_of, "arcade.php?action=scores&gid={$game['gid']}");

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $mybb->settings['scoresperpage'];
	}

	$query = $db->simple_select("arcadescores", "COUNT(sid) AS count", "gid ='{$gid}'");
	$result = $db->fetch_field($query, "count");

	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$pages = $result / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$multipage = multipage($result, $perpage, $page, "arcade.php?action=scores&gid={$gid}");

	// Play game link
	$playgame = '';
	if($mybb->usergroup['canplayarcade'] == 1)
	{
		eval("\$playgame = \"".$templates->get("arcade_scores_play")."\";");
	}

	// Favorite check
	$add_remove_favorite = '';
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("arcadefavorites", "gid", "gid='".(int)$gid."' AND uid='".(int)$mybb->user['uid']."'", array('limit' => 1));
		if($db->fetch_field($query, 'gid'))
		{
			$add_remove_favorite_type = 'remove';
			$add_remove_favorite_text = $lang->remove_from_favorites;
		}
		else
		{
			$add_remove_favorite_type = 'add';
			$add_remove_favorite_text = $lang->add_to_favorites;
		}

		eval("\$add_remove_favorite = \"".$templates->get("arcade_favorite")."\";");
	}

	// Work out the rating for this game.
	$rategame = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		if($game['numratings'] <= 0)
		{
			$game['rating_width'] = 0;
			$game['averagerating'] = 0;
			$game['numratings'] = 0;
		}
		else
		{
			$game['averagerating'] = (float)round($game['totalratings']/$game['numratings'], 2);
			$game['rating_width'] = (int)round($game['averagerating'])*20;
			$game['numratings'] = (int)$game['numratings'];
		}

		$rated = '';
		if($game['numratings'])
		{
			// At least >someone< has rated this game, was it me?
			// Check if we have already voted on this game - it won't show hover effect then.
			$query = $db->simple_select("arcaderatings", "uid", "gid='{$gid}' AND uid='".(int)$mybb->user['uid']."'");
			$rated = $db->fetch_field($query, 'uid');
		}

		$not_rated = '';
		if(!$rated)
		{
			$not_rated = ' star_rating_notrated';
		}

		$ratingvotesav = $lang->sprintf($lang->rating_average, $game['numratings'], $game['averagerating']);
		eval("\$rategame = \"".$templates->get("arcade_scores_rating")."\";");
	}

	// Fetch the scores which will be displayed on this page
	$score_bit = '';
	$counter = 0;
	$query = $db->query("
		SELECT s.*, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.gid='{$gid}'
		ORDER BY score {$game['sortby']}, dateline ASC
		LIMIT {$start}, {$perpage}
	");
	while($score = $db->fetch_array($query))
	{
		$score['score'] = my_number_format((float)$score['score']);
		$score['username'] = format_name(htmlspecialchars_uni($score['username']), $score['usergroup'], $score['displaygroup']);
		$dateline = my_date('relative', $score['dateline']);

		$plus = ($perpage * $page) - $perpage;
		$counter++;
		$rank = $counter + $plus;

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$score['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($score['uid']);
		}

		// Does the current user have permission to delete this score? Show delete link
		$delete_link = '';
		if($mybb->usergroup['canmoderategames'] == 1)
		{
			eval("\$delete_link = \"".$templates->get("arcade_scores_delete")."\";");
		}

		// Does the current user have permission to edit this score comment? Show edit link
		$time = TIME_NOW;
		$edit_link = '';
		if($mybb->usergroup['canmoderategames'] == 1 || ($score['uid'] == $mybb->user['uid'] && ($mybb->settings['arcade_editcomment'] == 0 || $score['dateline'] > ($time-($mybb->settings['arcade_editcomment']*60)))))
		{
			eval("\$edit_link = \"".$templates->get("arcade_scores_edit")."\";");
		}

		// Parse smilies and bad words in the score comment
		$comment_parser = array(
			"allow_html" => 0,
			"allow_mycode" => 0,
			"allow_smilies" => 1,
			"allow_imgcode" => 0,
			"allow_videocode" => 0,
			"filter_badwords" => 1
		);

		$score['comment'] = $parser->parse_message($score['comment'], $comment_parser);

		if(!$score['timeplayed'])
		{
			$timeplayed = $lang->na;
		}
		else
		{
			$timeplayed = nice_time($score['timeplayed'], array('short' => 1));
		}

		if($mybb->input['newscore'] == 1 && $mybb->user['uid'] == $score['uid'])
		{
			$alt_bg = "trow_shaded";
		}
		else
		{
			$alt_bg = alt_trow();
		}

		// Display IP address of scores if user is a mod/admin
		$ipaddressbit = '';
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			$score['ipaddress'] = my_inet_ntop($db->unescape_binary($score['ipaddress']));
			eval("\$ipaddressbit = \"".$templates->get("arcade_scores_bit_ipaddress")."\";");
		}

		eval("\$score_bit .= \"".$templates->get("arcade_scores_bit")."\";");
	}

	if(!$score_bit)
	{
		eval("\$score_bit = \"".$templates->get("arcade_scores_no_scores")."\";");
	}

	// Display IP address of scores if user is a mod/admin
	$ipaddresscol = '';
	if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
	{
		eval("\$ipaddresscol = \"".$templates->get("arcade_scores_ipaddress")."\";");
		$colspan = 7;
	}
	else
	{
		$colspan = 6;
	}

	$plugins->run_hooks("arcade_scores_end");

	eval("\$scores = \"".$templates->get("arcade_scores")."\";");
	output_page($scores);
}

// Rating a game
if($mybb->input['action'] == "rate")
{
	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	if($mybb->settings['arcade_ratings'] == 0)
	{
		error($lang->error_ratingsdisabled);
	}

	if($mybb->usergroup['canrategames'] == 0)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

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

	$plugins->run_hooks("arcade_rate_start");

	$mybb->input['rating'] = $mybb->get_input('rating', MyBB::INPUT_INT);
	if($mybb->input['rating'] < 1 || $mybb->input['rating'] > 5)
	{
		error($lang->error_invalidrating);
	}

	// Check to see if user has already rated this game
	if($mybb->user['uid'] != 0)
	{
		$whereclause = "uid='{$mybb->user['uid']}'";
	}
	else
	{
		$whereclause = "ipaddress=".$db->escape_binary($session->packedip);
	}
	$query = $db->simple_select("arcaderatings", "*", "{$whereclause} AND gid='{$gid}'");
	$ratecheck = $db->fetch_array($query);

	if($ratecheck['rid'] || isset($mybb->cookies['mybbrategame'][$gid]))
	{
		error($lang->error_alreadyratedgame);
	}
	else
	{
		$mybb->binary_fields["arcaderatings"] = array('ipaddress' => true);
		$plugins->run_hooks("arcade_rate_process");

		$db->write_query("
			UPDATE ".TABLE_PREFIX."arcadegames
			SET numratings=numratings+1, totalratings=totalratings+'{$mybb->input['rating']}'
			WHERE gid='{$gid}'
		");
		if($mybb->user['uid'] != 0)
		{
			$insertarray = array(
				'gid' => $gid,
				'uid' => $mybb->user['uid'],
				'rating' => $mybb->input['rating'],
				'ipaddress' => $db->escape_binary($session->packedip)
			);
			$db->insert_query("arcaderatings", $insertarray);
		}
		else
		{
			$insertarray = array(
				'gid' => $gid,
				'rating' => $mybb->input['rating'],
				'ipaddress' => $db->escape_binary($session->packedip)
			);
			$db->insert_query("arcaderatings", $insertarray);
			$time = TIME_NOW;
			my_setcookie("mybbrategame[{$gid}]", $mybb->input['rating']);
		}
	}

	if(!empty($mybb->input['ajax']))
	{
		$json = array("success" => $lang->rating_added);
		$query = $db->simple_select("arcadegames", "totalratings, numratings", "gid='$gid'", array('limit' => 1));
		$fetch = $db->fetch_array($query);
		$width = 0;
		if($fetch['numratings'] >= 0)
		{
			$averagerating = (float)round($fetch['totalratings']/$fetch['numratings'], 2);
			$width = (int)round($averagerating)*20;
			$fetch['numratings'] = (int)$fetch['numratings'];
			$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $fetch['numratings'], $averagerating);
			$json = $json + array("average" => $ratingvotesav);
		}
		$json = $json + array("width" => $width);

		@header("Content-type: application/json; charset={$lang->settings['charset']}");
		echo json_encode($json);
		exit;
	}

	$plugins->run_hooks("arcade_rate_end");

	if($server_http_referer)
	{
		$url = $server_http_referer;
	}
	else
	{
		$url = "arcade.php?action=scores&gid={$gid}";
	}
	redirect($url, $lang->redirect_rating_added);
}

// Delete a specific score
if($mybb->input['action'] == "delete")
{
	// Only arcade moderators can delete.
	if($mybb->usergroup['canmoderategames'] != 1)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("arcade_delete_start");

	$sid = $mybb->get_input('sid', MyBB::INPUT_INT);
	$query = $db->simple_select("arcadescores", "sid, gid, uid", "sid='{$sid}'");
	$score = $db->fetch_array($query);

	if(!$score['sid'])
	{
		error($lang->error_invalidscore);
	}

	$user = get_user($score['uid']);

	if(!$user['uid'])
	{
		error($lang->error_invaliduser);
	}

	$arcade->delete_score($score['sid'], $score['gid']);
	log_arcade_action(array("gid" => $score['gid'], "uid" => $user['uid'], "username" => $user['username']), $lang->deleted_score);

	$plugins->run_hooks("arcade_delete_end");

	redirect("arcade.php?action=scores&gid={$score['gid']}", $lang->redirect_score_deleted);
}

// Saving the new comment
if($mybb->input['action'] == "do_edit" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$sid = $mybb->get_input('sid', MyBB::INPUT_INT);
	$query = $db->simple_select("arcadescores", "sid, uid, comment", "sid='{$sid}'");
	$score = $db->fetch_array($query);

	$plugins->run_hooks("arcade_do_edit_start");

	if(!$score['sid'])
	{
		$message = $lang->error_invalidscore;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("arcade_edit_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("arcade_edit_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	if($mybb->usergroup['canmoderategames'] == 0 && $mybb->usergroup['cancp'] == 0 && $mybb->user['uid'] != $score['uid'])
	{
		$message = $lang->edit_nopermission;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("arcade_edit_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("arcade_edit_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	// The length of the comment is too long
	if(my_strlen($mybb->input['comment']) > $mybb->settings['arcade_maxcommentlength'])
	{
		$message = $lang->sprintf($lang->edit_toolong, $mybb->settings['arcade_maxcommentlength']);
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("arcade_edit_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("arcade_edit_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	// Build array of score data.
	$updatedcomment = array(
		"comment" => $db->escape_string($mybb->input['comment'])
	);

	$db->update_query("arcadescores", $updatedcomment, "sid='{$score['sid']}'");

	$plugins->run_hooks("arcade_do_edit_end");

	eval("\$edited = \"".$templates->get("arcade_edited", 1, 0)."\";");
	echo $edited;
	exit;
}

// Edit a score comment
if($mybb->input['action'] == "edit")
{
	$time = TIME_NOW;

	$sid = $mybb->get_input('sid', MyBB::INPUT_INT);
	$query = $db->simple_select("arcadescores", "*", "sid='{$sid}'");
	$score = $db->fetch_array($query);

	$plugins->run_hooks("arcade_edit_start");
	
	if(!$score['sid'])
	{
		$message = $lang->error_invalidscore;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("arcade_edit_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("arcade_edit_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	if($mybb->usergroup['canmoderategames'] == 0 && $mybb->usergroup['cancp'] == 0 && $mybb->user['uid'] != $score['uid'])
	{
		$message = $lang->edit_nopermission;
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("arcade_edit_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("arcade_edit_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	if($mybb->usergroup['canmoderategames'] == 0 && $mybb->usergroup['cancp'] == 0 && ($mybb->settings['arcade_editcomment'] != 0 && $score['dateline'] < ($time-($mybb->settings['arcade_editcomment']*60))))
	{
		$message = $lang->sprintf($lang->error_timelimit, $mybb->settings['arcade_editcomment']);
		if($mybb->input['nomodal'])
		{
			eval("\$error = \"".$templates->get("arcade_edit_error_nomodal", 1, 0)."\";");
		}
		else
		{
			eval("\$error = \"".$templates->get("arcade_edit_error", 1, 0)."\";");
		}
		echo $error;
		exit;
	}

	$score['comment'] = htmlspecialchars_uni($score['comment']);

	$plugins->run_hooks("arcade_edit_end");

	eval("\$edit = \"".$templates->get("arcade_edit", 1, 0)."\";");
	echo $edit;
	exit;
}

// Adding to favorites
if($mybb->input['action'] == "addfavorite")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	$plugins->run_hooks("arcade_addfavorite_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

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

	$query = $db->simple_select("arcadefavorites", "fid", "gid='".$game['gid']."' AND uid='".(int)$mybb->user['uid']."'", array('limit' => 1));
	$favorite = $db->fetch_field($query, "fid");

	if(empty($favorite))
	{
		$insert_array = array(
			'uid' => (int)$mybb->user['uid'],
			'gid' => $game['gid'],
		);
		$db->insert_query("arcadefavorites", $insert_array);
	}
	if($server_http_referer)
	{
		$url = $server_http_referer;
	}
	else
	{
		$url = "arcade.php?action=scores&gid={$game['gid']}";
	}

	$plugins->run_hooks("arcade_addfavorite_end");

	redirect($url, $lang->redirect_favoriteadded);
}

// Removing from favorites
if($mybb->input['action'] == "removefavorite")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	$plugins->run_hooks("arcade_removefavorite_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
	$game = get_game($gid);

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

	$db->delete_query("arcadefavorites", "gid='".$game['gid']."' AND uid='".(int)$mybb->user['uid']."'");
	if($server_http_referer)
	{
		$url = $server_http_referer;
	}
	else
	{
		$url = "arcade.php?action=favorites";
	}

	$plugins->run_hooks("arcade_removefavorite_end");

	redirect($url, $lang->redirect_favoriteremoved);
}

// Favorites page
if($mybb->input['action'] == "favorites")
{
	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("arcade_favorites_start");

	add_breadcrumb($lang->favorite_games, "arcade.php?action=favorites");

	// Delete games if user cannot view them (based on category permission)
	if($unviewable)
	{
		$query = $db->simple_select("arcadegames", "gid", "cid IN ($unviewable)");
		while($delete_fav = $db->fetch_array($query))
		{
			$db->delete_query("arcadefavorites", "gid='{$delete_fav['gid']}' AND uid='".(int)$mybb->user['uid']."'");
		}
	}

	$sortby_selected = array('date' => '', 'plays' => '', 'lastplayed' => '', 'rating' => '', 'name' => '');
	$order_selected = array('asc' => '', 'desc' => '');

	// Pick the sort order.
	if(!isset($mybb->input['order']) && !empty($mybb->settings['gamesorder']))
	{
		$mybb->input['order'] = $mybb->settings['gamesorder'];
	}
	else
	{
		$mybb->input['order'] = $mybb->get_input('order');
	}

	$mybb->input['order'] = htmlspecialchars_uni($mybb->input['order']);

	$order_select = '';
	switch($mybb->input['order'])
	{
		case "desc":
			$order = "desc";
			$order_selected['desc'] = "selected=\"selected\"";
			break;
		default:
			$order = "asc";
			$order_selected['asc'] = "selected=\"selected\"";
			break;
	}

	// Sort by which field?
	if(!isset($mybb->input['sortby']) && !empty($mybb->settings['gamessortby']))
	{
		$mybb->input['sortby'] = $mybb->settings['gamessortby'];
	}
	else
	{
		$mybb->input['sortby'] = $mybb->get_input('sortby');
	}

	$mybb->input['sortby'] = htmlspecialchars_uni($mybb->input['sortby']);

	$sortby_select = '';
	switch($mybb->input['sortby'])
	{
		case "date":
			$sortby = "g.dateline";
			$sortby_selected['date'] = 'selected="selected"';
			break;
		case "plays":
			$sortby = "g.plays";
			$sortby_selected['plays'] = 'selected="selected"';
			break;
		case "lastplayed":
			$sortby = "g.lastplayed";
			$sortby_selected['lastplayed'] = 'selected="selected"';
			break;
		case "rating":
			$sortby = "g.totalratings";
			$sortby_selected['rating'] = 'selected="selected"';
			break;
		default:
			$sortby = "g.name";
			$sortby_selected['last_updated'] = 'selected="selected"';
			break;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['gamesperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	$query = $db->simple_select("arcadefavorites", "COUNT(gid) AS page_count", "uid='".(int)$mybb->user['uid']."'");
	$page_count = $db->fetch_field($query, "page_count");

	$pages = ceil($page_count/$perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	if($mybb->input['order'] || $mybb->input['sortby'])
	{
		$page_url = "arcade.php?action=favorites&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}";
	}
	else
	{
		$page_url = "arcade.php?action=favorites";
	}

	$multipage = multipage($page_count, $perpage, $page, $page_url);

	switch($db->type)
	{
		case "pgsql":
			$ratingadd = "CASE WHEN g.numratings=0 THEN 0 ELSE g.totalratings/g.numratings::numeric END AS averagerating, ";
			break;
		default:
			$ratingadd = "(g.totalratings/g.numratings) AS averagerating, ";
	}

	// Fetch the games which will be displayed on this page
	$game_bit = '';
	$query = $db->query("
		SELECT f.*, g.*, {$ratingadd}u.username, u.usergroup, u.displaygroup, s.score, f.fid AS favorite, c.score AS champscore, c.uid AS champuid, c.username AS champusername, cu.usergroup AS champusergroup, cu.displaygroup AS champdisplaygroup, r.uid AS rated
		FROM ".TABLE_PREFIX."arcadefavorites f
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=f.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (f.gid=s.gid AND s.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcadechampions c ON (f.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcaderatings r ON (g.gid=r.gid AND r.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."users u ON (g.lastplayeduid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users cu ON (c.uid=cu.uid)
		WHERE g.active='1' AND f.uid='".$mybb->user['uid']."'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($game = $db->fetch_array($query))
	{
		$game_bit .= build_gamebit($game);
	}

	if(!$game_bit)
	{
		eval("\$game_bit = \"".$templates->get("arcade_no_games")."\";");
	}

	$arcaderating = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		eval("\$arcaderating = \"".$templates->get("arcade_rating")."\";");
	}

	$plugins->run_hooks("arcade_favorites_end");

	eval("\$favorites = \"".$templates->get("arcade_favorites")."\";");
	output_page($favorites);
}

// Update Settings
if($mybb->input['action'] == "do_settings" && $mybb->request_method == "post")
{
	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("arcade_do_settings_start");

	$update_array = array(
		"gamesperpage" => $mybb->get_input('gamesperpage', MyBB::INPUT_INT),
		"scoresperpage" => $mybb->get_input('scoresperpage', MyBB::INPUT_INT),
		"gamessortby" => $db->escape_string($mybb->get_input('gamessortby')),
		"gamesorder" => $db->escape_string($mybb->get_input('gamesorder')),
		"whosonlinearcade" => $mybb->get_input('whosonlinearcade', MyBB::INPUT_INT),
		"champdisplaypostbit" => $mybb->get_input('champdisplaypostbit', MyBB::INPUT_INT),
		"tournamentnotify" => $mybb->get_input('tournamentnotify', MyBB::INPUT_INT),
		"champnotify" => $mybb->get_input('champnotify', MyBB::INPUT_INT)
	);
	$db->update_query("users", $update_array, "uid='".(int)$mybb->user['uid']."'");

	$plugins->run_hooks("arcade_do_settings_end");

	redirect("arcade.php", $lang->redirect_settingsupdated);
}

// Arcade Settings
if($mybb->input['action'] == "settings")
{
	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	add_breadcrumb($lang->arcade_settings, "arcade.php?action=settings");

	$plugins->run_hooks("arcade_settings_start");

	if($errors != '')
	{
		$user = $mybb->input;
	}
	else
	{
		$user = $mybb->user;
	}

	$pm_tournamentnotify_selected = $email_tournamentnotify_selected = $no_tournamentnotify_selected = '';
	if($user['tournamentnotify'] == 1)
	{
		$pm_tournamentnotify_selected = "selected=\"selected\"";
	}
	elseif($user['tournamentnotify'] == 2)
	{
		$email_tournamentnotify_selected = "selected=\"selected\"";
	}
	else
	{
		$no_tournamentnotify_selected = "selected=\"selected\"";
	}

	$pm_champnotify_selected = $email_champnotify_selected = $no_champnotify_selected = '';
	if($user['champnotify'] == 1)
	{
		$pm_champnotify_selected = "selected=\"selected\"";
	}
	elseif($user['champnotify'] == 2)
	{
		$email_champnotify_selected = "selected=\"selected\"";
	}
	else
	{
		$no_champnotify_selected = "selected=\"selected\"";
	}

	$whosonlinearcadecheck = '';
	if($user['whosonlinearcade'] == 1)
	{
		$whosonlinearcadecheck = "checked=\"checked\"";
	}

	$champdisplaypostbitcheck ='';
	if($user['champdisplaypostbit'] == 1)
	{
		$champdisplaypostbitcheck = "checked=\"checked\"";
	}

	$sortbysel = array('date' => '', 'plays' => '', 'lastplayed' => '', 'rating' => '', 'name' => '');
	$ordersel = array('asc' => '', 'desc' => '');

	$sortbysel[$user['gamessortby']] = 'selected="selected"';
	$ordersel[$user['gamesorder']] = 'selected="selected"';

	if($mybb->settings['gamesperpageoptions'])
	{
		$explodedgames = explode(",", $mybb->settings['gamesperpageoptions']);
		$gameperpageoptions = $gamesoptions = '';
		if(is_array($explodedgames))
		{
			foreach($explodedgames as $key => $val)
			{
				$val = trim($val);
				$selected = '';
				if($user['gamesperpage'] == $val)
				{
					$selected = " selected=\"selected\"";
				}

				$games_option = $lang->sprintf($lang->games_option, $val);
				eval("\$gamesoptions .= \"".$templates->get("arcade_settings_gamesselect_option")."\";");
			}
		}
		eval("\$gameperpageoptions = \"".$templates->get("arcade_settings_gamesselect")."\";");
	}

	if($mybb->settings['scoresperpageoptions'])
	{
		$explodedscores = explode(",", $mybb->settings['scoresperpageoptions']);
		$scoreperpageoptions = $scoreoptions = '';
		if(is_array($explodedscores))
		{
			foreach($explodedscores as $key => $val)
			{
				$val = trim($val);
				$selected = '';
				if($user['scoresperpage'] == $val)
				{
					$selected = " selected=\"selected\"";
				}

				$score_option = $lang->sprintf($lang->score_option, $val);
				eval("\$scoreoptions .= \"".$templates->get("arcade_settings_scoreselect_option")."\";");
			}
		}
		eval("\$scoreperpageoptions = \"".$templates->get("arcade_settings_scoreselect")."\";");
	}

	$whosonlinedisplay = '';
	if($mybb->settings['arcade_whosonline'] != 0)
	{
		eval("\$whosonlinedisplay = \"".$templates->get("arcade_settings_whosonline")."\";");
	}

	$champdisplaypostbit = '';
	if($mybb->settings['arcade_postbit'] != 0)
	{
		eval("\$champdisplaypostbit = \"".$templates->get("arcade_settings_champpostbit")."\";");
	}

	$tournamentnotifydisplay = '';
	if($mybb->usergroup['canjointournaments'] == 1)
	{
		eval("\$tournamentnotifydisplay = \"".$templates->get("arcade_settings_tournamentnotify")."\";");
	}

	$plugins->run_hooks("arcade_settings_end");

	eval("\$arcadesettings = \"".$templates->get("arcade_settings")."\";");
	output_page($arcadesettings);
}

// Stats page
if($mybb->input['action'] == "stats")
{
	$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
	if(!$uid)
	{
		$uid = (int)$mybb->user['uid'];
	}

	$user = get_user($uid);
	if(!$user['uid'])
	{
		error($lang->error_invaliduser);
	}

	if($mybb->usergroup['canviewgamestats'] != 1 && $mybb->user['uid'] != $uid)
	{
		error_no_permission();
	}

	$plugins->run_hooks("arcade_stats_start");

	add_breadcrumb($lang->arcade_stats, "arcade.php?action=stats");

	$user['username'] = htmlspecialchars_uni($user['username']);

	$lang->arcade_stats_for = $lang->sprintf($lang->arcade_stats_for, $user['username']);
	$lang->player_details = $lang->sprintf($lang->player_details, $user['username']);

	$userinput = '';
	if($uid)
	{
		eval("\$userinput = \"".$templates->get("arcade_stats_userinput")."\";");
	}

	$sortby_selected = array('date' => '', 'name' => '');
	$order_selected = array('asc' => '', 'desc' => '');

	$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));

	// Check the sorting order for games
	$order_select = '';
	switch($mybb->input['order'])
	{
		case "desc":
			$order = "desc";
			$order_selected['desc'] = "selected=\"selected\"";
			break;
		default:
			$order = "asc";
			$order_selected['asc'] = "selected=\"selected\"";
			break;
	}

	// Sort by which field?
	$mybb->input['sortby'] = htmlspecialchars_uni($mybb->get_input('sortby'));

	$sortby_select = '';
	switch($mybb->input['sortby'])
	{
		case "date":
			$sortby = "s.dateline";
			$sortby_selected['date'] = 'selected="selected"';
			break;
		default:
			$sortby = "g.name";
			$sortby_selected['name'] = 'selected="selected"';
			break;
	}

	// Figure out if we need to display multiple pages.
	if(!$mybb->settings['statsperpage'] || (int)$mybb->settings['statsperpage'] < 1)
	{
		$mybb->settings['statsperpage'] = 10;
	}

	$perpage = $mybb->settings['statsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	$query = $db->simple_select("arcadegames", "COUNT(gid) AS page_count", "active='1'{$cat_sql}");
	$page_count = $db->fetch_field($query, "page_count");

	$pages = ceil($page_count/$perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	if($mybb->input['order'] || $mybb->input['sortby'])
	{
		if($uid)
		{
			$page_url = "arcade.php?action=stats&uid={$uid}&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}";
		}
		else
		{
			$page_url = "arcade.php?action=stats&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}";
		}
	}
	elseif($uid)
	{
		$page_url = "arcade.php?action=stats&uid={$uid}";
	}
	else
	{
		$page_url = "arcade.php?action=stats";
	}

	$multipage = multipage($page_count, $perpage, $page, $page_url);

	// Fetch the games and scores which will be displayed on this page
	$stats_bit = '';
	$query = $db->query("
		SELECT g.*, s.uid, s.score, s.dateline, COUNT(c.gid) AS totalscores, ch.uid AS firstplace
		FROM ".TABLE_PREFIX."arcadegames g
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (g.gid=s.gid AND s.uid='{$uid}')
		LEFT JOIN ".TABLE_PREFIX."arcadescores c ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadechampions ch ON (g.gid=ch.gid AND ch.uid='{$uid}')
		WHERE g.active='1'{$cat_sql_game}
		GROUP BY g.gid, s.score, s.dateline
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($score = $db->fetch_array($query))
	{
		$score['name'] = htmlspecialchars_uni($score['name']);

		if(!$score['uid'])
		{
			$userrank = $lang->na;
		}
		elseif($score['firstplace'])
		{
			$userrank = 1;
		}
		else
		{
			$userrank = get_rank($uid, $score['gid'], $score['sortby']);
		}

		if($score['score'])
		{
			$score['score'] = my_number_format((float)$score['score']);
		}
		else
		{
			$score['score'] = $lang->na;
		}

		if($score['dateline'])
		{
			$dateline = my_date('relative', $score['dateline']);
		}
		else
		{
			$dateline = $lang->na;
		}

		if($score['dateline'])
		{
			$total = TIME_NOW - $score['dateline'];
			$scoreage = nice_time($total);
		}
		else
		{
			$scoreage = $lang->na;
		}

		$alt_bg = alt_trow();
		eval("\$stats_bit .= \"".$templates->get("arcade_stats_bit")."\";");
	}

	if(!$stats_bit)
	{
		$message = $lang->no_stats;
		$colspan = 5;
		eval("\$stats_bit = \"".$templates->get("arcade_no_display")."\";");
	}

	$statsdetails = user_game_rank($user['uid'], $cat_sql_game);

	if($mybb->settings['enabletournaments'] == 1 && $mybb->usergroup['canviewtournaments'] == 1)
	{
		$query2 = $db->query("
			SELECT p.tid, p.uid, t.champion, t.uid AS creater
			FROM ".TABLE_PREFIX."arcadetournamentplayers p
			LEFT JOIN ".TABLE_PREFIX."arcadetournaments t ON (p.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (t.gid=g.gid)
			WHERE p.uid='{$user['uid']}' AND p.round='1' AND g.active='1'{$cat_sql_game}
		");
		$tournamentscreated = 0;
		$tournamentswon = 0;
		$tournamentsentered = $db->num_rows($query2);
		while($tournaments = $db->fetch_array($query2))
		{
			if($tournaments['creater'] == $user['uid'])
			{
				$tournamentscreated++;
			}

			if($tournaments['champion'] == $user['uid'])
			{
				$tournamentswon++;
			}
		}

		eval("\$tournamentstats = \"".$templates->get("arcade_stats_tournaments")."\";");
	}

	eval("\$statistics = \"".$templates->get("arcade_stats")."\";");
	output_page($statistics);
}

// Latest champions
if($mybb->input['action'] == "champions")
{
	add_breadcrumb($lang->arcade_champions, "arcade.php?action=champions");

	$plugins->run_hooks("arcade_champions_start");

	$sortby_selected = array('name' => '', 'user' => '', 'date' => '');
	$order_selected = array('asc' => '', 'desc' => '');
	$perpage_selected = array(5 => '', 10 => '', 15 => '', 20 => '', 25 => '', 30 => '', 40 => '', 50 => '');

	$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));

	// Check the sorting order for games
	$order_select = '';
	switch($mybb->input['order'])
	{
		case "asc":
			$order = "asc";
			$order_selected['asc'] = "selected=\"selected\"";
			break;
		default:
			$order = "desc";
			$order_selected['desc'] = "selected=\"selected\"";
			break;
	}

	// Sort by which field?
	$mybb->input['sortby'] = htmlspecialchars_uni($mybb->get_input('sortby'));

	$sortby_select = '';
	switch($mybb->input['sortby'])
	{
		case "name":
			$sortby = "g.name";
			$sortby_selected['name'] = 'selected="selected"';
			break;
		case "user":
			$sortby = "c.username";
			$sortby_selected['user'] = 'selected="selected"';
			break;
		default:
			$sortby = "c.dateline";
			$sortby_selected['date'] = 'selected="selected"';
			break;
	}

	$perpage_selected[$mybb->get_input('perpage')] = 'selected="selected"';

	// Figure out if we need to display multiple pages.
	$mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$perpage = $mybb->input['perpage'];
	}
	else
	{
		$perpage = $mybb->input['perpage'] = $mybb->settings['scoresperpage'];	
	}

	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	$query = $db->query("
		SELECT COUNT(c.cid) AS page_count
		FROM ".TABLE_PREFIX."arcadechampions c
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=c.gid)
		WHERE g.active='1'{$cat_sql_game}
	");
	$page_count = $db->fetch_field($query, "page_count");

	$pages = ceil($page_count/$perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	if($mybb->input['order'] || $mybb->input['sortby'])
	{
		$page_url = "arcade.php?action=champions&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}&perpage={$mybb->input['perpage']}";
	}
	else
	{
		$page_url = "arcade.php?action=champions";
	}

	$multipage = multipage($page_count, $perpage, $page, $page_url);

	// Fetch the champions which will be displayed on this page
	$champ_bit = '';
	$query = $db->query("
		SELECT c.*, g.name, s.comment, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadechampions c
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (s.gid=c.gid AND s.uid=c.uid)
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=c.uid)
		WHERE g.active='1'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($champ = $db->fetch_array($query))
	{
		$champ['name'] = htmlspecialchars_uni($champ['name']);
		$champ['score'] = my_number_format((float)$champ['score']);

		$dateline = my_date('relative', $champ['dateline']);
		$champ['username'] = format_name(htmlspecialchars_uni($champ['username']), $champ['usergroup'], $champ['displaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$champ['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($champ['uid']);
		}

		// Parse smilies and bad words in the score comment
		$comment_parser = array(
			"allow_html" => 0,
			"allow_mycode" => 0,
			"allow_smilies" => 1,
			"allow_imgcode" => 0,
			"allow_videocode" => 0,
			"filter_badwords" => 1
		);

		$champ['comment'] = $parser->parse_message($champ['comment'], $comment_parser);

		$alt_bg = alt_trow();
		eval("\$champ_bit .= \"".$templates->get("arcade_champions_bit")."\";");
	}

	if(!$champ_bit)
	{
		$message = $lang->no_champions;
		$colspan = 5;
		eval("\$champ_bit = \"".$templates->get("arcade_no_display")."\";");
	}

	$plugins->run_hooks("arcade_champions_end");

	eval("\$champions = \"".$templates->get("arcade_champions")."\";");
	output_page($champions);
}

// Scoreboard (list of scores)
if($mybb->input['action'] == "scoreboard")
{
	add_breadcrumb($lang->scoreboard, "arcade.php?action=scoreboard");

	$plugins->run_hooks("arcade_scoreboard_start");

	$sortby_selected = array('name' => '', 'user' => '', 'date' => '');
	$order_selected = array('asc' => '', 'desc' => '');
	$perpage_selected = array(5 => '', 10 => '', 15 => '', 20 => '', 25 => '', 30 => '', 40 => '', 50 => '');

	$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));

	// Check the sorting order for games
	$order_select = '';
	switch($mybb->input['order'])
	{
		case "asc":
			$order = "asc";
			$order_selected['asc'] = "selected=\"selected\"";
			break;
		default:
			$order = "desc";
			$order_selected['desc'] = "selected=\"selected\"";
			break;
	}

	// Sort by which field?
	$mybb->input['sortby'] = htmlspecialchars_uni($mybb->get_input('sortby'));

	$sortby_select = '';
	switch($mybb->input['sortby'])
	{
		case "name":
			$sortby = "g.name";
			$sortby_selected['name'] = 'selected="selected"';
			break;
		case "user":
			$sortby = "s.username";
			$sortby_selected['user'] = 'selected="selected"';
			break;
		default:
			$sortby = "s.dateline";
			$sortby_selected['date'] = 'selected="selected"';
			break;
	}

	$perpage_selected[$mybb->get_input('perpage')] = 'selected="selected"';

	// Figure out if we need to display multiple pages.
	$mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$perpage = $mybb->input['perpage'];
	}
	else
	{
		$perpage = $mybb->input['perpage'] = $mybb->settings['scoresperpage'];	
	}

	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	$query = $db->query("
		SELECT COUNT(s.sid) AS page_count
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=s.gid)
		WHERE g.active='1'{$cat_sql_game}
	");
	$page_count = $db->fetch_field($query, "page_count");

	$pages = ceil($page_count/$perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	if($mybb->input['order'] || $mybb->input['sortby'])
	{
		$page_url = "arcade.php?action=scoreboard&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}&perpage={$mybb->input['perpage']}";
	}
	else
	{
		$page_url = "arcade.php?action=scoreboard";
	}

	$multipage = multipage($page_count, $perpage, $page, $page_url);

	// Fetch the scores which will be displayed on this page
	$score_bit = '';
	$query = $db->query("
		SELECT s.*, g.name, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=s.gid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
		WHERE g.active='1'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($score = $db->fetch_array($query))
	{
		$score['name'] = htmlspecialchars_uni($score['name']);
		$score['score'] = my_number_format((float)$score['score']);

		$dateline = my_date('relative', $score['dateline']);
		$score['username'] = format_name(htmlspecialchars_uni($score['username']), $score['usergroup'], $score['displaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$score['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($score['uid']);
		}

		// Does the current user have permission to delete this score? Show delete link
		$delete_link = '';
		if($mybb->usergroup['canmoderategames'] == 1)
		{
			eval("\$delete_link = \"".$templates->get("arcade_scores_delete")."\";");
		}

		// Does the current user have permission to edit this score's comment? Show edit link
		$edit_link = '';
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			eval("\$edit_link = \"".$templates->get("arcade_scores_edit")."\";");
		}

		// Parse smilies and bad words in the score comment
		$comment_parser = array(
			"allow_html" => 0,
			"allow_mycode" => 0,
			"allow_smilies" => 1,
			"allow_imgcode" => 0,
			"allow_videocode" => 0,
			"filter_badwords" => 1
		);

		$score['comment'] = $parser->parse_message($score['comment'], $comment_parser);

		$alt_bg = alt_trow();

		// Display IP address of scores if user is a mod/admin
		$ipaddressbit = '';
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			$score['ipaddress'] = my_inet_ntop($db->unescape_binary($score['ipaddress']));
			eval("\$ipaddressbit = \"".$templates->get("arcade_scores_bit_ipaddress")."\";");
		}

		eval("\$score_bit .= \"".$templates->get("arcade_scoreboard_bit")."\";");
	}

	if(!$score_bit)
	{
		$message = $lang->no_scoreboard;
		$colspan = 6;
		eval("\$score_bit = \"".$templates->get("arcade_no_display")."\";");
	}

	// Display IP address of scores if user is a mod/admin
	$ipaddresscol = '';
	if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
	{
		eval("\$ipaddresscol = \"".$templates->get("arcade_scores_ipaddress")."\";");
		$colspan = 6;
	}
	else
	{
		$colspan = 5;
	}

	$plugins->run_hooks("arcade_scoreboard_end");

	eval("\$scoreboard = \"".$templates->get("arcade_scoreboard")."\";");
	output_page($scoreboard);
}

// Search Arcade games
if($mybb->input['action'] == "do_search" && $mybb->request_method == "post")
{
	if($mybb->settings['arcade_searching'] == 0)
	{
		error($lang->error_searchingdisabled);
	}

	if($mybb->usergroup['cansearchgames'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("arcade_do_search_start");

	// Check if search flood checking is enabled and user is not admin
	if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		$timecut = TIME_NOW-$mybb->settings['searchfloodtime'];
		$query = $db->simple_select("searchlog", "*", "uid='".(int)$mybb->user['uid']."' AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if($last_search['sid'])
		{
			$remaining_time = $mybb->settings['searchfloodtime']-(TIME_NOW-$last_search['dateline']);
			if($remaining_time == 1)
			{
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
			}
			else
			{
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
			}
			error($lang->error_searchflooding);
		}
	}

	if($mybb->input['name'] != 1 && $mybb->input['description'] != 1)
	{
		error($lang->error_nosearchresults);
	}

	$search_data = array(
		"keywords" => $mybb->input['keywords'],
		"name" => $mybb->input['name'],
		"description" => $mybb->input['description'],
		"cid" => $mybb->get_input('cid', MyBB::INPUT_INT),
	);

	if($db->can_search == true)
	{		
		$search_results = arcade_perform_search_mysql($search_data);
	}
	else
	{
		error($lang->error_no_search_support);
	}
	$sid = md5(uniqid(microtime(), 2));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => (int)$mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_binary($session->packedip),
		"threads" => '',
		"posts" => '',
		"resulttype" => "games",
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->input['keywords']),
	);
	$plugins->run_hooks("arcade_do_search_process");

	$db->insert_query("searchlog", $searcharray);

	$plugins->run_hooks("arcade_do_search_end");
	redirect("arcade.php?action=results&sid={$sid}", $lang->redirect_searchresults);
}

// Arcade search results
if($mybb->input['action'] == "results")
{
	if($mybb->settings['arcade_searching'] == 0)
	{
		error($lang->error_searchingdisabled);
	}

	if($mybb->usergroup['cansearchgames'] == 0)
	{
		error_no_permission();
	}

	$sid = $db->escape_string($mybb->input['sid']);
	$query = $db->simple_select("searchlog", "*", "sid='{$sid}' AND uid='".(int)$mybb->user['uid']."'");
	$search = $db->fetch_array($query);

	if(!$search['sid'])
	{
		error($lang->error_invalidsearch);
	}

	$sortby_selected = array('date' => '', 'plays' => '', 'lastplayed' => '', 'rating' => '', 'name' => '');
	$order_selected = array('asc' => '', 'desc' => '');

	$plugins->run_hooks("arcade_results_start");

	add_breadcrumb($lang->search_results, "arcade.php?action=results&{$sid}");

	// Pick the sort order.
	if(!isset($mybb->input['order']) && !empty($mybb->settings['gamesorder']))
	{
		$mybb->input['order'] = $mybb->settings['gamesorder'];
	}

	$mybb->input['order'] = htmlspecialchars_uni($mybb->input['order']);

	// Check the sorting order for games
	$order_select = '';
	switch($mybb->input['order'])
	{
		case "desc":
			$order = "desc";
			$order_selected['desc'] = "selected=\"selected\"";
			break;
		default:
			$order = "asc";
			$order_selected['asc'] = "selected=\"selected\"";
			break;
	}

	// Sort by which field?
	if(!isset($mybb->input['sortby']) && !empty($mybb->settings['gamessortby']))
	{
		$mybb->input['sortby'] = $mybb->settings['gamessortby'];
	}

	$mybb->input['sortby'] = htmlspecialchars_uni($mybb->input['sortby']);

	$sortby_select = '';
	switch($mybb->input['sortby'])
	{
		case "date":
			$sortby = "g.dateline";
			$sortby_selected['date'] = 'selected="selected"';
			break;
		case "plays":
			$sortby = "g.plays";
			$sortby_selected['plays'] = 'selected="selected"';
			break;
		case "lastplayed":
			$sortby = "g.lastplayed";
			$sortby_selected['lastplayed'] = 'selected="selected"';
			break;
		case "rating":
			$sortby = "g.totalratings";
			$sortby_selected['rating'] = 'selected="selected"';
			break;
		default:
			$sortby = "g.name";
			$sortby_selected['name'] = 'selected="selected"';
			break;
	}

	// Work out pagination, which page we're at, as well as the limits.
	$perpage = $mybb->settings['gamesperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;

	// Work out if we have terms to highlight
	$highlight = '';
	if($search['keywords'])
	{
		$highlight = "&amp;highlight=".urlencode($search['keywords']);
	}

	// Do Multi Pages
	$query = $db->simple_select("arcadegames", "COUNT(*) AS total", "gid IN(".$db->escape_string($search['querycache']).")");
	$game_count = $db->fetch_array($query);

	if($upper > $game_count)
	{
		$upper = $game_count;
	}

	$multipage = multipage($game_count['total'], $perpage, $page, "arcade.php?action=results&amp;sid=".htmlspecialchars_uni($mybb->input['sid'])."&amp;sortby={$mybb->input['sortby']}&amp;order={$mybb->input['order']}");

	switch($db->type)
	{
		case "pgsql":
			$ratingadd = "CASE WHEN g.numratings=0 THEN 0 ELSE g.totalratings/g.numratings::numeric END AS averagerating, ";
			break;
		default:
			$ratingadd = "(g.totalratings/g.numratings) AS averagerating, ";
	}

	// Fetch the games which will be displayed on this page
	$game_bit = '';
	$query = $db->query("
		SELECT g.*, {$ratingadd}u.username, u.usergroup, u.displaygroup, s.score, f.fid AS favorite, c.score AS champscore, c.uid AS champuid, c.username AS champusername, cu.usergroup AS champusergroup, cu.displaygroup AS champdisplaygroup, r.uid AS rated
		FROM ".TABLE_PREFIX."arcadegames g
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (g.gid=s.gid AND s.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcadechampions c ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadefavorites f ON (g.gid=f.gid AND f.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcaderatings r ON (g.gid=r.gid AND r.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."users u ON (g.lastplayeduid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users cu ON (c.uid=cu.uid)
		WHERE g.gid IN(".$db->escape_string($search['querycache']).") AND g.active='1'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($game = $db->fetch_array($query))
	{
		$game_bit .= build_gamebit($game);
	}

	$arcaderating = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		eval("\$arcaderating = \"".$templates->get("arcade_rating")."\";");
	}

	$plugins->run_hooks("arcade_results_end");

	eval("\$results = \"".$templates->get("arcade_search_results")."\";");
	output_page($results);
}

// Arcade home page
if(!$mybb->input['action'])
{
	$where_cat = $catinput = '';
	if(!empty($mybb->input['cid']))
	{
		$cid = $mybb->get_input('cid', MyBB::INPUT_INT);

		$query = $db->simple_select("arcadecategories", "*", "cid='{$cid}'");
		$category = $db->fetch_array($query);

		// Invalid category
		if(!$category['cid'])
		{
			error($lang->error_invalidcategory);
		}

		if($category['active'] != 1)
		{
			error($lang->error_categoryinactive);
		}

		$category_check = explode(',', $unviewable);
		if(in_array($cid, $category_check))
		{
			error($lang->error_nocategorypermission);
		}

		eval("\$catinput = \"".$templates->get("arcade_catinput")."\";");
		$where_cat = " AND g.cid='{$cid}'";
	}

	// Stats box
	if($mybb->settings['arcade_stats'] == 1)
	{
		$collapsed['arcadestats_e'] = $collapsed['arcadecat_e'] ='';
		$collapsedimg['arcadestats'] = $collapsedimg['arcadecat'] ='';

		// Newest Games
		$newestgames = '';
		$query = $db->simple_select("arcadegames", "gid, name, dateline, smallimage", "active='1'{$cat_sql}", array('order_by' => 'dateline', 'order_dir' => 'desc', 'limit' => $mybb->settings['arcade_stats_newgames']));
		while($game = $db->fetch_array($query))
		{
			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&gid={$game['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&gid={$game['gid']}";
			}

			eval("\$newestgames .= \"".$templates->get("arcade_statistics_gamebit")."\";");
		}
		if(!$newestgames)
		{
			eval("\$newestgames = \"".$templates->get("arcade_statistics_no_games")."\";");
		}

		// Most played games
		$mostplayedgames = '';
		$query2 = $db->simple_select("arcadegames", "gid, name, plays, smallimage", "active='1'{$cat_sql}", array('order_by' => 'plays', 'order_dir' => 'DESC', 'limit' => $mybb->settings['arcade_stats_newgames']));
		while($game = $db->fetch_array($query2))
		{
			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&gid={$game['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&gid={$game['gid']}";
			}

			eval("\$mostplayedgames .= \"".$templates->get("arcade_statistics_gamebit")."\";");
		}
		if(!$mostplayedgames)
		{
			eval("\$mostplayedgames = \"".$templates->get("arcade_statistics_no_games")."\";");
		}

		// Newest Champions
		$newestchamps = '';
		$query3 = $db->query("
			SELECT c.gid, c.uid, c.username, c.dateline, c.score, g.name, g.smallimage, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."arcadechampions c
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (c.uid=u.uid)
			WHERE g.active ='1'{$cat_sql_game}
			ORDER BY c.dateline DESC
			LIMIT {$mybb->settings['arcade_stats_newchamps']}
		");
		while($score = $db->fetch_array($query3))
		{
			$score['name'] = htmlspecialchars_uni($score['name']);
			$score['score'] = my_number_format((float)$score['score']);

			$dateline = my_date('relative', $score['dateline']);
			$score['username'] = format_name(htmlspecialchars_uni($score['username']), $score['usergroup'], $score['displaygroup']);

			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$score['uid']}";
			}
			else
			{
				$profilelink = get_profile_link($score['uid']);
			}

			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&amp;gid={$score['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&amp;gid={$score['gid']}";
			}

			eval("\$newestchamps .= \"".$templates->get("arcade_statistics_scorebit")."\";");
		}

		if(!$newestchamps)
		{
			eval("\$newestchamps = \"".$templates->get("arcade_statistics_no_champs")."\";");
		}

		// Latest Scores
		$latestscores = '';
		$query4 = $db->query("
			SELECT s.gid, s.uid, s.username, s.dateline, s.score, g.name, g.smallimage, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."arcadescores s
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
			WHERE g.active ='1'{$cat_sql_game}
			ORDER BY s.dateline DESC
			LIMIT {$mybb->settings['arcade_stats_newscores']}
		");
		while($score = $db->fetch_array($query4))
		{
			$score['name'] = htmlspecialchars_uni($score['name']);
			$score['score'] = my_number_format((float)$score['score']);

			$dateline = my_date('relative', $score['dateline']);
			$score['username'] = format_name(htmlspecialchars_uni($score['username']), $score['usergroup'], $score['displaygroup']);

			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$score['uid']}";
			}
			else
			{
				$profilelink = get_profile_link($score['uid']);
			}

			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&gid={$score['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&gid={$score['gid']}";
			}

			eval("\$latestscores .= \"".$templates->get("arcade_statistics_scorebit")."\";");
		}
		if(!$latestscores)
		{
			eval("\$latestscores = \"".$templates->get("arcade_statistics_no_scores")."\";");
		}

		// Best Players
		if($mybb->settings['arcade_stats_bestplayers'] == 1)
		{
			$rank = 0;

			$query5 = $db->query("
				SELECT c.uid, c.username, u.avatar, u.avatardimensions, COUNT(c.gid) AS champs, u.usergroup, u.displaygroup
				FROM ".TABLE_PREFIX."arcadechampions c
				LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=c.gid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=c.uid)
				WHERE g.active ='1'{$cat_sql_game}
				GROUP BY c.uid, c.username
				ORDER BY champs DESC
				LIMIT 3
			");
			$bestplayers_bit = '';
			while($champ = $db->fetch_array($query5))
			{
				$rank++;
				$bestplayer_rank_lang = "bestplayers_place_".$rank;
				$bestplayer_rank_lang = $lang->$bestplayer_rank_lang;

				$best_player_avatar = '';
				if($mybb->settings['arcade_stats_avatar'] == 1)
				{
					$useravatar = format_avatar(htmlspecialchars_uni($champ['avatar']), $champ['avatardimensions'], '100x100');
					eval("\$best_player_avatar = \"".$templates->get("arcade_statistics_bestplayers_avatar")."\";");
				}

				$champ['username'] = format_name(htmlspecialchars_uni($champ['username']), $champ['usergroup'], $champ['displaygroup']);
				$with_wins = $lang->sprintf($lang->with_wins, $champ['champs']);

				if($mybb->usergroup['canviewgamestats'] == 1)
				{
					$profilelink = "arcade.php?action=stats&uid={$champ['uid']}";
				}
				else
				{
					$profilelink = get_profile_link($champ['uid']);
				}

				eval("\$bestplayers_bit .= \"".$templates->get("arcade_statistics_bestplayers_bit")."\";");
			}

			if(!$bestplayers_bit)
			{
				eval("\$bestplayers_bit = \"".$templates->get("arcade_statistics_no_champs")."\";");
			}

			eval("\$bestplayers = \"".$templates->get("arcade_statistics_bestplayers")."\";");
		}

		eval("\$stats = \"".$templates->get("arcade_statistics")."\";");
	}

	// Category box
	$categorycount = 0;
	$categories = $categorybit = '';
	$query = $db->query("
		SELECT c.*, COUNT(g.gid) AS games
		FROM ".TABLE_PREFIX."arcadecategories c
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.cid=g.cid AND g.active='1')
		WHERE c.active='1'{$cat_sql_cat}
		GROUP BY c.cid
		ORDER BY c.name ASC
	");
	while($category = $db->fetch_array($query))
	{
		$value = 100/$mybb->settings['arcade_category_number'];
		$category['name'] = htmlspecialchars_uni($category['name']);

		$image = '';
		if(is_file($category['image']))
		{
			eval("\$image = \"".$templates->get('arcade_category_bit_image')."\";");
		}

		eval("\$categorybit .= \"".$templates->get('arcade_category_bit')."\";");
		++$categorycount;
	}
	if($categorycount > 0)
	{
		eval("\$categories = \"".$templates->get('arcade_categories')."\";");
	}

	// Tournaments box
	$tournaments = '';
	if($mybb->settings['enabletournaments'] == 1 && $mybb->usergroup['canviewtournaments'] == 1)
	{
		$tournaments_stats = $cache->read("tournaments_stats");

		$tournaments_stats['numwaitingtournaments'] = my_number_format($tournaments_stats['numwaitingtournaments']);
		$tournaments_stats['numrunningtournaments'] = my_number_format($tournaments_stats['numrunningtournaments']);
		$tournaments_stats['numfinishedtournaments'] = my_number_format($tournaments_stats['numfinishedtournaments']);

		$lang->tournaments_running = $lang->sprintf($lang->tournaments_running, $tournaments_stats['numrunningtournaments']);
		$lang->tournaments_finished = $lang->sprintf($lang->tournaments_finished, $tournaments_stats['numfinishedtournaments']);
		$lang->tournaments_waiting = $lang->sprintf($lang->tournaments_waiting, $tournaments_stats['numwaitingtournaments']);

		// Display cancelled tournament stats to arcade moderators
		$tournamentscancelled = '';
		if($mybb->usergroup['canmoderategames'] == 1)
		{
			$tournaments_stats['numcancelledtournaments'] = my_number_format($tournaments_stats['numcancelledtournaments']);
			$lang->tournaments_cancelled = $lang->sprintf($lang->tournaments_cancelled, $tournaments_stats['numcancelledtournaments']);

			eval("\$tournamentscancelled = \"".$templates->get('arcade_tournaments_cancelled')."\";");
		}

		$tournamentswaiting = $tournamentmember = '';
		if($mybb->usergroup['canjointournaments'] == 1)
		{
			$numgames = 0;
			$enrolledin = 0;

			$activetournaments = '';
			$query = $db->query("
				SELECT t.tid, t.status, g.name, g.smallimage
				FROM ".TABLE_PREFIX."arcadetournamentplayers p
				LEFT JOIN ".TABLE_PREFIX."arcadetournaments t ON (p.tid=t.tid AND t.round=p.round)
				LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (t.gid=g.gid)
				WHERE p.uid='{$mybb->user['uid']}' AND t.status IN(1,2) AND p.status !='3' AND g.active='1'{$cat_sql_game}
				ORDER BY t.dateline DESC
			");
			while($activeenrolled = $db->fetch_array($query))
			{
				++$numgames;

				if($activeenrolled['status'] == 2)
				{
					++$enrolledin;

					$tournament_game = $lang->sprintf($lang->tournament_game, $activeenrolled['name']);
					eval("\$activetournaments .= \"".$templates->get("arcade_tournaments_user_game")."\";");
				}
			}

			if($enrolledin < 1)
			{
				$activetournaments = $lang->na;
			}

			$tournamentcreate = '';
			if($mybb->usergroup['cancreatetournaments'] == 1)
			{
				eval("\$tournamentcreate .= \"".$templates->get('arcade_tournaments_create')."\";");
			}

			eval("\$tournamentswaiting = \"".$templates->get('arcade_tournaments_waiting')."\";");
			eval("\$tournamentmember .= \"".$templates->get('arcade_tournaments_user')."\";");
		}
		else
		{
			$tournamentswaiting = "{$lang->tournaments_waiting}";
		}

		eval("\$tournaments = \"".$templates->get('arcade_tournaments')."\";");
	}

	// Search box
	$search = '';
	if($mybb->settings['arcade_searching'] == 1 && $mybb->usergroup['cansearchgames'] == 1)
	{
		$searchcategorycount = 0;
		$categoryoptions = '';
		$query = $db->simple_select("arcadecategories", "*", "active='1'{$cat_sql}", array('order_by' => 'name', 'order_dir' => 'asc'));
		while($category = $db->fetch_array($query))
		{
			$category['name'] = htmlspecialchars_uni($category['name']);
			eval("\$categoryoptions .= \"".$templates->get("arcade_search_catagory_option")."\";");
			++$searchcategorycount;
		}

		$categorysearch = '';
		if($searchcategorycount > 0)
		{
			eval("\$categorysearch = \"".$templates->get("arcade_search_catagory")."\";");
		}

		eval("\$search = \"".$templates->get('arcade_search')."\";");
	}

	$sortby_selected = array('date' => '', 'plays' => '', 'lastplayed' => '', 'rating' => '', 'name' => '');
	$order_selected = array('asc' => '', 'desc' => '');

	// Pick the sort order.
	if(!isset($mybb->input['order']) && !empty($mybb->settings['gamesorder']))
	{
		$mybb->input['order'] = $mybb->settings['gamesorder'];
	}
	else
	{
		$mybb->input['order'] = $mybb->get_input('order');
	}

	$mybb->input['order'] = htmlspecialchars_uni($mybb->input['order']);

	// Check the sorting order for games
	$order_select = '';
	switch($mybb->input['order'])
	{
		case "desc":
			$order = "desc";
			$order_selected['desc'] = "selected=\"selected\"";
			break;
		default:
			$order = "asc";
			$order_selected['asc'] = "selected=\"selected\"";
			break;
	}

	// Sort by which field?
	if(!isset($mybb->input['sortby']) && !empty($mybb->settings['gamessortby']))
	{
		$mybb->input['sortby'] = $mybb->settings['gamessortby'];
	}
	else
	{
		$mybb->input['sortby'] = $mybb->get_input('sortby');
	}

	$mybb->input['sortby'] = htmlspecialchars_uni($mybb->input['sortby']);

	$sortby_select = '';
	switch($mybb->input['sortby'])
	{
		case "date":
			$sortby = "g.dateline";
			$sortby_selected['date'] = 'selected="selected"';
			break;
		case "plays":
			$sortby = "g.plays";
			$sortby_selected['plays'] = 'selected="selected"';
			break;
		case "lastplayed":
			$sortby = "g.lastplayed";
			$sortby_selected['lastplayed'] = 'selected="selected"';
			break;
		case "rating":
			$sortby = "g.totalratings";
			$sortby_selected['rating'] = 'selected="selected"';
			break;
		default:
			$sortby = "g.name";
			$sortby_selected['name'] = 'selected="selected"';
			break;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['gamesperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	if($mybb->get_input('cid') > 0)
	{
		$query = $db->simple_select("arcadegames", "COUNT(gid) AS page_count", "cid='{$cid}' AND active='1'");
	}
	else
	{
		$query = $db->simple_select("arcadegames", "COUNT(gid) AS page_count", "active='1'{$cat_sql}");
	}
	$page_count = $db->fetch_field($query, "page_count");

	$pages = ceil($page_count/$perpage);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	// Assemble page URL
	$page_url = "arcade.php";

	$q = $and = '';
	if($mybb->get_input('cid') > 0)
	{
		$cid = $mybb->get_input('cid', MyBB::INPUT_INT);
		$page_url .= "?cid={$cid}";
		$and = "&";
	}
	else
	{
		$q .= '?';
	}

	if($mybb->input['order'] != "{$mybb->settings['gamesorder']}" || $mybb->input['sortby'] != "{$mybb->settings['gamessortby']}")
	{
		$page_url .= "{$q}{$and}sortby={$mybb->input['sortby']}&order={$mybb->input['order']}";
	}

	$multipage = multipage($page_count, $perpage, $page, $page_url);

	switch($db->type)
	{
		case "pgsql":
			$ratingadd = "CASE WHEN g.numratings=0 THEN 0 ELSE g.totalratings/g.numratings::numeric END AS averagerating, ";
			break;
		default:
			$ratingadd = "(g.totalratings/g.numratings) AS averagerating, ";
	}

	// Fetch the games which will be displayed on this page
	$query = $db->query("
		SELECT g.*, {$ratingadd}u.username, u.usergroup, u.displaygroup, s.score, f.fid AS favorite, c.score AS champscore, c.uid AS champuid, c.username AS champusername, cu.usergroup AS champusergroup, cu.displaygroup AS champdisplaygroup, r.uid AS rated
		FROM ".TABLE_PREFIX."arcadegames g
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (g.gid=s.gid AND s.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcadechampions c ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadefavorites f ON (g.gid=f.gid AND f.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcaderatings r ON (g.gid=r.gid AND r.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."users u ON (g.lastplayeduid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users cu ON (c.uid=cu.uid)
		WHERE g.active='1'{$where_cat}{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	$game_bit = '';
	while($game = $db->fetch_array($query))
	{
		$game_bit .= build_gamebit($game);
	}

	if(!$game_bit)
	{
		eval("\$game_bit = \"".$templates->get("arcade_no_games")."\";");
	}

	$arcaderating = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		eval("\$arcaderating = \"".$templates->get("arcade_rating")."\";");
	}

	$plugins->run_hooks("arcade_end");

	eval("\$arcadehome = \"".$templates->get("arcade")."\";");
	output_page($arcadehome);
}
