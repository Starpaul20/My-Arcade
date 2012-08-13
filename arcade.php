<?php
/**
 * My Arcade
 * Copyright 2012 Starpaul20
 */

define("IN_MYBB", 1);
define("IGNORE_CLEAN_VARS", "sid");
define('THIS_SCRIPT', 'arcade.php');

$templatelist = "arcade,arcade_categories,arcade_category_bit,arcade_gamebit,arcade_menu,arcade_settings,arcade_settings_gamesselect,arcade_settings_scoreselect,arcade_settings_whosonline,arcade_settings_tournamentnotify,arcade_settings_champpostbit";
$templatelist .= ",arcade_statistics,arcade_stats,arcade_statistics_bestplayers,arcade_statistics_bestplayers_bit,arcade_statistics_gamebit,arcade_statistics_scorebit,multipage_page_current,multipage_page,multipage_nextpage,multipage,multipage_prevpage";
$templatelist .= ",arcade_champions,arcade_champions_bit,arcade_scoreboard_bit,arcade_scoreboard,arcade_stats_bit,arcade_stats_details,arcade_stats_tournaments,arcade_tournaments_create,arcade_tournaments_user,arcade_tournaments_user_game,arcade_tournaments";
$templatelist .= ",arcade_rating,arcade_online_memberbit,arcade_online,arcade_search_catagory,arcade_search,arcade_no_games,arcade_scores,arcade_scores_bit,arcade_no_display,arcade_play,arcade_play_rating,arcade_play_tournament,arcade_favorites";

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

if(!$mybb->settings['scoresperpage'])
{
	$mybb->settings['scoresperpage'] = 10;
}

if(!$mybb->settings['gamesperpage'])
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

// Top Menu bar (for members only)
if($mybb->user['uid'] != 0)
{
	eval("\$menu = \"".$templates->get("arcade_menu")."\";");
}

// Gets only games this user can view (based on category group permission)
$unviewable = get_unviewable_categories($mybb->user['usergroup']);
if($unviewable)
{
	$cat_sql_cat .= " AND c.cid NOT IN ($unviewable)";
	$cat_sql_game .= " AND g.cid NOT IN ($unviewable)";
	$cat_sql .= " AND cid NOT IN ($unviewable)";
}

// Build Who's Online box
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
else
{
	$online = "";
}

// V3Arcade insert of a score
switch($mybb->input['sessdo'])
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
		$perpage = intval($mybb->settings['scoresperpage']);

		$score = $mybb->cookies['v3score'];
		$name = $mybb->input['id'];
		$sid = $mybb->cookies['arcadesession'];

		$query = $db->query("
			SELECT s.*, g.*
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
			if($pagenum > 1)
			{
				$page = "&page={$pagenum}";
			}
			else
			{
				$page = "";
			}

			my_unsetcookie('v3score');
			redirect("arcade.php?action=scores&gid={$game['gid']}&newscore=1{$page}", $message);
		}
	break;
}

// Playing a game
if($mybb->input['action'] == "play")
{
	if($mybb->usergroup['canplayarcade'] != 1)
	{
		error_no_permission();
	}

	$gid = intval($mybb->input['gid']);
	$game = get_game($gid);

	if($mybb->settings['enabletournaments'] == 1 && $mybb->input['tid'])
	{
		$tid = intval($mybb->input['tid']);

		$query = $db->query("
			SELECT t.*, p.*
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
		$query = $db->simple_select("arcadesessions", "COUNT(*) AS play_count", "uid='{$mybb->user['uid']}' AND dateline >= '".(TIME_NOW - (60*60*24))."'");
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
	$game['about'] = htmlspecialchars_uni($game['about']);
	$game['controls'] = htmlspecialchars_uni($game['controls']);

	add_breadcrumb($game['name'], "arcade.php?action=play&gid={$game['gid']}");

	// Load Tournament info if inputted
	if($mybb->settings['enabletournaments'] == 1 && $mybb->input['tid'])
	{
		// Create an arcade session (to ensure proper submitting and scoring)
		$sid = md5(uniqid(microtime(), 3));
		$new_session = array(
			"sid" => $db->escape_string($sid),
			"uid" => intval($mybb->user['uid']),
			"gid" => intval($game['gid']),
			"tid" => intval($tid),
			"dateline" => TIME_NOW,
			"gname" => $db->escape_string($game['file']),
			"gtitle" => $db->escape_string($game['name']),
			"ipaddress" => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("arcadesessions", $new_session);

		my_setcookie('arcadesession', $sid, 21600);

		$startedon = $information[$tournament['round']]['starttime'];
		$roundstartedon = my_date($mybb->settings['dateformat'], $startedon).", ".my_date($mybb->settings['timeformat'], $startedon);
		$triesleft = ($tournament['tries'] - $tournament['attempts']);
		$hightournamentscore = my_number_format(floatval($tournament['score']));

		eval("\$tournaments = \"".$templates->get("arcade_play_tournament")."\";");
	}
	else
	{
		// Create an arcade session (to ensure proper submitting and scoring)
		$sid = md5(uniqid(microtime(), 3));
		$new_session = array(
			"sid" => $db->escape_string($sid),
			"uid" => intval($mybb->user['uid']),
			"gid" => intval($game['gid']),
			"dateline" => TIME_NOW,
			"gname" => $db->escape_string($game['file']),
			"gtitle" => $db->escape_string($game['name']),
			"ipaddress" => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("arcadesessions", $new_session);

		my_setcookie('arcadesession', $sid, 21600);
	}

	$query = $db->simple_select("arcadechampions", "*", "gid='{$game['gid']}'");
	$champ = $db->fetch_array($query);

	if($champ['score'])
	{
		$champ['score'] = my_number_format(floatval($champ['score']));
		$lang->current_champion = $lang->sprintf($lang->current_champion, $champ['score']);
	}
	else
	{
		$lang->current_champion = $lang->sprintf($lang->current_champion, $lang->na);
	}

	if($champ['username'])
	{
		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$champ['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($champ['uid']);
		}

		$champusername = "<a href=\"{$profilelink}\">{$champ['username']}</a>";
	}
	else
	{
		$champusername = $lang->na;
	}

	// User's best score
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("arcadescores", "score", "gid='{$game['gid']}' AND uid='".intval($mybb->user['uid'])."'");
		$score = $db->fetch_array($query);

		if($score['score'])
		{
			$userbestscore = my_number_format(floatval($score['score']));
		}
		else
		{
			$userbestscore = $lang->na;
		}
	}

	// Favorite check
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("arcadefavorites", "gid", "gid='".intval($game['gid'])."' AND uid='".intval($mybb->user['uid'])."'", array('limit' => 1));
		if($db->fetch_field($query, 'gid'))
		{
			$add_remove_favorite = "<a href=\"arcade.php?action=removefavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->remove_from_favorites}</a><br />";
		}
		else
		{
			$add_remove_favorite = "<a href=\"arcade.php?action=addfavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->add_to_favorites}</a><br />";
		}
	}
	else
	{
		$add_remove_favorite = '';
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
			$game['averagerating'] = floatval(round($game['totalratings']/$game['numratings'], 2));
			$game['rating_width'] = intval(round($game['averagerating']))*20;
			$game['numratings'] = intval($game['numratings']);
		}

		if($game['numratings'])
		{
			// At least >someone< has rated this game, was it me?
			// Check if we have already voted on this game - it won't show hover effect then.
			$query = $db->simple_select("arcaderatings", "uid", "gid='{$game['gid']}' AND uid='".intval($mybb->user['uid'])."'");
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

	if($mybb->user['uid'] == 0)
	{
		$guestmessage = "<span class=\"smalltext\">{$lang->guest_scoring}</span><br /><br />";
	}
	else
	{
		$guestmessage = "";
	}

	// Increment play views, last play time and last play uid.
	$update_game = array(
		"plays" => intval($game['plays']) + 1,
		"lastplayed" => TIME_NOW,
		"lastplayeduid" => intval($mybb->user['uid'])
	);
	$db->update_query("arcadegames", $update_game, "gid='{$game['gid']}'");

	$plugins->run_hooks("arcade_play_end");

	eval("\$play = \"".$templates->get("arcade_play")."\";");
	output_page($play);
}

// High scores for a game
if($mybb->input['action'] == "scores")
{
	$gid = intval($mybb->input['gid']);
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

	$plugins->run_hooks("arcade_scores_start");

	$game['name'] = htmlspecialchars_uni($game['name']);
	$game['description'] = htmlspecialchars_uni($game['description']);

	$lang->play_game = $lang->sprintf($lang->play_game, $game['name']);
	$lang->highest_scores_of = $lang->sprintf($lang->highest_scores_of, $game['name']);

	add_breadcrumb($lang->highest_scores_of, "arcade.php?action=scores&gid={$game['gid']}");

	// Figure out if we need to display multiple pages.
	$perpage = intval($mybb->input['perpage']);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $mybb->settings['scoresperpage'];
	}

	$query = $db->simple_select("arcadescores", "COUNT(sid) AS count", "gid ='{$gid}'");
	$result = $db->fetch_field($query, "count");

	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}

	$pages = $result / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
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

	// Favorite check
	if($mybb->user['uid'] != 0)
	{
		$query = $db->simple_select("arcadefavorites", "gid", "gid='".intval($gid)."' AND uid='".intval($mybb->user['uid'])."'", array('limit' => 1));
		if($db->fetch_field($query, 'gid'))
		{
			$add_remove_favorite = "<a href=\"arcade.php?action=removefavorite&gid={$gid}&my_post_key={$mybb->post_code}\">{$lang->remove_from_favorites}</a><br />";
		}
		else
		{
			$add_remove_favorite = "<a href=\"arcade.php?action=addfavorite&gid={$gid}&my_post_key={$mybb->post_code}\">{$lang->add_to_favorites}</a><br />";
		}
	}
	else
	{
		$add_remove_favorite = '';
	}

	// Work out the rating for this game.
	$rating = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		if($game['numratings'] <= 0)
		{
			$game['width'] = 0;
			$game['averagerating'] = 0;
			$game['numratings'] = 0;
		}
		else
		{
			$game['averagerating'] = floatval(round($game['totalratings']/$game['numratings'], 2));
			$game['width'] = intval(round($game['averagerating']))*20;
			$game['numratings'] = intval($game['numratings']);
		}

		if($game['numratings'])
		{
			// At least >someone< has rated this game, was it me?
			// Check if we have already voted on this game - it won't show hover effect then.
			$query = $db->simple_select("arcaderatings", "uid", "gid='{$gid}' AND uid='".intval($mybb->user['uid'])."'");
			$rated = $db->fetch_field($query, 'uid');
		}

		$not_rated = '';
		if(!$rated)
		{
			$not_rated = ' star_rating_notrated';
		}

		$ratingvotesav = $lang->sprintf($lang->rating_average, $game['numratings'], $game['averagerating']);
		eval("\$rategame = \"".$templates->get("arcade_rating")."\";");
	}

	// Fetch the scores which will be displayed on this page
	$query = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."arcadescores
		WHERE gid='{$gid}'
		ORDER BY score {$game['sortby']}, dateline ASC
		LIMIT {$start}, {$perpage}
	");
	while($score = $db->fetch_array($query))
	{
		$score['score'] = my_number_format(floatval($score['score']));
		$dateline = my_date($mybb->settings['dateformat'], $score['dateline']).", ".my_date($mybb->settings['timeformat'], $score['dateline']);

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
		if($mybb->usergroup['canmoderategames'] == 1)
		{
			$delete_link = "[<a href=\"arcade.php?action=delete&amp;sid={$score['sid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"if(confirm(&quot;{$lang->delete_score_confirm}&quot;))window.location=this.href.replace('action=delete','action=delete');return false;\">{$lang->delete}</a>]";
		}
		else
		{
			$delete_link = '';
		}

		// Does the current user have permission to edit this score comment? Show edit link
		$time = TIME_NOW;
		if($mybb->usergroup['canmoderategames'] == 1 || ($score['uid'] == $mybb->user['uid'] && ($mybb->settings['arcade_editcomment'] == 0 || $score['dateline'] > ($time-($mybb->settings['arcade_editcomment']*60)))))
		{
			$edit_link = "[<a href=\"javascript:MyBB.popupWindow('arcade.php?action=edit&amp;sid={$score['sid']}', 'editcomment', '400', '300') \">{$lang->edit}</a>]";
		}
		else
		{
			$edit_link = '';
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
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			$ipaddressbit = "<td class=\"{$alt_bg}\" align=\"center\">{$score['ipaddress']}</td>";
		}

		eval("\$score_bit .= \"".$templates->get("arcade_scores_bit")."\";");
	}

	if(!$score_bit)
	{
		eval("\$score_bit = \"".$templates->get("arcade_scores_no_scores")."\";");
	}

	// Display IP address of scores if user is a mod/admin
	if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
	{
		$ipaddresscol = "<td class=\"tcat\" width=\"10%\" align=\"center\"><span class=\"smalltext\"><strong>{$lang->ip_address}</strong></span></td>";
		$colspan = "7";
	}
	else
	{
		$colspan = "6";
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
	verify_post_check($mybb->input['my_post_key']);

	$gid = intval($mybb->input['gid']);
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

	$mybb->input['rating'] = intval($mybb->input['rating']);
	if($mybb->input['rating'] < 1 || $mybb->input['rating'] > 5)
	{
		error($lang->error_invalidrating);
	}

	// Check to see if user has already rated this game
	$query = $db->simple_select("arcaderatings", "*", "uid='".intval($mybb->user['uid'])."' AND gid='{$gid}'");
	$ratecheck = $db->fetch_array($query);

	if($ratecheck['rid'])
	{
		error($lang->error_alreadyratedgame);
	}
	else
	{
		$update_game = array(
			"numratings" => $game['numratings'] + 1,
			"totalratings" => $game['totalratings'] + $mybb->input['rating']
		);
		$db->update_query("arcadegames", $update_game, "gid='{$gid}'");

		$insertarray = array(
			'gid' => $gid,
			'uid' => intval($mybb->user['uid']),
			'rating' => $mybb->input['rating'],
			'ipaddress' => $db->escape_string($session->ipaddress)
		);
		$db->insert_query("arcaderatings", $insertarray);
	}

	if($mybb->input['ajax'])
	{
		echo "<success>{$lang->rating_added}</success>\n";
		$query = $db->simple_select("arcadegames", "totalratings, numratings", "gid='$gid'", array('limit' => 1));
		$fetch = $db->fetch_array($query);
		$width = 0;
		if($fetch['numratings'] >= 0)
		{
			$averagerating = floatval(round($fetch['totalratings']/$fetch['numratings'], 2));
			$width = intval(round($averagerating))*20;
			$fetch['numratings'] = intval($fetch['numratings']);
			$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $fetch['numratings'], $averagerating);
			echo "<average>{$ratingvotesav}</average>\n";
		}
		echo "<width>{$width}</width>";
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
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("arcade_delete_start");

	$query = $db->simple_select("arcadescores", "sid, gid, uid", "sid='".intval($mybb->input['sid'])."'");
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
	verify_post_check($mybb->input['my_post_key']);

	$query = $db->simple_select("arcadescores", "sid, uid, comment", "sid='".intval($mybb->input['sid'])."'");
	$score = $db->fetch_array($query);

	$plugins->run_hooks("arcade_do_edit_start");

	if(!$score['sid'])
	{
		$message = $lang->error_invalidscore;
		eval("\$error = \"".$templates->get("arcade_edit_error")."\";");
		output_page($error);
		exit;
	}

	if($mybb->usergroup['canmoderategames'] == 0 && $mybb->usergroup['cancp'] == 0 && $mybb->user['uid'] != $score['uid'])
	{
		$message = $lang->edit_nopermission;
		eval("\$error = \"".$templates->get("arcade_edit_error")."\";");
		output_page($error);
		exit;
	}

	// The length of the comment is too long
	if(my_strlen($mybb->input['comment']) > $mybb->settings['arcade_maxcommentlength'])
	{
		$show_back = 1;
		$message = $lang->sprintf($lang->edit_toolong, $mybb->settings['arcade_maxcommentlength']);
		eval("\$error = \"".$templates->get("arcade_edit_error")."\";");
		output_page($error);
		exit;
	}

	// Build array of score data.
	$updatedcomment = array(
		"comment" => $db->escape_string($mybb->input['comment'])
	);

	$db->update_query("arcadescores", $updatedcomment, "sid='".intval($mybb->input['sid'])."'");

	$plugins->run_hooks("arcade_do_edit_end");

	eval("\$edited = \"".$templates->get("arcade_edited")."\";");
	output_page($edited);
}

// Edit a score comment
if($mybb->input['action'] == "edit")
{
	$time = TIME_NOW;

	$query = $db->simple_select("arcadescores", "*", "sid='".intval($mybb->input['sid'])."'");
	$score = $db->fetch_array($query);

	$plugins->run_hooks("arcade_edit_start");
	
	if(!$score['sid'])
	{
		$message = $lang->error_invalidscore;
		eval("\$error = \"".$templates->get("arcade_edit_error")."\";");
		output_page($error);
		exit;
	}

	if($mybb->usergroup['canmoderategames'] == 0 && $mybb->usergroup['cancp'] == 0 && $mybb->user['uid'] != $score['uid'])
	{
		$message = $lang->edit_nopermission;
		eval("\$error = \"".$templates->get("arcade_edit_error")."\";");
		output_page($error);
		exit;
	}

	if($mybb->usergroup['canmoderategames'] == 0 && $mybb->usergroup['cancp'] == 0 && ($mybb->settings['arcade_editcomment'] != 0 && $score['dateline'] < ($time-($mybb->settings['arcade_editcomment']*60))))
	{
		$message = $lang->sprintf($lang->error_timelimit, $mybb->settings['arcade_editcomment']);
		eval("\$error = \"".$templates->get("arcade_edit_error")."\";");
		output_page($error);
		exit;
	}

	$plugins->run_hooks("arcade_edit_end");
	
	eval("\$edit = \"".$templates->get("arcade_edit")."\";");
	output_page($edit);
}

// Adding to favorites
if($mybb->input['action'] == "addfavorite")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	$plugins->run_hooks("arcade_addfavorite_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	$gid = intval($mybb->input['gid']);
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

	$query = $db->simple_select("arcadefavorites", "*", "gid='".intval($game['gid'])."' AND uid='".intval($mybb->user['uid'])."'", array('limit' => 1));
	$favorite = $db->fetch_array($query);
	if(!$favorite['gid'])
	{
		$insert_array = array(
			'uid' => intval($mybb->user['uid']),
			'gid' => intval($game['gid']),
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
	verify_post_check($mybb->input['my_post_key']);

	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	$plugins->run_hooks("arcade_removefavorite_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	$gid = intval($mybb->input['gid']);
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

	$db->delete_query("arcadefavorites", "gid='".$game['gid']."' AND uid='".intval($mybb->user['uid'])."'");
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
			$db->delete_query("arcadefavorites", "gid='{$delete_fav['gid']}' AND uid='".intval($mybb->user['uid'])."'");
		}
	}

	// Pick the sort order.
	if(!isset($mybb->input['order']) && !empty($mybb->settings['gamesorder']))
	{
		$mybb->input['order'] = $mybb->settings['gamesorder'];
	}

	$mybb->input['order'] = htmlspecialchars($mybb->input['order']);

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

	$mybb->input['sortby'] = htmlspecialchars($mybb->input['sortby']);

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
	$perpage = intval($mybb->settings['gamesperpage']);
	$page = intval($mybb->input['page']);

	$query = $db->simple_select("arcadefavorites", "COUNT(gid) AS page_count", "uid='".intval($mybb->user['uid'])."'");
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

	// Fetch the games which will be displayed on this page
	$query = $db->query("
		SELECT f.*, g.*, u.username AS user_name, s.score AS your_score, c.score AS champscore, c.uid AS champuid, c.username AS champusername, r.uid AS rated
		FROM ".TABLE_PREFIX."arcadefavorites f
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=f.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (f.gid=s.gid AND s.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcadechampions c ON (f.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcaderatings r ON (g.gid=r.gid AND r.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."users u ON (g.lastplayeduid=u.uid)
		WHERE g.active='1' AND f.uid='".$mybb->user['uid']."'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($game = $db->fetch_array($query))
	{
		$game['name'] = htmlspecialchars_uni($game['name']);
		$game['description'] = htmlspecialchars_uni($game['description']);

		if($game['lastplayeduid'])
		{
			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$game['lastplayeduid']}";
			}
			else
			{
				$profilelink = get_profile_link($game['lastplayeduid']);
			}

			$playedby = "<a href=\"{$profilelink}\">{$game['user_name']}</a>";
			$lastplayedby = $lang->sprintf($lang->by_user, $playedby);
		}
		else
		{
			$lastplayedby = "";
		}

		if($game['lastplayed'] && $game['lastplayeduid'])
		{
			$lastplayed = my_date($mybb->settings['dateformat'], $game['lastplayed']).", ".my_date($mybb->settings['timeformat'], $game['lastplayed']);
		}
		else
		{
			$lastplayed = $lang->na;
		}

		if($game['champscore'])
		{
			$game['champscore'] = my_number_format(floatval($game['champscore']));
			$champion = $lang->sprintf($lang->champion_with_score, $game['champscore']);
		}
		else
		{
			$champion = $lang->sprintf($lang->champion_with_score, $lang->na);
		}

		if($game['champusername'])
		{
			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$game['champuid']}";
			}
			else
			{
				$profilelink = get_profile_link($game['champuid']);
			}

			$champusername = "<a href=\"{$profilelink}\">{$game['champusername']}</a>";
		}
		else
		{
			$champusername = $lang->na;
		}

		if($game['your_score'])
		{
			$game['your_score'] = my_number_format(floatval($game['your_score']));
		}
		else
		{
			$game['your_score'] = $lang->na;
		}

		if($mybb->user['uid'] != 0 && $mybb->usergroup['canplayarcade'] == 1)
		{
			$your_score = "<li>{$lang->your_high_score} <strong>{$game['your_score']}</strong></li>";
		}

		if($game['tournamentselect'] == 1 && $mybb->usergroup['cancreatetournaments'] == 1)
		{
			$tournament = "<li><a href=\"tournaments.php?action=create&gid={$game['gid']}\">{$lang->create_tournament}</a></li>";
		}
		else
		{
			$tournament = "";
		}

		// Is this a new game?
		$time = TIME_NOW-($mybb->settings['arcade_newgame']*60*60*24);

		if($game['dateline'] >= $time)
		{
			$new = " <img src=\"images/arcade/new.png\" alt=\"{$lang->new}\" />";
		}
		else
		{
			$new = "";
		}

		$add_remove_favorite = "<li><a href=\"arcade.php?action=removefavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->remove_from_favorites}</a></li>";

		// Work out the rating for this game.
		$rating = '';
		if($mybb->settings['arcade_ratings'] != 0)
		{
			if($game['numratings'] <= 0)
			{
				$game['width'] = 0;
				$game['averagerating'] = 0;
				$game['numratings'] = 0;
			}
			else
			{
				$game['averagerating'] = floatval(round($game['totalratings']/$game['numratings'], 2));
				$game['width'] = intval(round($game['averagerating']))*20;
				$game['numratings'] = intval($game['numratings']);
			}

			$not_rated = '';
			if(!$game['rated'])
			{
				$not_rated = ' star_rating_notrated';
			}

			$li = "<li>";
			$ratingvotesav = $lang->sprintf($lang->rating_average, $game['numratings'], $game['averagerating']);
			eval("\$rategame = \"".$templates->get("arcade_rating")."\";");
		}

		$plugins->run_hooks("arcade_game");

		$alt_bg = alt_trow();
		eval("\$game_bit .= \"".$templates->get("arcade_gamebit")."\";");
	}

	if(!$game_bit)
	{
		eval("\$game_bit = \"".$templates->get("arcade_no_games")."\";");
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
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("arcade_do_settings_start");

	$update_array = array(
		"gamesperpage" => intval($mybb->input['gamesperpage']),
		"scoresperpage" => intval($mybb->input['scoresperpage']),
		"gamessortby" => $db->escape_string($mybb->input['gamessortby']),
		"gamesorder" => $db->escape_string($mybb->input['gamesorder']),
		"whosonlinearcade" => intval($mybb->input['whosonlinearcade']),
		"champdisplaypostbit" => intval($mybb->input['champdisplaypostbit']),
		"tournamentnotify" => intval($mybb->input['tournamentnotify']),
		"champnotify" => intval($mybb->input['champnotify'])
	);

	$db->update_query("users", $update_array, "uid='".intval($mybb->user['uid'])."'");

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

	if($user['whosonlinearcade'] == 1)
	{
		$whosonlinearcadecheck = "checked=\"checked\"";
	}
	else
	{
		$whosonlinearcadecheck = "";
	}

	if($user['champdisplaypostbit'] == 1)
	{
		$champdisplaypostbitcheck = "checked=\"checked\"";
	}
	else
	{
		$champdisplaypostbitcheck = "";
	}

	$sortbysel[$user['gamessortby']] = 'selected="selected"';
	$ordersel[$user['gamesorder']] = 'selected="selected"';

	if($mybb->settings['gamesperpageoptions'])
	{
		$explodedgames = explode(",", $mybb->settings['gamesperpageoptions']);
		$gamesoptions = '';
		if(is_array($explodedgames))
		{
			foreach($explodedgames as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if($user['gamesperpage'] == $val)
				{
					$selected = "selected=\"selected\"";
				}
				$gamesoptions .= "<option value=\"$val\" $selected>".$lang->sprintf($lang->games_option, $val)."</option>\n";
			}
		}
		eval("\$gameperpageoptions = \"".$templates->get("arcade_settings_gamesselect")."\";");
	}
	if($mybb->settings['scoresperpageoptions'])
	{
		$explodedscores = explode(",", $mybb->settings['scoresperpageoptions']);
		$scoreoptions = '';
		if(is_array($explodedscores))
		{
			foreach($explodedscores as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if($user['scoresperpage'] == $val)
				{
					$selected = "selected=\"selected\"";
				}
				$scoreoptions .= "<option value=\"$val\" $selected>".$lang->sprintf($lang->score_option, $val)."</option>\n";
			}
		}
		eval("\$scoreperpageoptions = \"".$templates->get("arcade_settings_scoreselect")."\";");
	}

	if($mybb->settings['arcade_whosonline'] != 0)
	{
		eval("\$whosonlinedisplay = \"".$templates->get("arcade_settings_whosonline")."\";");
	}

	if($mybb->settings['arcade_postbit'] != 0)
	{
		eval("\$champdisplaypostbit = \"".$templates->get("arcade_settings_champpostbit")."\";");
	}

	if($mybb->usergroup['canjointournaments'] == 1)
	{
		eval("\$tournamentnotifydisplay = \"".$templates->get("arcade_settings_tournamentnotify")."\";");
	}

	$plugins->run_hooks("arcade_settings_end");

	eval("\$settings = \"".$templates->get("arcade_settings")."\";");
	output_page($settings);
}

// Stats page
if($mybb->input['action'] == "stats")
{
	$uid = intval($mybb->input['uid']);
	if(!$mybb->input['uid'])
	{
		$uid = intval($mybb->user['uid']);
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

	$lang->arcade_stats_for = $lang->sprintf($lang->arcade_stats_for, $user['username']);
	$lang->player_details = $lang->sprintf($lang->player_details, $user['username']);

	if($mybb->input['uid'])
	{
		$userinput = "<input type=\"hidden\" name=\"uid\" value=\"{$mybb->input['uid']}\" />";
	}
	else
	{
		$userinput = "";
	}

	$mybb->input['order'] = htmlspecialchars($mybb->input['order']);

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
	$mybb->input['sortby'] = htmlspecialchars($mybb->input['sortby']);

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
	if(!$mybb->settings['statsperpage'])
	{
		$mybb->settings['statsperpage'] = 10;
	}

	$perpage = intval($mybb->settings['statsperpage']);
	$page = intval($mybb->input['page']);

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
		if($mybb->input['uid'])
		{
			$page_url = "arcade.php?action=stats&uid={$uid}&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}";
		}
		else
		{
			$page_url = "arcade.php?action=stats&sortby={$mybb->input['sortby']}&order={$mybb->input['order']}";
		}
	}
	elseif($mybb->input['uid'])
	{
		$page_url = "arcade.php?action=stats&uid={$uid}";
	}
	else
	{
		$page_url = "arcade.php?action=stats";
	}

	$multipage = multipage($page_count, $perpage, $page, $page_url);

	// Fetch the games and scores which will be displayed on this page
	$query = $db->query("
		SELECT g.*, s.uid, s.score, s.dateline, COUNT(c.gid) AS totalscores, ch.uid AS firstplace
		FROM ".TABLE_PREFIX."arcadegames g
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (g.gid=s.gid AND s.uid='{$uid}')
		LEFT JOIN ".TABLE_PREFIX."arcadescores c ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadechampions ch ON (g.gid=ch.gid AND ch.uid='{$uid}')
		WHERE g.active='1'{$cat_sql_game}
		GROUP BY g.gid
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
			$score['score'] = my_number_format(floatval($score['score']));
		}
		else
		{
			$score['score'] = $lang->na;
		}

		if($score['dateline'])
		{
			$dateline = my_date($mybb->settings['dateformat'], $score['dateline']).", ".my_date($mybb->settings['timeformat'], $score['dateline']);
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
			SELECT p.tid, p.uid, t.champion
			FROM ".TABLE_PREFIX."arcadetournamentplayers p
			LEFT JOIN ".TABLE_PREFIX."arcadetournaments t ON (p.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (t.gid=g.gid)
			WHERE p.uid='{$user['uid']}' AND p.round='1' AND g.active='1'{$cat_sql_game}
		");
		$tournamentswon = 0;
		$tournamentsentered = $db->num_rows($query2);
		while($tournaments = $db->fetch_array($query2))
		{
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

	$mybb->input['order'] = htmlspecialchars($mybb->input['order']);

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
	$mybb->input['sortby'] = htmlspecialchars($mybb->input['sortby']);

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

	$perpage_selected[$mybb->input['perpage']] = 'selected="selected"';

	// Figure out if we need to display multiple pages.
	$mybb->input['perpage'] = intval($mybb->input['perpage']);
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$perpage = $mybb->input['perpage'];
	}
	else
	{
		$perpage = $mybb->input['perpage'] = intval($mybb->settings['scoresperpage']);	
	}

	$page = intval($mybb->input['page']);

	$query = $db->query("
		SELECT COUNT(c.cid) AS page_count, g.active, g.cid
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
	$query = $db->query("
		SELECT c.*, g.name, g.active, s.comment
		FROM ".TABLE_PREFIX."arcadechampions c
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (s.gid=c.gid AND s.uid=c.uid)
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=c.gid)
		WHERE g.active='1'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($champ = $db->fetch_array($query))
	{
		$champ['name'] = htmlspecialchars_uni($champ['name']);
		$champ['score'] = my_number_format(floatval($champ['score']));

		$dateline = my_date($mybb->settings['dateformat'], $champ['dateline']).", ".my_date($mybb->settings['timeformat'], $champ['dateline']);

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

	$mybb->input['order'] = htmlspecialchars($mybb->input['order']);

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
	$mybb->input['sortby'] = htmlspecialchars($mybb->input['sortby']);

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

	$perpage_selected[$mybb->input['perpage']] = 'selected="selected"';

	// Figure out if we need to display multiple pages.
	$mybb->input['perpage'] = intval($mybb->input['perpage']);
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$perpage = $mybb->input['perpage'];
	}
	else
	{
		$perpage = $mybb->input['perpage'] = intval($mybb->settings['scoresperpage']);	
	}

	$page = intval($mybb->input['page']);

	$query = $db->query("
		SELECT COUNT(s.sid) AS page_count, g.active, g.cid
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
	$query = $db->query("
		SELECT s.*, g.name, g.active
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=s.gid)
		WHERE g.active='1'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($score = $db->fetch_array($query))
	{
		$score['name'] = htmlspecialchars_uni($score['name']);
		$score['score'] = my_number_format(floatval($score['score']));

		$dateline = my_date($mybb->settings['dateformat'], $score['dateline']).", ".my_date($mybb->settings['timeformat'], $score['dateline']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$score['uid']}";
		}
		else
		{
			$profilelink = get_profile_link($score['uid']);
		}

		// Does the current user have permission to delete this score? Show delete link
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			$delete_link = "[<a href=\"arcade.php?action=delete&amp;sid={$score['sid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"if(confirm(&quot;{$lang->delete_score_confirm}&quot;))window.location=this.href.replace('action=delete','action=delete');return false;\">{$lang->delete}</a>]";
		}
		else
		{
			$delete_link = '';
		}

		// Does the current user have permission to edit this score's comment? Show edit link
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			$edit_link = "[<a href=\"javascript:MyBB.popupWindow('arcade.php?action=edit&amp;sid={$score['sid']}', 'editcomment', '400', '300') \">{$lang->edit}</a>]";
		}
		else
		{
			$edit_link = '';
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
		if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
		{
			$ipaddressbit = "<td class=\"{$alt_bg}\" align=\"center\">{$score['ipaddress']}</td>";
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
	if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['canmoderategames'] == 1)
	{
		$ipaddresscol = "<td class=\"tcat\" width=\"10%\" align=\"center\"><strong>{$lang->ip_address}</strong></td>";
		$colspan = "6";
	}
	else
	{
		$colspan = "5";
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
		$query = $db->simple_select("searchlog", "*", "uid='".intval($mybb->user['uid'])."' AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
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
		"cid" => $mybb->input['cid'],
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
		"uid" => intval($mybb->user['uid']),
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_string($session->ipaddress),
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
	$query = $db->simple_select("searchlog", "*", "sid='{$sid}' AND uid='".intval($mybb->user['uid'])."'");
	$search = $db->fetch_array($query);

	if(!$search['sid'])
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("arcade_results_start");

	add_breadcrumb($lang->search_results, "arcade.php?action=results&{$sid}");

	// Pick the sort order.
	if(!isset($mybb->input['order']) && !empty($mybb->settings['gamesorder']))
	{
		$mybb->input['order'] = $mybb->settings['gamesorder'];
	}

	$mybb->input['order'] = htmlspecialchars($mybb->input['order']);

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

	$mybb->input['sortby'] = htmlspecialchars($mybb->input['sortby']);

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
	$perpage = intval($mybb->settings['gamesperpage']);
	$page = intval($mybb->input['page']);
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
	$highlight = "";
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

	// Fetch the games which will be displayed on this page
	$query = $db->query("
		SELECT g.*, u.username AS user_name, s.score AS your_score, f.gid AS favorite, c.score AS champscore, c.uid AS champuid, c.username AS champusername, r.uid AS rated
		FROM ".TABLE_PREFIX."arcadegames g
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (g.gid=s.gid AND s.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcadechampions c ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadefavorites f ON (g.gid=f.gid AND f.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcaderatings r ON (g.gid=r.gid AND r.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."users u ON (g.lastplayeduid=u.uid)
		WHERE g.gid IN(".$db->escape_string($search['querycache']).") AND g.active='1'{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($game = $db->fetch_array($query))
	{
		$game['name'] = htmlspecialchars_uni($game['name']);
		$game['description'] = htmlspecialchars_uni($game['description']);

		if($game['lastplayeduid'])
		{
			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$game['lastplayeduid']}";
			}
			else
			{
				$profilelink = get_profile_link($game['lastplayeduid']);
			}

			$playedby = "<a href=\"{$profilelink}\">{$game['user_name']}</a>";
			$lastplayedby = $lang->sprintf($lang->by_user, $playedby);
		}
		else
		{
			$lastplayedby = "";
		}

		if($game['lastplayed'])
		{
			$lastplayed = my_date($mybb->settings['dateformat'], $game['lastplayed']).", ".my_date($mybb->settings['timeformat'], $game['lastplayed']);
		}
		else
		{
			$lastplayed = $lang->na;
		}

		if($game['champscore'])
		{
			$game['champscore'] = my_number_format(floatval($game['champscore']));
			$champion = $lang->sprintf($lang->champion_with_score, $game['champscore']);
		}
		else
		{
			$champion = $lang->sprintf($lang->champion_with_score, $lang->na);
		}

		if($game['champusername'])
		{
			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$game['champuid']}";
			}
			else
			{
				$profilelink = get_profile_link($game['champuid']);
			}

			$champusername = "<a href=\"{$profilelink}\">{$game['champusername']}</a>";
		}
		else
		{
			$champusername = $lang->na;
		}

		if($game['your_score'])
		{
			$game['your_score'] = my_number_format(floatval($game['your_score']));
		}
		else
		{
			$game['your_score'] = $lang->na;
		}

		if($mybb->user['uid'] != 0 && $mybb->usergroup['canplayarcade'] == 1)
		{
			$your_score = "<li>{$lang->your_high_score} <strong>{$game['your_score']}</strong></li>";
		}

		if($game['tournamentselect'] == 1 && $mybb->usergroup['cancreatetournaments'] == 1)
		{
			$tournament = "<li><a href=\"tournaments.php?action=create&gid={$game['gid']}\">{$lang->create_tournament}</a></li>";
		}
		else
		{
			$tournament = "";
		}

		// Is this a new game?
		$time = TIME_NOW-($mybb->settings['arcade_newgame']*60*60*24);

		if($game['dateline'] >= $time)
		{
			$new = " <img src=\"images/arcade/new.png\" alt=\"{$lang->new}\" />";
		}
		else
		{
			$new = "";
		}

		// Favorite check
		if($mybb->user['uid'] != 0)
		{
			if($game['favorite'])
			{
				$add_remove_favorite = "<li><a href=\"arcade.php?action=removefavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->remove_from_favorites}</a></li>";
			}
			else
			{
				$add_remove_favorite = "<li><a href=\"arcade.php?action=addfavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->add_to_favorites}</a></li>";
			}
		}
		else
		{
			$add_remove_favorite = '';
		}

		// Work out the rating for this game.
		$rating = '';
		if($mybb->settings['arcade_ratings'] != 0)
		{
			if($game['numratings'] <= 0)
			{
				$game['width'] = 0;
				$game['averagerating'] = 0;
				$game['numratings'] = 0;
			}
			else
			{
				$game['averagerating'] = floatval(round($game['totalratings']/$game['numratings'], 2));
				$game['width'] = intval(round($game['averagerating']))*20;
				$game['numratings'] = intval($game['numratings']);
			}

			$not_rated = '';
			if(!$game['rated'])
			{
				$not_rated = ' star_rating_notrated';
			}

			$li = "<li>";
			$ratingvotesav = $lang->sprintf($lang->rating_average, $game['numratings'], $game['averagerating']);
			eval("\$rategame = \"".$templates->get("arcade_rating")."\";");
		}

		$plugins->run_hooks("arcade_game");

		$alt_bg = alt_trow();
		eval("\$game_bit .= \"".$templates->get("arcade_gamebit")."\";");
	}

	$plugins->run_hooks("arcade_results_end");

	eval("\$results = \"".$templates->get("arcade_search_results")."\";");
	output_page($results);
}

// Arcade home page
if(!$mybb->input['action'])
{
	if($mybb->input['cid'])
	{
		$cid = intval($mybb->input['cid']);

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

		$catinput = "<input type=\"hidden\" name=\"cid\" value=\"{$cid}\" />";
		$where_cat = " AND g.cid='{$cid}'";
	}
	else
	{
		$where_cat ="";
		$catinput = "";
	}

	// Stats box
	if($mybb->settings['arcade_stats'] == 1)
	{
		// Newest Games
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
			$newestgames = "<em>{$lang->no_games}</em>";
		}

		// Most played games
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
			$mostplayedgames = "<em>{$lang->no_games}</em>";
		}

		// Newest Champions
		$query3 = $db->query("
			SELECT c.*, g.active, g.name, g.smallimage
			FROM ".TABLE_PREFIX."arcadechampions c
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
			WHERE g.active ='1'{$cat_sql_game}
			ORDER BY c.dateline DESC
			LIMIT {$mybb->settings['arcade_stats_newchamps']}
		");
		while($score = $db->fetch_array($query3))
		{
			$score['name'] = htmlspecialchars_uni($score['name']);
			$score['score'] = my_number_format(floatval($score['score']));

			$dateline = my_date($mybb->settings['dateformat'], $score['dateline']).", ".my_date($mybb->settings['timeformat'], $score['dateline']);
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

			$lang->scored_playing = $lang->sprintf($lang->scored_playing, $score['score']);
			eval("\$newestchamps .= \"".$templates->get("arcade_statistics_scorebit")."\";");
		}
		if(!$newestchamps)
		{
			$newestchamps = "<em>{$lang->no_champs}</em>";
		}

		// Latest Scores
		$query4 = $db->query("
			SELECT s.*, g.active, g.name, g.smallimage
			FROM ".TABLE_PREFIX."arcadescores s
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
			WHERE g.active ='1'{$cat_sql_game}
			ORDER BY s.dateline DESC
			LIMIT {$mybb->settings['arcade_stats_newscores']}
		");
		while($score = $db->fetch_array($query4))
		{
			$score['name'] = htmlspecialchars_uni($score['name']);
			$score['score'] = my_number_format(floatval($score['score']));

			$dateline = my_date($mybb->settings['dateformat'], $score['dateline']).", ".my_date($mybb->settings['timeformat'], $score['dateline']);
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

			$lang->scored_playing = $lang->sprintf($lang->scored_playing, $score['score']);
			eval("\$latestscores .= \"".$templates->get("arcade_statistics_scorebit")."\";");
		}
		if(!$latestscores)
		{
			$latestscores = "<em>{$lang->no_scores}</em>";
		}

		// Best Players
		if($mybb->settings['arcade_stats_bestplayers'] == 1)
		{
			$rank = 0;

			$query5 = $db->query("
				SELECT c.*, u.avatar, COUNT(c.gid) AS champs
				FROM ".TABLE_PREFIX."arcadechampions c
				LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=c.gid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=c.uid)
				WHERE g.active ='1'{$cat_sql_game}
				GROUP BY c.uid
				ORDER BY champs DESC
				LIMIT 3
			");
			while($champ = $db->fetch_array($query5))
			{
				$rank++;
				$bestplayer_rank_lang = "bestplayers_place_".$rank;
				$bestplayer_rank_lang = $lang->$bestplayer_rank_lang;

				if($mybb->settings['arcade_stats_avatar'] == 1)
				{
					if($champ['avatar'])
					{
						$best_player_avatar = "<img src=\"".$champ['avatar']."\" alt\"\" width=\"100\" height=\"100\" /><br />";
					}
					else
					{
						$best_player_avatar = "<img src=\"images/default_avatar.gif\" alt\"\" width=\"100\" height=\"100\" /><br />";
					}
				}

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
				$bestplayers_bit = "<em>{$lang->no_champs}</em>";
			}

			eval("\$bestplayers = \"".$templates->get("arcade_statistics_bestplayers")."\";");
		}

		eval("\$stats = \"".$templates->get("arcade_statistics")."\";");
	}

	// Category box
	$categorycount = 0;
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

		if(is_file($category['image']))
		{
			$image = "<img src=\"{$category['image']}\" alt=\"{$category['name']}\">&nbsp;";
		}
		else
		{
			$image = "";
		}

		eval("\$categorybit .= \"".$templates->get('arcade_category_bit')."\";");
		++$categorycount;
	}
	if($categorycount > 0)
	{
		eval("\$categories = \"".$templates->get('arcade_categories')."\";");
	}

	// Tournaments box
	if($mybb->settings['enabletournaments'] == 1 && $mybb->usergroup['canviewtournaments'] == 1)
	{
		$tournaments_stats = $cache->read("tournaments_stats");
		$tournaments_stats['numwaitingtournaments'] = my_number_format($tournaments_stats['numwaitingtournaments']);
		$tournaments_stats['numrunningtournaments'] = my_number_format($tournaments_stats['numrunningtournaments']);
		$tournaments_stats['numfinishedtournaments'] = my_number_format($tournaments_stats['numfinishedtournaments']);

		$lang->tournaments_running = $lang->sprintf($lang->tournaments_running, $tournaments_stats['numrunningtournaments']);
		$lang->tournaments_finished = $lang->sprintf($lang->tournaments_finished, $tournaments_stats['numfinishedtournaments']);
		$lang->tournaments_waiting = $lang->sprintf($lang->tournaments_waiting, $tournaments_stats['numwaitingtournaments']);

		if($mybb->usergroup['canjointournaments'] == 1)
		{
			$numgames = 0;
			$enrolledin = 0;

			$query = $db->query("
				SELECT t.*, p.uid, g.active, g.name, g.smallimage
				FROM ".TABLE_PREFIX."arcadetournamentplayers p
				LEFT JOIN ".TABLE_PREFIX."arcadetournaments t ON (p.tid=t.tid AND t.round=p.round)
				LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (t.gid=g.gid)
				WHERE p.uid='{$mybb->user['uid']}' AND t.status IN(1,2) AND p.status !='4' AND g.active='1'{$cat_sql_game}
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

			if($mybb->usergroup['cancreatetournaments'] == 1)
			{
				eval("\$tournamentcreate .= \"".$templates->get('arcade_tournaments_create')."\";");
			}

			$tournamentswaiting = "<a href=\"tournaments.php?action=waiting\">{$lang->tournaments_waiting}</a>";
			eval("\$tournamentmember .= \"".$templates->get('arcade_tournaments_user')."\";");
		}
		else
		{
			$tournamentswaiting = "{$lang->tournaments_waiting}";
		}

		eval("\$tournaments = \"".$templates->get('arcade_tournaments')."\";");
	}

	// Search box
	if($mybb->settings['arcade_searching'] == 1 && $mybb->usergroup['cansearchgames'] == 1)
	{
		$searchcategorycount = 0;
		$query = $db->simple_select("arcadecategories", "*", "active='1'{$cat_sql}", array('order_by' => 'name', 'order_dir' => 'asc'));
		while($category = $db->fetch_array($query))
		{
			$name = htmlspecialchars_uni($category['name']);
			$categoryoptions .= "<option value=\"{$category['cid']}\">{$name}</option>\n";
			++$searchcategorycount;
		}

		if($searchcategorycount > 0)
		{
			eval("\$categorysearch .= \"".$templates->get("arcade_search_catagory")."\";");
		}

		eval("\$search = \"".$templates->get('arcade_search')."\";");
	}

	// Pick the sort order.
	if(!isset($mybb->input['order']) && !empty($mybb->settings['gamesorder']))
	{
		$mybb->input['order'] = $mybb->settings['gamesorder'];
	}

	$mybb->input['order'] = htmlspecialchars($mybb->input['order']);

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

	$mybb->input['sortby'] = htmlspecialchars($mybb->input['sortby']);

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
	$perpage = intval($mybb->settings['gamesperpage']);
	$page = intval($mybb->input['page']);

	if($mybb->input['cid'])
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

	if($mybb->input['cid'] > 0)
	{
		$cid = intval($mybb->input['cid']);
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

	// Fetch the games which will be displayed on this page
	$query = $db->query("
		SELECT g.*, u.username AS user_name, s.score AS your_score, f.gid AS favorite, c.score AS champscore, c.uid AS champuid, c.username AS champusername, r.uid AS rated
		FROM ".TABLE_PREFIX."arcadegames g
		LEFT JOIN ".TABLE_PREFIX."arcadescores s ON (g.gid=s.gid AND s.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcadechampions c ON (g.gid=c.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadefavorites f ON (g.gid=f.gid AND f.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."arcaderatings r ON (g.gid=r.gid AND r.uid='{$mybb->user['uid']}')
		LEFT JOIN ".TABLE_PREFIX."users u ON (g.lastplayeduid=u.uid)
		WHERE g.active='1'{$where_cat}{$cat_sql_game}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($game = $db->fetch_array($query))
	{
		$game['name'] = htmlspecialchars_uni($game['name']);
		$game['description'] = htmlspecialchars_uni($game['description']);

		if($game['lastplayeduid'])
		{
			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$game['lastplayeduid']}";
			}
			else
			{
				$profilelink = get_profile_link($game['lastplayeduid']);
			}

			$playedby = "<a href=\"{$profilelink}\">{$game['user_name']}</a>";
			$lastplayedby = $lang->sprintf($lang->by_user, $playedby);
		}
		else
		{
			$lastplayedby = "";
		}

		if($game['lastplayed'])
		{
			$lastplayed = my_date($mybb->settings['dateformat'], $game['lastplayed']).", ".my_date($mybb->settings['timeformat'], $game['lastplayed']);
		}
		else
		{
			$lastplayed = $lang->na;
		}

		if($game['champscore'])
		{
			$game['champscore'] = my_number_format(floatval($game['champscore']));
			$champion = $lang->sprintf($lang->champion_with_score, $game['champscore']);
		}
		else
		{
			$champion = $lang->sprintf($lang->champion_with_score, $lang->na);
		}

		if($game['champusername'])
		{
			if($mybb->usergroup['canviewgamestats'] == 1)
			{
				$profilelink = "arcade.php?action=stats&uid={$game['champuid']}";
			}
			else
			{
				$profilelink = get_profile_link($game['champuid']);
			}

			$champusername = "<a href=\"{$profilelink}\">{$game['champusername']}</a>";
		}
		else
		{
			$champusername = $lang->na;
		}

		if($game['your_score'])
		{
			$game['your_score'] = my_number_format(floatval($game['your_score']));
		}
		else
		{
			$game['your_score'] = $lang->na;
		}

		if($mybb->user['uid'] != 0 && $mybb->usergroup['canplayarcade'] == 1)
		{
			$your_score = "<li>{$lang->your_high_score} <strong>{$game['your_score']}</strong></li>";
		}

		if($game['tournamentselect'] == 1 && $mybb->usergroup['cancreatetournaments'] == 1)
		{
			$tournament = "<li><a href=\"tournaments.php?action=create&gid={$game['gid']}\">{$lang->create_tournament}</a></li>";
		}
		else
		{
			$tournament = "";
		}

		// Is this a new game?
		$time = TIME_NOW-($mybb->settings['arcade_newgame']*60*60*24);

		if($game['dateline'] >= $time)
		{
			$new = " <img src=\"images/arcade/new.png\" alt=\"{$lang->new}\" />";
		}
		else
		{
			$new = "";
		}

		// Favorite check
		if($mybb->user['uid'] != 0)
		{
			if($game['favorite'])
			{
				$add_remove_favorite = "<li><a href=\"arcade.php?action=removefavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->remove_from_favorites}</a></li>";
			}
			else
			{
				$add_remove_favorite = "<li><a href=\"arcade.php?action=addfavorite&gid={$game['gid']}&my_post_key={$mybb->post_code}\">{$lang->add_to_favorites}</a></li>";
			}
		}
		else
		{
			$add_remove_favorite = '';
		}

		// Work out the rating for this game.
		$rating = '';
		if($mybb->settings['arcade_ratings'] != 0)
		{
			if($game['numratings'] <= 0)
			{
				$game['width'] = 0;
				$game['averagerating'] = 0;
				$game['numratings'] = 0;
			}
			else
			{
				$game['averagerating'] = floatval(round($game['totalratings']/$game['numratings'], 2));
				$game['width'] = intval(round($game['averagerating']))*20;
				$game['numratings'] = intval($game['numratings']);
			}

			$not_rated = '';
			if(!$game['rated'])
			{
				$not_rated = ' star_rating_notrated';
			}

			$li = "<li>";
			$ratingvotesav = $lang->sprintf($lang->rating_average, $game['numratings'], $game['averagerating']);
			eval("\$rategame = \"".$templates->get("arcade_rating")."\";");
		}

		$plugins->run_hooks("arcade_game");

		$alt_bg = alt_trow();
		eval("\$game_bit .= \"".$templates->get("arcade_gamebit")."\";");
	}

	if(!$game_bit)
	{
		eval("\$game_bit = \"".$templates->get("arcade_no_games")."\";");
	}

	$plugins->run_hooks("arcade_end");

	eval("\$arcadehome = \"".$templates->get("arcade")."\";");
	output_page($arcadehome);
}

?>