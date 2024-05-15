<?php
/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

/**
 * Get the game of a game id.
 *
 * @param int $gid The game id of the arcade game.
 * @param boolean $recache Whether or not to recache the game.
 * @return string The database row of the game.
 */
function get_game($gid, $recache = false)
{
	global $db;
	static $game_cache;

	$gid = (int)$gid;

	if(isset($game_cache[$gid]) && !$recache)
	{
		return $game_cache[$gid];
	}
	else
	{
		$query = $db->simple_select("arcadegames", "*", "gid='{$gid}'");
		$game = $db->fetch_array($query);

		if($game)
		{
			$game_cache[$gid] = $game;
			return $game;
		}
		else
		{
			$game_cache[$gid] = false;
			return false;
		}
	}
}

/**
 * Get the tournament of a tournament id.
 *
 * @param int $tid The tournament ID.
 * @param boolean $recache Whether or not to recache the tournament.
 * @return string The database row of the tournament.
 */
function get_tournament($tid, $recache = false)
{
	global $db;
	static $tournament_cache;

	$tid = (int)$tid;

	if(isset($tournament_cache[$tid]) && !$recache)
	{
		return $tournament_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("arcadetournaments", "*", "tid='{$tid}'");
		$tournament = $db->fetch_array($query);

		if($tournament)
		{
			$tournament_cache[$tid] = $tournament;
			return $tournament;
		}
		else
		{
			$tournament_cache[$tid] = false;
			return false;
		}
	}
}

/**
 * Log any actions in the arcade.
 *
 * @param array $data The data of the action.
 * @param string $action The message to enter for the action performed.
 */
function log_arcade_action($data, $action="")
{
	global $mybb, $db, $session;
	$mybb->binary_fields["arcadelogs"] = array('ipaddress' => true);

	$game = 0;
	if(isset($data['gid']))
	{
		$game = (int)$data['gid'];
		unset($data['gid']);
	}

	$tournament = 0;
	if(isset($data['tid']))
	{
		$tournament = (int)$data['tid'];
		unset($data['tid']);
	}

	// Any remaining extra data - we serialize and insert in to its own column
	if(is_array($data))
	{
		$data = serialize($data);
	}

	$sql_array = array(
		"uid" => (int)$mybb->user['uid'],
		"dateline" => TIME_NOW,
		"gid" => $game,
		"tid" => $tournament,
		"action" => $db->escape_string($action),
		"data" => $db->escape_string($data),
		"ipaddress" => $db->escape_binary($session->packedip)
	);
	$db->insert_query("arcadelogs", $sql_array);
}

/**
 * Update Champion of a game.
 *
 * @param int $gid The game ID.
 */
function update_champion($gid)
{
	global $db;

	$query = $db->simple_select("arcadegames", "gid, sortby", "gid='".(int)$gid."'");
	$game = $db->fetch_array($query);

	// Fetch the highest score for this game
	$query2 = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."arcadescores
		WHERE gid='{$game['gid']}'
		ORDER BY score {$game['sortby']}, dateline ASC
		LIMIT 0, 1
	");
	$highestscore = $db->fetch_array($query2);

	// If no highest score exists, delete champion. Otherwise update
	if(!$highestscore['sid'])
	{
		$db->delete_query("arcadechampions", "gid='{$game['gid']}'");
	}
	else
	{
		$updated_champion = array(
			"uid" => (int)$highestscore['uid'],
			"username" => $db->escape_string($highestscore['username']),
			"score" => $db->escape_string($highestscore['score']),
			"dateline" => (int)$highestscore['dateline']
		);
		$db->update_query("arcadechampions", $updated_champion, "gid='{$game['gid']}'");
	}
}

/**
 * Get a specific user's score ranking for a game
 *
 * @param int $uid The user ID.
 * @param int $gid The game ID.
 * @param string $sortby Game score sorting direction.
 * @return string The rank of the user.
 */

function get_rank($uid, $gid, $sortby)
{
	global $db, $mybb;

	$data = array();

	$query = $db->query("
		SELECT uid
		FROM ".TABLE_PREFIX."arcadescores
		WHERE gid='{$gid}'
		ORDER BY score {$sortby}, dateline ASC
	");

	while($rows = $db->fetch_array($query))
	{
		$data[] = $rows;
	}

	$rank = 1;
	foreach($data as $item)
	{
		if($item['uid'] == $uid)
		{
			return $rank;
		}
		++$rank;
	}

	return $rank;
}

/**
 *  Build a comma separated list of the categories this user cannot view
 *
 *  @return string return a CSV list of categories user cannot view or play games in
 */
function get_unviewable_categories()
{
	global $db;

	$categories = array();

	$query = $db->simple_select("arcadecategories", "cid, `groups`");
	while($category = $db->fetch_array($query))
	{
		if(!is_member($category['groups']))
		{
			$categories[] = $category['cid'];
		}
	}

	$categories = implode(',', $categories);
	return $categories;
}

/**
 * Update the tournaments stats cache
 *
 */
function update_tournaments_stats()
{
	global $db, $cache;

	$query = $db->simple_select("arcadetournaments", "COUNT(tid) AS numwaitingtournaments", "status='1'");
	$stats['numwaitingtournaments'] = $db->fetch_field($query, 'numwaitingtournaments');

	if(!$stats['numwaitingtournaments'])
	{
		$stats['numwaitingtournaments'] = 0;
	}

	$query = $db->simple_select("arcadetournaments", "COUNT(tid) AS numrunningtournaments", "status='2'");
	$stats['numrunningtournaments'] = $db->fetch_field($query, 'numrunningtournaments');

	if(!$stats['numrunningtournaments'])
	{
		$stats['numrunningtournaments'] = 0;
	}

	$query = $db->simple_select("arcadetournaments", "COUNT(tid) AS numfinishedtournaments", "status='3'");
	$stats['numfinishedtournaments'] = $db->fetch_field($query, 'numfinishedtournaments');

	if(!$stats['numfinishedtournaments'])
	{
		$stats['numfinishedtournaments'] = 0;
	}

	$query = $db->simple_select("arcadetournaments", "COUNT(tid) AS numcancelledtournaments", "status='4'");
	$stats['numcancelledtournaments'] = $db->fetch_field($query, 'numcancelledtournaments');

	if(!$stats['numcancelledtournaments'])
	{
		$stats['numcancelledtournaments'] = 0;
	}

	$cache->update("tournaments_stats", $stats);
}

/**
 * Reload the most online cache
 *
 */
function reload_arcade_mostonline()
{
	global $db, $cache;

	$query = $db->simple_select("datacache", "title,cache", "title='arcade_mostonline'");
	$cache->update("arcade_mostonline", @unserialize($db->fetch_field($query, "cache")));
}

/**
 * Builds a user's game rankings for the stats page
 *
 *  @param int $uid User ID
 *  @param string $cat_sql Unviewable Category CSV
 */
function user_game_rank($uid, $cat_sql)
{
	global $mybb, $db, $lang, $theme, $plugins, $templates;
	$uid = (int)$uid;

	$firstplacewins = 0;
	$secondplacewins = 0;
	$thirdplacewins = 0;
	$toptenwins = 0;
	$totalscores = 0;

	$games = array();
	$score_totals = array();
	$users_place = array();
	$users_top = array();

	$query = $db->query("
		SELECT g.gid, g.sortby
		FROM ".TABLE_PREFIX."arcadegames g
		WHERE g.active='1'{$cat_sql}
	");
	while($this_game = $db->fetch_array($query))
	{
		$games[] = array(
			'gid' => $this_game['gid'],
			'type' => $this_game['sortby'],
		);
	}

	$query = $db->query("
		SELECT s.gid, s.uid, s.score
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
		WHERE g.active='1' AND g.sortby='desc'{$cat_sql}
		ORDER BY s.score DESC, s.dateline ASC
	");
	while($this_score = $db->fetch_array($query))
	{
		if(isset($score_totals[$this_score['gid']]))
		{
			$score_totals[$this_score['gid']]++;
		}

		if($this_score['uid'] == $uid)
		{
			$totalscores++;
			if(isset($this_score['score']) && isset($users_top[$this_score['gid']]) && ($this_score['score'] > $users_top[$this_score['gid']]) || empty($users_top[$this_score['gid']]))
			{
				$users_top[$this_score['gid']] = $this_score['score'];
				if(isset($score_totals[$this_score['gid']]))
				{
					$users_place[$this_score['gid']] = $score_totals[$this_score['gid']];
				}
			}
		}
	}

	$query = $db->query("
		SELECT s.gid, s.uid, s.score
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
		WHERE g.active='1' AND g.sortby='asc'{$cat_sql}
		ORDER BY s.score ASC, s.dateline ASC
	");
	while($this_score = $db->fetch_array($query))
	{
		if(isset($score_totals[$this_score['gid']]))
		{
			$score_totals[$this_score['gid']]++;
		}

		if($this_score['uid'] == $uid)
		{
			$totalscores++;
			if(isset($this_score['score']) && isset($users_top[$this_score['gid']]) && ($this_score['score'] < $users_top[$this_score['gid']]) || empty($users_top[$this_score['gid']]))
			{
				$users_top[$this_score['gid']] = $this_score['score'];
				if(isset($score_totals[$this_score['gid']]))
				{
					$users_place[$this_score['gid']] = $score_totals[$this_score['gid']];
				}
			}
		}
	}

	foreach($games as $the_game)
	{
		if(isset($users_place[$the_game['gid']]))
		{
			$rank = $users_place[$the_game['gid']];

			if($rank == 1)
			{
				$firstplacewins++;
				$toptenwins++;
			}
			else if($rank <= 10)
			{
				if($rank == 2)
				{
					$secondplacewins++;
					$toptenwins++;
				}
				else if($rank == 3)
				{
					$thirdplacewins++;
					$toptenwins++;
				}
				else
				{
					$toptenwins++;
				}
			}
		}
	}

	eval("\$statsdetails = \"".$templates->get("arcade_stats_details")."\";");
	return $statsdetails;
}

/**
 * Builds the Who's Online in the arcade
 *
 */
function whos_online()
{
	global $mybb, $db, $lang, $theme, $session, $cache, $plugins, $templates, $cat_sql;

	$collapse = $collapsed = $collapsedimg = array();
	$collapsed['online_e'] = '';
	$collapsedimg['online'] = '';

	if($mybb->settings['arcade_onlineimage'] == 1)
	{
		$query = $db->simple_select("arcadegames", "gid, smallimage", "active='1'{$cat_sql}");
		while($games = $db->fetch_array($query))
		{
			$game[$games['gid']] = $games['smallimage'];
		}
	}

	// Get the online users.
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
	$comma = '';
	$query = $db->query("
		SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY s.time DESC
	");

	$membercount = 0;
	$onlinemembers = '';
	$guestcount = 0;
	$anoncount = 0;
	$botcount = 0;
	$doneusers = array();

	// Fetch spiders
	$spiders = $cache->read("spiders");

	$plugins->run_hooks("arcade_whosonline_start");

	// Loop through all users.
	while($online = $db->fetch_array($query))
	{
		$online_loc = explode(".php", $online['location']);
		$online_loc = my_substr($online_loc[0], -strpos(strrev($online_loc[0]), "/"));

		// Is this user in the Arcade?
		if($online_loc == "arcade" || $online_loc == "tournaments")
		{
			$location = '';
			if($mybb->settings['arcade_onlineimage'] == 1)
			{
				$loc_image_gid = explode("gid=", $online['location']);

				if(isset($loc_image_gid[1]) && isset($game[trim($loc_image_gid[1])]))
				{
					$gamelink = "arcade.php?action=scores&gid={$loc_image_gid[1]}";
					$gameimage = $game[trim($loc_image_gid[1])];

					eval("\$location = \"".$templates->get("arcade_online_memberbit_image_game", 1, 0)."\";");
				}
				else
				{
					eval("\$location = \"".$templates->get("arcade_online_memberbit_image_home", 1, 0)."\";");
				}
			}

			$plugins->run_hooks("arcade_whosonline_while_start");

			// Create a key to test if this user is a search bot.
			$botkey = my_strtolower(str_replace("bot=", '', $online['sid']));

			// Decide what type of user we are dealing with.
			if($online['uid'] > 0)
			{
				// The user is registered.
				if(isset($doneusers[$online['uid']]) < $online['time'] || $doneusers[$online['uid']])
				{
					// If the user is logged in anonymously, update the count for that.
					if($online['invisible'] == 1)
					{
						++$anoncount;
					}
					++$membercount;
					if($online['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $online['uid'] == $mybb->user['uid'])
					{
						// If this usergroup can see anonymously logged-in users, mark them.
						if($online['invisible'] == 1)
						{
							$invisiblemark = "*";
						}
						else
						{
							$invisiblemark = '';
						}

						// Properly format the username and assign the template.
						$online['username'] = htmlspecialchars_uni($online['username']);
						$online['username'] = format_name($online['username'], $online['usergroup'], $online['displaygroup']);
						$online['profilelink'] = build_profile_link($online['username'], $online['uid']);
						eval("\$onlinemembers .= \"".$templates->get("arcade_online_memberbit", 1, 0)."\";");
						$comma = $lang->comma;
					}
					// This user has been handled.
					$doneuser[$online['uid']] = $online['time'];	
				}
			}
			else
			{
				// The user is a search bot.
				$botkey = my_strtolower(str_replace("bot=", '', $online['sid']));

				if(my_strpos($online['sid'], "bot=") !== false && $spiders[$botkey])
				{
					// It's a search bot, add to guest total
					$onlinemembers .= $comma.$location.format_name($spiders[$botkey]['name'], $spiders[$botkey]['usergroup']);
					$comma = $lang->comma;
					++$botcount;
				}
				else
				{
					// The user is a guest.
					++$guestcount;
				}
			}

			$plugins->run_hooks("arcade_whosonline_while_end");
		}
	}

	// Build the who's online bit.
	$onlinecount = $membercount + $guestcount + $botcount;

	if($onlinecount != 1)
	{
		$onlinebit = $lang->online_online_plural;
	}
	else
	{
		$onlinebit = $lang->online_online_singular;
	}
	if($membercount != 1)
	{
		$memberbit = $lang->online_member_plural;
	}
	else
	{
		$memberbit = $lang->online_member_singular;
	}
	if($anoncount != 1)
	{
		$anonbit = $lang->online_anon_plural;
	}
	else
	{
		$anonbit = $lang->online_anon_singular;
	}
	if($guestcount != 1)
	{
		$guestbit = $lang->online_guest_plural;
	}
	else
	{
		$guestbit = $lang->online_guest_singular;
	}

	// Find out what the highest users online count is.
	$mostonline = $cache->read("arcade_mostonline");
	if($onlinecount > $mostonline['numusers'])
	{
		$mostonline['numusers'] = $onlinecount;
		$mostonline['dateline'] = TIME_NOW;
		$cache->update("arcade_mostonline", $mostonline);
	}

	$recordcount = $mostonline['numusers'];
	$recorddate = my_date($mybb->settings['dateformat'], $mostonline['dateline']);
	$recordtime = my_date($mybb->settings['timeformat'], $mostonline['dateline']);

	$plugins->run_hooks("arcade_whosonline_end");

	// Now format that language strings.
	$lang->online_count = $lang->sprintf($lang->online_count, my_number_format($onlinecount), $onlinebit, $mybb->settings['wolcutoffmins'], my_number_format($membercount), $memberbit, my_number_format($anoncount), $anonbit, my_number_format($guestcount), $guestbit);
	$lang->online_record = $lang->sprintf($lang->online_record, $recordcount, $recorddate, $recordtime);

	eval("\$arcade_online = \"".$templates->get("arcade_online")."\";");
	return $arcade_online;
}

/**
 * Build a game bit
 *
 * @param array $game The game data
 * @return string The built game bit
 */
function build_gamebit($game)
{
	global $mybb, $lang, $templates, $plugins;

	$alt_bg = alt_trow();

	$game['name'] = htmlspecialchars_uni($game['name']);
	$game['description'] = htmlspecialchars_uni($game['description']);

	$play_full_screen = '';
	if($mybb->usergroup['canplayarcade'] == 1)
	{
		$gamelink = "arcade.php?action=play&gid={$game['gid']}";
		eval("\$play_full_screen = \"".$templates->get("arcade_gamebit_fullscreen")."\";");
	}
	else
	{
		$gamelink = "arcade.php?action=scores&gid={$game['gid']}";
	}

	if($game['lastplayed'])
	{
		$lastplayed = my_date('relative', $game['lastplayed']);
		$game['username'] = format_name(htmlspecialchars_uni($game['username']), $game['usergroup'], $game['displaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$game['lastplayeduid']}";
		}
		else
		{
			$profilelink = get_profile_link($game['lastplayeduid']);
		}

		eval("\$lastplayedby = \"".$templates->get("arcade_gamebit_lastplayed")."\";");
	}
	else
	{
		$lastplayedby = $lang->na;
	}

	if($game['champscore'])
	{
		$game['champscore'] = my_number_format((float)$game['champscore']);
		$champion = $lang->sprintf($lang->champion_with_score, $game['champscore']);
	}
	else
	{
		$champion = $lang->sprintf($lang->champion_with_score, $lang->na);
	}

	if($game['champusername'])
	{
		$game['champusername'] = format_name(htmlspecialchars_uni($game['champusername']), $game['champusergroup'], $game['champdisplaygroup']);

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$profilelink = "arcade.php?action=stats&uid={$game['champuid']}";
		}
		else
		{
			$profilelink = get_profile_link($game['champuid']);
		}

		eval("\$champusername = \"".$templates->get("arcade_gamebit_champ")."\";");
	}
	else
	{
		$champusername = $lang->na;
	}

	if($game['score'])
	{
		$game['score'] = my_number_format((float)$game['score']);
	}
	else
	{
		$game['score'] = $lang->na;
	}

	$your_score = '';
	if($mybb->user['uid'] != 0 && $mybb->usergroup['canplayarcade'] == 1)
	{
		eval("\$your_score = \"".$templates->get("arcade_gamebit_score")."\";");
	}

	$tournament = '';
	if($game['tournamentselect'] == 1 && $mybb->usergroup['cancreatetournaments'] == 1)
	{
		eval("\$tournament = \"".$templates->get("arcade_gamebit_tournaments")."\";");
	}

	// Is this a new game?
	$time = TIME_NOW-($mybb->settings['arcade_newgame']*60*60*24);

	$new = '';
	if($game['dateline'] >= $time)
	{
		eval("\$new = \"".$templates->get("arcade_gamebit_new")."\";");
	}

	// Favorite check
	$add_remove_favorite = '';
	if($mybb->user['uid'] != 0)
	{
		if($game['favorite'])
		{
			$add_remove_favorite_type = 'remove';
			$add_remove_favorite_text = $lang->remove_from_favorites;
		}
		else
		{
			$add_remove_favorite_type = 'add';
			$add_remove_favorite_text = $lang->add_to_favorites;
		}

		eval("\$add_remove_favorite = \"".$templates->get("arcade_gamebit_favorite")."\";");
	}

	// Work out the rating for this game.
	$rategame = '';
	if($mybb->settings['arcade_ratings'] != 0)
	{
		$game['averagerating'] = (float)round($game['averagerating'], 2);
		$game['rating_width'] = (int)round($game['averagerating'])*20;
		$game['numratings'] = (int)$game['numratings'];

		$not_rated = '';
		if(!isset($game['rated']) || empty($game['rated']))
		{
			$not_rated = ' star_rating_notrated';
		}

		$ratingvotesav = $lang->sprintf($lang->rating_votes_average, $game['numratings'], $game['averagerating']);
		eval("\$rategame = \"".$templates->get("arcade_gamebit_rating")."\";");
	}

	$plugins->run_hooks("arcade_game");

	eval("\$game_bit = \"".$templates->get("arcade_gamebit")."\";");
	return $game_bit;
}

/**
 * Perform a game search under MySQL or MySQLi
 *
 * @param array $search Array of search data
 * @return array Array of search data with results mixed in
 */
function arcade_perform_search_mysql($search)
{
	global $mybb, $db, $lang;
	$lang->load("arcade");

	require_once MYBB_ROOT."inc/functions_search.php";

	$keywords = clean_keywords($search['keywords']);
	if(!$keywords)
	{
		error($lang->error_nosearchterms);
	}

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 3;
	}

	$name_lookin = "";
	$description_lookin = "";
	$searchsql = "active='1'";

	if($keywords)
	{
		// Complex search
		$keywords = " {$keywords} ";
		if(preg_match("# and|or #", $keywords))
		{
			$string = "AND";
			if($search['name'] == 1)
			{
				$string = "OR";
				$name_lookin = " AND (";
			}

			if($search['description'] == 1)
			{
				$description_lookin = " {$string} (";
			}

			// Expand the string by double quotes
			$keywords_exp = explode("\"", $keywords);
			$inquote = false;

			foreach($keywords_exp as $phrase)
			{
				// If we're not in a double quoted section
				if(!$inquote)
				{
					// Expand out based on search operators (and, or)
					$matches = preg_split("#\s{1,}(and|or)\s{1,}#", $phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
					$count_matches = count($matches);

					for($i=0; $i < $count_matches; ++$i)
					{
						$word = trim($matches[$i]);
						if(empty($word))
						{
							continue;
						}
						// If this word is a search operator set the boolean
						if($i % 2 && ($word == "and" || $word == "or"))
						{
							if($i <= 1)
							{
								if($search['name'] && $search['description'] && $name_lookin == " AND (")
								{
									// We're looking for anything, check for a name lookin
									continue;
								}
								elseif($search['name'] && !$search['description'] && $name_lookin == " AND (")
								{
									// Just in a name?
									continue;
								}
								elseif(!$search['name'] && $search['description'] && $description_lookin == " {$string} (")
								{
									// Just in a description?
									continue;	
								}
							}

							$boolean = $word;
						}
						// Otherwise check the length of the word as it is a normal search term
						else
						{
							$word = trim($word);
							// Word is too short - show error description
							if(my_strlen($word) < $mybb->settings['minsearchword'])
							{
								$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
								error($lang->error_minsearchlength);
							}
							// Add terms to search query
							if($search['name'] == 1)
							{
								$name_lookin .= " $boolean LOWER(name) LIKE '%{$word}%'";
							}
							if($search['description'] == 1)
							{
								$description_lookin .= " $boolean LOWER(description) LIKE '%{$word}%'";
							}
						}
					}
				}	
				// In the middle of a quote (phrase)
				else
				{
					$phrase = str_replace(array("+", "-", "*"), '', trim($phrase));
					if(my_strlen($phrase) < $mybb->settings['minsearchword'])
					{
						$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
						error($lang->error_minsearchlength);
					}
					// Add phrase to search query
					$name_lookin .= " $boolean LOWER(name) LIKE '%{$phrase}%'";
					if($search['description'] == 1)
					{
						$description_lookin .= " $boolean LOWER(description) LIKE '%{$phrase}%'";
					}					
				}

				// Check to see if we have any search terms and not a malformed SQL string
				$error = false;
				if($search['name'] && $search['description'] && $name_lookin == " AND (")
				{
					// We're looking for anything, check for a name lookin
					$error = true;
				}
				elseif($search['name'] && !$search['description'] && $name_lookin == " AND (")
				{
					// Just in a name?
					$error = true;
				}
				elseif(!$search['name'] && $search['description'] && $description_lookin == " {$string} (")
				{
					// Just in a description?
					$error = true;	
				}

				if($error == true)
				{
					// There are no search keywords to look for
					$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
					error($lang->error_minsearchlength);
				}

				$inquote = !$inquote;
			}

			if($search['name'] == 1)
			{
				$name_lookin .= ")";
			}

			if($search['description'] == 1)
			{
				$description_lookin .= ")";
			}

			$searchsql .= "{$name_lookin} {$description_lookin}";
		}
		else
		{
			$keywords = str_replace("\"", '', trim($keywords));
			if(my_strlen($keywords) < $mybb->settings['minsearchword'])
			{
				$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
				error($lang->error_minsearchlength);
			}

			// If we're looking in both, then find matches in either the name or the description
			if($search['name'] == 1 && $search['description'] == 1)
			{
				$searchsql .= " AND (LOWER(name) LIKE '%{$keywords}%' OR LOWER(description) LIKE '%{$keywords}%')";
			}
			else
			{
				if($search['name'] == 1)
				{
					$searchsql .= " AND LOWER(name) LIKE '%{$keywords}%'";
				}

				if($search['description'] == 1)
				{
					$searchsql .= " AND LOWER(description) LIKE '%{$keywords}%'";
				}
			}
		}
	}

	if(!is_array($search['cid']))
	{
		$search['cid'] = array($search['cid']);
	}

	if(!empty($search['cid']))
	{
		$categoryids = array();

		$search['cid'] = array_map("intval", $search['cid']);

		$categoryids = implode(',', $search['cid']);

		if($categoryids)
		{
			$searchsql .= " AND cid IN (".$categoryids.")";
		}
	}

	$unviewable = get_unviewable_categories($mybb->user['usergroup']);
	if($unviewable)
	{
		$searchsql .= " AND cid NOT IN ($unviewable)";
	}

	// Run the search
	$games = array();
	$query = $db->simple_select("arcadegames", "gid", $searchsql);
	while($game = $db->fetch_array($query))
	{
		$games[$game['gid']] = $game['gid'];
	}

	if(count($games) < 1)
	{
		error($lang->error_nosearchresults);
	}
	$games = implode(',', $games);

	return array(
		"querycache" => $games
	);
}

/**
 * Start a tournament if ready
 *
 * @param int $tid The tournament ID
 */
function start_tournament($tid)
{
	global $db, $mybb;

	$tid = (int)$tid;
	$tournament = get_tournament($tid);

	// Invalid tournament
	if(!$tournament['tid'])
	{
		return false;
	}

	$game = get_game($tournament['gid']);

	// Invalid game
	if(!$game['gid'])
	{
		return false;
	}

	// Make sure we have enough players to start
	$players = pow(2, $tournament['rounds']);

	$query = $db->simple_select("arcadetournamentplayers", "*", "tid='{$tournament['tid']}'");
	$playersentered = $db->num_rows($query);

	if($players <= $playersentered)
	{
		$information = array();
		$information['1']['starttime'] = TIME_NOW;

		$update_tournament = array(
			"status" => 2,
			"round" => 1,
			"information" => serialize($information)
		);

		$db->update_query("arcadetournaments", $update_tournament, "tid='{$tournament['tid']}'");

		$query = $db->query("
			SELECT p.uid, u.tournamentnotify, u.receivepms, u.language, u.email
			FROM ".TABLE_PREFIX."arcadetournamentplayers p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.tid='{$tournament['tid']}'
		");
		while($player = $db->fetch_array($query))
		{
			if($player['tournamentnotify'] == 1)
			{
				$player_pm = array(
					'subject' => 'tournament_subject',
					'message' => array('tournament_message', $game['name'], $tournament['days'], $tournament['tries']),
					'touid' => $player['uid'],
					'receivepms' => (int)$player['receivepms'],
					'language' => $player['language'],
					'language_file' => 'arcade'
				);

				send_pm($player_pm, $tournament['uid']);
			}

			else if($player['tournamentnotify'] == 2)
			{
				$emailsubject = $lang->sprintf($lang->tournament_email_subject, $mybb->settings['bbname']);
				$emailmessage = $lang->sprintf($lang->tournament_message, $game['name'], $tournament['days'], $tournament['tries']);

				my_mail($player['email'], $emailsubject, $emailmessage);
			}

			// My Alerts support
			if($db->table_exists("alert_types") && class_exists("MybbStuff_MyAlerts_AlertTypeManager"))
			{
				$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('arcade_newround');

				if ($alertType != null && $alertType->getEnabled()) {
					$alert = new MybbStuff_MyAlerts_Entity_Alert($player['uid'], $alertType, $tournament['tid'], $tournament['uid']);
							$alert->setExtraDetails(
							array(
								'tid' 		=> $tournament['tid'],
								'g_name' => $db->escape_string($game['name'])
							));
					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				}
			}
		}

		update_tournaments_stats();
		return true;
	}

	return false;
}
