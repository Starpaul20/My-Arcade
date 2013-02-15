<?php
/**
 * My Arcade
 * Copyright 2013 Starpaul20
 */

/**
 * Get the game of a game id.
 *
 * @param int The game id of the arcade game.
 * @param boolean Whether or not to recache the game.
 * @return string The database row of the game.
 */
function get_game($gid, $recache = false)
{
	global $db;
	static $game_cache;

	if(isset($game_cache[$gid]) && !$recache)
	{
		return $game_cache[$gid];
	}
	else
	{
		$query = $db->simple_select("arcadegames", "*", "gid='".intval($gid)."'");
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
 * @param int The tournament ID.
 * @param boolean Whether or not to recache the tournament.
 * @return string The database row of the tournament.
 */
function get_tournament($tid, $recache = false)
{
	global $db;
	static $tournament_cache;

	if(isset($tournament_cache[$tid]) && !$recache)
	{
		return $tournament_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("arcadetournaments", "*", "tid='".intval($tid)."'");
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
 * @param array The data of the action.
 * @param string The message to enter for the action performed.
 */
function log_arcade_action($data, $action="")
{
	global $mybb, $db, $session;

	// If the Game ID is not set, set it to 0 so MySQL doesn't choke on it.
	if($data['gid'] == '')
	{
		$game = 0;
	}
	else
	{
		$game = $data['gid'];
		unset($data['gid']);
	}

	// If the Tournament ID is not set, set it to 0 so MySQL doesn't choke on it.
	if($data['tid'] == '')
	{
		$tournament = 0;
	}
	else
	{
		$tournament = $data['tid'];
		unset($data['tid']);
	}

	// Any remaining extra data - we serialize and insert in to its own column
	if(is_array($data))
	{
		$data = serialize($data);
	}

	$sql_array = array(
		"uid" => intval($mybb->user['uid']),
		"dateline" => TIME_NOW,
		"gid" => intval($game),
		"tid" => intval($tournament),
		"action" => $db->escape_string($action),
		"data" => $db->escape_string($data),
		"ipaddress" => $db->escape_string($session->ipaddress)
	);
	$db->insert_query("arcadelogs", $sql_array);
}

/**
 * Update Champion of a game.
 *
 * @param int The game ID.
 */
function update_champion($gid)
{
	global $db;

	$query = $db->simple_select("arcadegames", "sortby", "gid='".intval($gid)."'");
	$game = $db->fetch_array($query);

	// Fetch the highest score for this game
	$query2 = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."arcadescores
		WHERE gid='{$gid}'
		ORDER BY score {$game['sortby']}, dateline ASC
		LIMIT 0, 1
	");
	$highestscore = $db->fetch_array($query2);

	// If no highest score exists, delete champion. Otherwise update
	if(!$highestscore['sid'])
	{
		$db->delete_query("arcadechampions", "gid='{$gid}'");
	}
	else
	$updated_champion = array(
		"uid" => intval($highestscore['uid']),
		"username" => $db->escape_string($highestscore['username']),
		"score" => $db->escape_string($highestscore['score']),
		"dateline" => intval($highestscore['dateline'])
	);

	$db->update_query("arcadechampions", $updated_champion, "gid='{$gid}'");
}

/**
 * Get a specific user's score ranking for a game
 *
 * @param int The user ID.
 * @param int The game ID.
 * @param int Game score sorting direction.
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
 *  @param int The primary group ID
 *  @return string return a CSV list of categories user cannot view or play games in
 */
function get_unviewable_categories($usergroup)
{
	global $db, $lang, $mybb;

	$categories = array();

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->simple_select("arcadecategories", "cid", "','||groups||',' NOT LIKE '%,$usergroup,%' AND ','||groups||',' NOT LIKE '%,-1,%'");
			break;
		default:
			$query = $db->simple_select("arcadecategories", "cid", "CONCAT(',',groups,',') NOT LIKE '%,$usergroup,%' AND CONCAT(',',groups,',') NOT LIKE '%,-1,%'");
	}

	while($category = $db->fetch_array($query))
	{
		$categories[] = $category['cid'];
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

	$cache->update("tournaments_stats", $stats);
}

/**
 * Builds a user's game rankings for the stats page
 *
 *  @param int User ID
 *  @param int Unviewable Category CSV
 */
function user_game_rank($uid, $cat_sql)
{
	global $mybb, $db, $lang, $theme, $plugins, $templates;
	$uid = intval($uid);

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
		SELECT g.sortby, g.active, g.cid, s.*
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
		WHERE g.active='1' AND g.sortby='desc'{$cat_sql}
		ORDER BY s.score DESC, s.dateline ASC
	");
	while($this_score = $db->fetch_array($query))
	{
		$score_totals[$this_score['gid']]++;

		if($this_score['uid'] == $uid)
		{
			$totalscores++;
			if(($this_score['score'] > $users_top[$this_score['gid']]) || $users_top[$this_score['gid']] == 0)
			{
				$users_top[$this_score['gid']] = $this_score['score'];
				$users_place[$this_score['gid']] = $score_totals[$this_score['gid']];
			}
		}
	}

	$query = $db->query("
		SELECT g.sortby, g.active, g.cid, s.*
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
		WHERE g.active='1' AND g.sortby='asc'{$cat_sql}
		ORDER BY s.score ASC, s.dateline ASC
	");
	while($this_score = $db->fetch_array($query))
	{
		$score_totals[$this_score['gid']]++;

		if($this_score['uid'] == $uid)
		{
			$totalscores++;
			if(($this_score['score'] < $users_top[$this_score['gid']]) || $users_top[$this_score['gid']] == 0)
			{
				$users_top[$this_score['gid']] = $this_score['score'];
				$users_place[$this_score['gid']] = $score_totals[$this_score['gid']];
			}
		}
	}

	foreach($games as $the_game)
	{
		if($users_place[$the_game['gid']])
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

	if($mybb->settings['arcade_onlineimage'] == 1)
	{
		$query = $db->simple_select("arcadegames", "*", "active='1'{$cat_sql}");
		while($games = $db->fetch_array($query))
		{
			$game[$games['gid']] = $games['smallimage'];
		}
	}

	// Get the online users.
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
	$comma = '';
	$query = $db->query("
		SELECT s.sid, s.ip, s.uid, s.time, s.location, s.location1, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."sessions s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		WHERE s.time>'$timesearch'
		ORDER BY s.time DESC
	");

	$membercount = 0;
	$onlinemembers = '';
	$guestcount = 0;
	$anoncount = 0;
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
			if($mybb->settings['arcade_onlineimage'] == 1)
			{
				$loc_image_gid = explode("gid=", $online['location']);
				$loc_image_gid = explode("&", $loc_image_gid[1]);
				$loc_image_link = my_substr($online['location'], -strpos(strrev($online['location']), "/"));

				if(isset($loc_image_gid[0]) && isset($game[trim($loc_image_gid[0])]))
				{
					$location = "<a href=\"".$loc_image_link."\"><img src=\"arcade/smallimages/".$game[trim($loc_image_gid[0])].".gif\" alt=\"\" /></a> ";
				}
				else
				{
					$location = "<a href=\"arcade.php\"><img src=\"images/arcade/arcade.png\" alt=\"Home\" /></a> ";
				}
			}

			$plugins->run_hooks("arcade_whosonline_while_start");

			// Create a key to test if this user is a search bot.
			$botkey = my_strtolower(str_replace("bot=", '', $online['sid']));

			// Decide what type of user we are dealing with.
			if($online['uid'] > 0)
			{
				// The user is registered.
				if($doneusers[$online['uid']] < $online['time'] || !$doneusers[$online['uid']])
				{
					// If the user is logged in anonymously, update the count for that.
					if($user['invisible'] == 1)
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
 * Perform a game search under MySQL or MySQLi
 *
 * @param array Array of search data
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

?>