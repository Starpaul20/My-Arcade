<?php
/**
 * My Arcade
 * Copyright 2012 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(my_strpos($_SERVER['PHP_SELF'], 'member.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'global_arcade_bit,member_profile_arcade';
}

if(my_strpos($_SERVER['PHP_SELF'], 'showthread.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'global_arcade_bit';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("index_start", "myarcade_index");
$plugins->add_hook("global_start", "myarcade_link");
$plugins->add_hook("showthread_start", "myarcade_categories");
$plugins->add_hook("postbit", "myarcade_postbit_post");
$plugins->add_hook("postbit_pm", "myarcade_postbit_other");
$plugins->add_hook("postbit_announcement", "myarcade_postbit_other");
$plugins->add_hook("postbit_prev", "myarcade_postbit_other");
$plugins->add_hook("member_profile_end", "myarcade_profile");
$plugins->add_hook("fetch_wol_activity_end", "myarcade_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "myarcade_online_location");
$plugins->add_hook("datahandler_user_update", "myarcade_user_update");

$plugins->add_hook("admin_style_templates_set", "myarcade_templates");
$plugins->add_hook("admin_user_users_merge_commit", "myarcade_merge");
$plugins->add_hook("admin_user_users_delete_commit", "myarcade_delete");
$plugins->add_hook("admin_user_groups_edit_graph_tabs", "myarcade_usergroups_permission");
$plugins->add_hook("admin_user_groups_edit_graph", "myarcade_usergroups_graph");
$plugins->add_hook("admin_user_groups_edit_commit", "myarcade_usergroups_commit");
$plugins->add_hook("admin_tools_cache_begin", "myarcade_datacache_class");
$plugins->add_hook("admin_tools_get_admin_log_action", "myarcade_admin_adminlog");

// The information that shows up on the plugin manager
function myarcade_info()
{
	return array(
		"name"				=> "My Arcade",
		"description"		=> "Adds an arcade to your board.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0 BETA",
		"guid"				=> "",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is installed.
function myarcade_install()
{
	global $db, $cache;
	myarcade_uninstall();
	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadegames (
				gid int(10) unsigned NOT NULL auto_increment,
				name varchar(50) NOT NULL default '',
				description text NOT NULL,
				about text NOT NULL,
				controls text NOT NULL,
				file varchar(40) NOT NULL default '',
				smallimage varchar(40) NOT NULL default '',
				largeimage varchar(40) NOT NULL default '',
				cid smallint(5) unsigned NOT NULL default '0',
				plays int(10) NOT NULL default '0',
				lastplayed bigint(30) NOT NULL default '0',
				lastplayeduid int(10) unsigned NOT NULL default '0',
				dateline bigint(30) NOT NULL default '0',
				bgcolor varchar(6) NOT NULL default '',
				width varchar(4) NOT NULL default '',
				height varchar(4) NOT NULL default '',
				sortby varchar(10) NOT NULL default 'desc',
				numratings smallint(5) NOT NULL default '0',
				totalratings smallint(5) NOT NULL default '0',
				tournamentselect int(1) NOT NULL default '1',
				active int(1) NOT NULL default '1',
				KEY cid (cid),
				PRIMARY KEY(gid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadecategories (
				cid smallint(5) unsigned NOT NULL auto_increment,
				name varchar(50) NOT NULL default '',
				image varchar(200) NOT NULL default '',
				groups text NOT NULL,
				active int(1) NOT NULL default '1',
				PRIMARY KEY(cid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadechampions (
				cid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				score float NOT NULL,
				dateline bigint(30) NOT NULL default '0',
				KEY gid (gid),
				PRIMARY KEY(cid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadefavorites (
				fid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				KEY uid (uid),
				PRIMARY KEY(fid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadelogs (
				uid int(10) unsigned NOT NULL default '0',
				dateline bigint(30) NOT NULL default '0',
				gid int(10) unsigned NOT NULL default '0',
				tid int(10) unsigned NOT NULL default '0',
				action text NOT NULL,
				data text NOT NULL,
				ipaddress varchar(50) NOT NULL,
				KEY gid (gid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcaderatings (
				rid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				rating smallint(5) NOT NULL default '0',
				ipaddress varchar(30) NOT NULL default '',
				KEY gid (gid, uid),
				PRIMARY KEY(rid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadescores (
				sid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				score float NOT NULL,
				dateline bigint(30) NOT NULL default '0',
				timeplayed int(10) NOT NULL default '0',
				comment varchar(200) NOT NULL default '',
				ipaddress varchar(30) NOT NULL default '',
				KEY gid (gid),
				KEY uid (uid),
				PRIMARY KEY(sid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadesessions (
				sid varchar(32) NOT NULL,
				uid int(10) unsigned NOT NULL default '0',
				gid int(10) unsigned NOT NULL default '0',
				tid int(10) unsigned NOT NULL default '0',
				dateline bigint(30) NOT NULL default '0',
				randchar1 varchar(100) NOT NULL default '',
				randchar2 varchar(100) NOT NULL default '',
				gname varchar(40) NOT NULL default '',
				gtitle varchar(50) NOT NULL default '',
				ipaddress varchar(50) NOT NULL default '',
				KEY uid (uid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadetournaments (
				tid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				dateline bigint(30) NOT NULL default '0',
				status int(1) NOT NULL default '1',
				rounds int(2) NOT NULL default '0',
				tries int(2) NOT NULL default '0',
				numplayers int(4) NOT NULL default '1',
				days int(1) NOT NULL default '0',
				round int(2) NOT NULL default '0',
				champion int(10) NOT NULL default '0',
				finishdateline bigint(30) NOT NULL default '0',
				information text NOT NULL,
				KEY gid (gid),
				PRIMARY KEY(tid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadetournamentplayers (
				pid int(10) unsigned NOT NULL auto_increment,
				tid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				score float NOT NULL,
				round int(2) NOT NULL default '0',
				attempts int(5) NOT NULL default '0',
				scoreattempt int(2) NOT NULL default '0',
				timeplayed bigint(30) NOT NULL default '0',
				status int(1) NOT NULL default '1',
				KEY tid (tid),
				KEY uid (uid),
				PRIMARY KEY(pid)
			) ENGINE=MyISAM{$collation}");

	$db->add_column("users", "gamesperpage", "int(3) NOT NULL default '0'");
	$db->add_column("users", "scoresperpage", "int(3) NOT NULL default '0'");
	$db->add_column("users", "gamessortby", "varchar(10) NOT NULL default ''");
	$db->add_column("users", "gamesorder", "varchar(4) NOT NULL default ''");
	$db->add_column("users", "whosonlinearcade", "int(1) NOT NULL default '1'");
	$db->add_column("users", "champdisplaypostbit", "int(1) NOT NULL default '1'");
	$db->add_column("users", "tournamentnotify", "int(1) NOT NULL default '0'");
	$db->add_column("users", "champnotify", "int(1) NOT NULL default '0'");

	$db->add_column("usergroups", "canviewarcade", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canplayarcade", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "maxplaysday", "int(3) NOT NULL default '25'");
	$db->add_column("usergroups", "canmoderategames", "int(1) NOT NULL default '0'");
	$db->add_column("usergroups", "canrategames", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "cansearchgames", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canviewgamestats", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canviewtournaments", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canjointournaments", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "cancreatetournaments", "int(1) NOT NULL default '0'");

	// Setting some basic arcade permissions...
	$update_array = array(
		"maxplaysday" => 0,
		"canmoderategames" => 1,
		"cancreatetournaments" => 1
	);
	$db->update_query("usergroups", $update_array, "cancp='1' OR issupermod='1'");

	$update_array = array(
		"canviewarcade" => 0,
		"canplayarcade" => 0,
		"maxplaysday" => 0,
		"canrategames" => 0,
		"cansearchgames" => 0,
		"canviewgamestats" => 0,
		"canviewtournaments" => 0,
		"canjointournaments" => 0
	);
	$db->update_query("usergroups", $update_array, "isbannedgroup='1' OR canview='0' OR gid IN('1','5')");

	$cache->update_usergroups();
}

// Checks to make sure plugin is installed
function myarcade_is_installed()
{
	global $db;
	if($db->table_exists("arcadegames"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function myarcade_uninstall()
{
	global $db, $cache;

	if($db->table_exists("arcadegames"))
	{
		$db->drop_table("arcadegames");
	}

	if($db->table_exists("arcadecategories"))
	{
		$db->drop_table("arcadecategories");
	}

	if($db->table_exists("arcadechampions"))
	{
		$db->drop_table("arcadechampions");
	}

	if($db->table_exists("arcadefavorites"))
	{
		$db->drop_table("arcadefavorites");
	}

	if($db->table_exists("arcadelogs"))
	{
		$db->drop_table("arcadelogs");
	}

	if($db->table_exists("arcaderatings"))
	{
		$db->drop_table("arcaderatings");
	}

	if($db->table_exists("arcadescores"))
	{
		$db->drop_table("arcadescores");
	}

	if($db->table_exists("arcadesessions"))
	{
		$db->drop_table("arcadesessions");
	}

	if($db->table_exists("arcadetournaments"))
	{
		$db->drop_table("arcadetournaments");
	}

	if($db->table_exists("arcadetournamentplayers"))
	{
		$db->drop_table("arcadetournamentplayers");
	}

	if($db->field_exists("gamesperpage", "users"))
	{
		$db->drop_column("users", "gamesperpage");
	}

	if($db->field_exists("scoresperpage", "users"))
	{
		$db->drop_column("users", "scoresperpage");
	}

	if($db->field_exists("gamessortby", "users"))
	{
		$db->drop_column("users", "gamessortby");
	}

	if($db->field_exists("gamesorder", "users"))
	{
		$db->drop_column("users", "gamesorder");
	}

	if($db->field_exists("whosonlinearcade", "users"))
	{
		$db->drop_column("users", "whosonlinearcade");
	}

	if($db->field_exists("champdisplaypostbit", "users"))
	{
		$db->drop_column("users", "champdisplaypostbit");
	}

	if($db->field_exists("tournamentnotify", "users"))
	{
		$db->drop_column("users", "tournamentnotify");
	}

	if($db->field_exists("champnotify", "users"))
	{
		$db->drop_column("users", "champnotify");
	}

	if($db->field_exists("canviewarcade", "usergroups"))
	{
		$db->drop_column("usergroups", "canviewarcade");
	}

	if($db->field_exists("canplayarcade", "usergroups"))
	{
		$db->drop_column("usergroups", "canplayarcade");
	}

	if($db->field_exists("maxplaysday", "usergroups"))
	{
		$db->drop_column("usergroups", "maxplaysday");
	}

	if($db->field_exists("canmoderategames", "usergroups"))
	{
		$db->drop_column("usergroups", "canmoderategames");
	}

	if($db->field_exists("canrategames", "usergroups"))
	{
		$db->drop_column("usergroups", "canrategames");
	}

	if($db->field_exists("cansearchgames", "usergroups"))
	{
		$db->drop_column("usergroups", "cansearchgames");
	}

	if($db->field_exists("canviewgamestats", "usergroups"))
	{
		$db->drop_column("usergroups", "canviewgamestats");
	}

	if($db->field_exists("canviewtournaments", "usergroups"))
	{
		$db->drop_column("usergroups", "canviewtournaments");
	}

	if($db->field_exists("canjointournaments", "usergroups"))
	{
		$db->drop_column("usergroups", "canjointournaments");
	}

	if($db->field_exists("cancreatetournaments", "usergroups"))
	{
		$db->drop_column("usergroups", "cancreatetournaments");
	}

	$cache->update_usergroups();

	$db->delete_query("datacache", "title IN('tournaments_stats','arcade_mostonline')");
	$db->delete_query("templates", "title IN('global_arcade_bit','header_arcade_link','member_profile_arcade','arcade','arcade_categories','arcade_category_bit','arcade_champions','arcade_champions_bit','arcade_edit','arcade_edited','arcade_edit_error','arcade_favorites','arcade_gamebit','arcade_menu','arcade_no_display','arcade_no_games','arcade_online','arcade_online_memberbit','arcade_play_rating','arcade_play','arcade_play_tournament','arcade_rating','arcade_scoreboard','arcade_scoreboard_bit','arcade_scores','arcade_scores_bit','arcade_scores_no_scores','arcade_search','arcade_search_catagory','arcade_search_results','arcade_settings','arcade_settings_gamesselect','arcade_settings_scoreselect','arcade_settings_tournamentnotify','arcade_settings_whosonline','arcade_settings_champpostbit','arcade_statistics','arcade_statistics_bestplayers','arcade_statistics_bestplayers_bit','arcade_statistics_gamebit','arcade_statistics_scorebit','arcade_stats','arcade_stats_bit','arcade_stats_tournaments','arcade_stats_details','arcade_tournaments','arcade_tournaments_create','arcade_tournaments_user','arcade_tournaments_user_game','tournaments_cancel','tournaments_cancelled','tournaments_cancel_error','tournaments_create','tournaments_finished','tournaments_finished_bit','tournaments_no_tournaments','tournaments_running','tournaments_running_bit','tournaments_view','tournaments_view_rounds','tournaments_view_rounds_bit','tournaments_view_rounds_bit_info','tournaments_view_rounds_champion','tournaments_waiting','tournaments_waiting_bit')");
}

// This function runs when the plugin is activated.
function myarcade_activate()
{
	global $db;

	// Insert setting groups
	$insertarray = array(
		'name'			=> 'arcade',
		'title'			=> 'Arcade Settings',
		'description'	=> 'This section allows you to control various aspects of the arcade (arcade.php), such as how many games to show per page, and which features to enable or disable.',
		'disporder'		=> 70,
		'isdefault'		=> 0
	);
	$gid = $db->insert_query('settinggroups', $insertarray);

	$insertarray = array(
		'name'			=> 'tournaments',
		'title'			=> 'Tournament Settings',
		'description'	=> 'Various options with relation to the Arcade tournament system (tournaments.php) can be managed and set here.',
		'disporder'		=> 71,
		'isdefault'		=> 0
	);
	$tid = $db->insert_query('settinggroups', $insertarray);

	// Insert settings
	$insertarray = array(
		'name' => 'enablearcade',
		'title' => 'Enable Arcade Functionality',
		'description' => 'If you wish to disable the arcade on your board, set this option to no.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 1,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_stats',
		'title' => 'Show Statistic Box',
		'description' => 'Allows you to set whether or not the statistic box will be shown at the top of the arcade home page.',
		'optionscode' => 'onoff',
		'value' => 1,
		'disporder' => 2,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_stats_newgames',
		'title' => 'New Games/Most Played Games',
		'description' => 'The number of new games and most played games to show on the statistics box.',
		'optionscode' => 'text',
		'value' => 15,
		'disporder' => 3,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_stats_newchamps',
		'title' => 'Newest Champs',
		'description' => 'The number of newest champs to show in the statistic box.',
		'optionscode' => 'text',
		'value' => 5,
		'disporder' => 4,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_stats_newscores',
		'title' => 'Latest Scores',
		'description' => 'Number of latest scores to show in the statistic box.',
		'optionscode' => 'text',
		'value' => 5,
		'disporder' => 5,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_stats_bestplayers',
		'title' => 'Show Best Players',
		'description' => 'Do you wish to show a box of the three best players in the statistic box?',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 6,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_stats_avatar',
		'title' => 'Show Avatar',
		'description' => $db->escape_string('Do you wish to show the user\'s avatar on the Best Players section?'),
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 7,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'gamesperpage',
		'title' => 'Games Per Page',
		'description' => 'The number of games to show per page on the arcade page.',
		'optionscode' => 'text',
		'value' => 10,
		'disporder' => 8,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'gamessortby',
		'title' => 'Default Sort Games By',
		'description' => 'Select the field that you want games to be sorted by default.',
		'optionscode' => 'select
name=Name
date=Date Added
plays=Times Played
lastplayed=Date Last Played
rating=Rating',
		'value' => 'name',
		'disporder' => 9,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'gamesorder',
		'title' => 'Default Game Ordering',
		'description' => 'Select the order that you want games to be sorted by default.<br />Ascending: A-Z / beginning-end<br />Descending: Z-A / end-beginning',
		'optionscode' => 'select
asc=Ascending
desc=Descending',
		'value' => 'asc',
		'disporder' => 10,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_category_number',
		'title' => 'Number Of Categories Per Row',
		'description' => 'The number of categories to display on a single row of the category table. It is recommended that this value be no higher than 10.',
		'optionscode' => 'text',
		'value' => 5,
		'disporder' => 11,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_newgame',
		'title' => 'Days For New Game',
		'description' => 'The number of days a game will be marked as new after being added.',
		'optionscode' => 'text',
		'value' => 7,
		'disporder' => 12,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_ratings',
		'title' => 'Game Ratings',
		'description' => 'Do you wish to allow games to be rated? Group permission can be set on Group Management page.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 13,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_searching',
		'title' => 'Game Searching',
		'description' => 'Do you wish to allow users to search for games based on name, description and category? Group permission can be set on Group Management page.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 14,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_whosonline',
		'title' => $db->escape_string('Who\'s Online Display'),
		'description' => 'Do you wish to show a box of who is online in the arcade and who can view it?',
		'optionscode' => 'radio
0=Disabled
1=Arcade Moderators and Administrators only
2=Registered Members
3=Everyone',
		'value' => 3,
		'disporder' => 15,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_onlineimage',
		'title' => 'Online Image',
		'description' => 'Do you wish to show an image of where the user currently is in the arcade?',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 16,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'scoresperpage',
		'title' => 'Scores Per Page',
		'description' => 'The number of scores to show per page on the score page.',
		'optionscode' => 'text',
		'value' => 10,
		'disporder' => 17,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_editcomment',
		'title' => 'Comment Edit Time Limit',
		'description' => 'The number of minutes until regular users cannot edit their own score comments. Enter 0 (zero) for no limit.',
		'optionscode' => 'text',
		'value' => 60,
		'disporder' => 18,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_maxcommentlength',
		'title' => 'Maximum Score Comment Length',
		'description' => 'The maximum number of characters a score comment can be.',
		'optionscode' => 'text',
		'value' => 120,
		'disporder' => 19,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'statsperpage',
		'title' => 'Stats Per Page',
		'description' => 'The number of games to show per page on the stats page. It is recommended this be no higher than 15.',
		'optionscode' => 'text',
		'value' => 10,
		'disporder' => 20,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'gamesperpageoptions',
		'title' => 'User Selectable Games Per Page',
		'description' => 'If you would like to allow users to select how many games per page are shown in the arcade, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many games are shown per page.',
		'optionscode' => 'text',
		'value' => '5,10,15,20,25,30,40',
		'disporder' => 21,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'scoresperpageoptions',
		'title' => 'User Selectable Scores Per Page',
		'description' => 'If you would like to allow users to select how many scores per page are shown on score pages, enter the options they should be able to select separated with commas. If this is left blank they will not be able to choose how many scores are shown per page.',
		'optionscode' => 'text',
		'value' => '5,10,15,20,25,30,40',
		'disporder' => 22,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_postbit',
		'title' => 'Display Championships On Postbit',
		'description' => $db->escape_string('Do you wish to display a user\'s championships on the postbit?'),
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 23,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'arcade_postbitlimit',
		'title' => 'Maximum Championships On Postbit',
		'description' => 'Enter the maximum number of championships that should be shown on the postbit. Enter 0 (zero) for no limit.',
		'optionscode' => 'text',
		'value' => 10,
		'disporder' => 24,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'enabletournaments',
		'title' => 'Enable Tournament Functionality',
		'description' => 'If you wish to disable the tournament feature on your board, set this option to no.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 1,
		'gid' => intval($tid)
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'tournaments_numrounds',
		'title' => 'Number Of Rounds',
		'description' => 'The user selectable number of rounds for a tournament. The higher the number, the more players can participate (2 rounds = 4 players, 4 rounds = 16 players).',
		'optionscode' => 'text',
		'value' => '1,2,3,4,5',
		'disporder' => 2,
		'gid' => intval($tid)
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'tournaments_numtries',
		'title' => 'Number Of Tries Per Round',
		'description' => 'The user selectable number of tries a player has to get the best score possible in a round.',
		'optionscode' => 'text',
		'value' => '1,2,3,4,5',
		'disporder' => 3,
		'gid' => intval($tid)
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'tournaments_numdays',
		'title' => 'Number Of Days Per Round',
		'description' => 'The user selectable number of days a single round will last.',
		'optionscode' => 'text',
		'value' => '1,2,3,4,5,6,7',
		'disporder' => 4,
		'gid' => intval($tid)
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'tournaments_canceltime',
		'title' => 'Tournament Cancel Time',
		'description' => 'The amount of time a tournament has (in days) to get players before it is cancelled.',
		'optionscode' => 'text',
		'value' => 90,
		'disporder' => 5,
		'gid' => intval($tid)
	);
	$db->insert_query("settings", $insertarray);

	rebuild_settings();

	// Insert template groups
	$insertarray = array(
		'prefix'	=>	'arcade',
		'title'		=>	'<lang:group_arcade>'
	);
	$db->insert_query("templategroups", $insertarray);

	$insertarray = array(
		'prefix'	=>	'tournaments',
		'title'		=>	'<lang:group_tournament>'
	);
	$db->insert_query("templategroups", $insertarray);

	// Inserts templates (arcade)
	require_once MYBB_ROOT."arcade/templates.php";

	foreach($arcade_templates as $title => $template)
	{
		$template_insert = array(
			'title'		=> $title,
			'template'	=> $template,
			'sid'		=> '-2',
			'version'	=> '1000',
			'dateline'	=> TIME_NOW
		);
		$db->insert_query("templates", $template_insert);
	}

	// Insert templates (global)
	$insert_array = array(
		'title'		=> 'global_arcade_bit',
		'template'	=> $db->escape_string('<a href="{$gamelink}"><img src="arcade/smallimages/{$champ[\'smallimage\']}.gif" alt="{$champ[\'name\']}" title="{$champion_of}" width="20" height="20" /></a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_arcade',
		'template'	=> $db->escape_string('<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->arcade_profile}</strong></td>
</tr>
<tr>
<td class="trow1"><strong>{$profilelink}</strong>
{$lang->total_scores} {$score_count}<br />
<br />{$champ_bit}</td>
</tr>
</table>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$signature}')."#i", '{$signature}{$arcadeprofile}');
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'user_details\']}')."#i", '{$post[\'user_details\']}<br />{$post[\'champions\']}');
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'user_details\']}')."#i", '{$post[\'user_details\']}<br />{$post[\'champions\']}');
	find_replace_templatesets("header", "#".preg_quote('<ul>')."#i", '<ul>{$arcade}');

	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$css = array(
		"name" => "arcade.css",
		"tid" => 1,
		"attachedto" => "arcade.php|tournaments.php",
		"stylesheet" => ".categories ul {\ncolor: #000000;\ntext-align: center;\npadding: 1px;\nlist-style: none;\nmargin: 0;\n}\n
.categories li {\ndisplay: inline;\nfloat: left;\n}\n
.star_rating,\n.star_rating li a:hover,\n.star_rating .current_rating {\nbackground: url(images/star_rating.gif) left -1000px repeat-x;\nvertical-align: middle;\n}\n
.star_rating {\nposition: relative;\nwidth:80px;\nheight:16px;\noverflow: hidden;\nlist-style: none;\nmargin: 0;\npadding: 0;\nbackground-position: left top;\n}\n
td .star_rating {\nmargin: auto;\n}\n
.star_rating li {\ndisplay: inline;\n}\n
.star_rating li a,\n.star_rating .current_rating {\nposition: absolute;\ntext-indent: -1000px;\nheight: 16px;\nline-height: 16px;\noutline: none;\noverflow: hidden;\nborder: none;\ntop:0;\nleft:0;\n}\n
.star_rating_notrated li a:hover {\nbackground-position: left bottom;\n}\n
.star_rating li a.one_star {\nwidth:20%;\nz-index:6;\n}\n
.star_rating li a.two_stars {\nwidth:40%;\nz-index:5;\n}\n
.star_rating li a.three_stars {\nwidth:60%;\nz-index:4;\n}\n
.star_rating li a.four_stars {\nwidth:80%;\nz-index:3;\n}\n
.star_rating li a.five_stars {\nwidth:100%;\nz-index:2;\n}\n
.star_rating .current_rating {\nz-index:1;\nbackground-position: left center;\n}\n
.star_rating_success, .success_message {\ncolor: #00b200;\nfont-weight: bold;\nfont-size: 10px;\nmargin-bottom: 10px;\n}\n
.inline_rating {\nfloat: left;\nvertical-align: middle;\npadding-right: 5px;\n}\n
.arcade_search {\nfloat: left;\npadding-left: 10px;\n}",
		"cachefile" => "arcade.css",
		"lastmodified" => TIME_NOW
	);
	$db->insert_query("themestylesheets", $css);

	cache_stylesheet(1, $css['cachefile'], $css['stylesheet']);

	$tids = $db->simple_select("themes", "tid");
	while($row = $db->fetch_array($tids))
	{
		update_theme_stylesheet_list($row['tid']);
	}

	require_once MYBB_ROOT."inc/functions_task.php";
	$arcadetask_insert = array(
		"title"			=> "Arcade Tasks",
		"description"	=> "Cleans out old arcade sessions and updates tournaments every 6 hours.",
		"file"			=> "arcade",
		"minute"		=> "0",
		"hour"			=> "0,6,12,18",
		"day"			=> "*",
		"month"			=> "*",
		"weekday"		=> "*",
		"enabled"		=> 1,
		"logging"		=> 1,
		"locked"		=> 0
	);

	$arcadetask_insert['nextrun'] = fetch_next_run($arcadetask_insert);
	$db->insert_query("tasks", $arcadetask_insert);

	change_admin_permission('arcade', 'games');
	change_admin_permission('arcade', 'categories');
	change_admin_permission('arcade', 'scores');
	change_admin_permission('arcade', 'logs');
}

// This function runs when the plugin is deactivated.
function myarcade_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('arcade','arcade_categories','arcade_category_bit','arcade_champions','arcade_champions_bit','arcade_edit','arcade_edited','arcade_edit_error','arcade_favorites','arcade_gamebit','arcade_menu','arcade_no_display','arcade_no_games','arcade_online','arcade_online_memberbit','arcade_play_rating','arcade_play','arcade_play_tournament','arcade_rating','arcade_scoreboard','arcade_scoreboard_bit','arcade_scores','arcade_scores_bit','arcade_scores_no_scores','arcade_search','arcade_search_catagory','arcade_search_results','arcade_settings','arcade_settings_gamesselect','arcade_settings_scoreselect','arcade_settings_tournamentnotify','arcade_settings_whosonline','arcade_settings_champpostbit','arcade_statistics','arcade_statistics_bestplayers','arcade_statistics_bestplayers_bit','arcade_statistics_gamebit','arcade_statistics_scorebit','arcade_stats','arcade_stats_bit','arcade_stats_tournaments','arcade_stats_details','arcade_tournaments','arcade_tournaments_create','arcade_tournaments_user','arcade_tournaments_user_game','tournaments_cancel','tournaments_cancelled','tournaments_cancel_error','tournaments_create','tournaments_finished','tournaments_finished_bit','tournaments_no_tournaments','tournaments_running','tournaments_running_bit','tournaments_view','tournaments_view_rounds','tournaments_view_rounds_bit','tournaments_view_rounds_bit_info','tournaments_view_rounds_champion','tournaments_waiting','tournaments_waiting_bit') AND sid='-2'");
	$db->delete_query("templates", "title IN('global_arcade_bit','member_profile_arcade')");
	$db->delete_query("settings", "name IN('enablearcade','arcade_stats','arcade_stats_newgames','arcade_stats_newchamps','arcade_stats_newscores','arcade_stats_bestplayers','arcade_stats_avatar','gamesperpage','gamessortby','gamesorder','arcade_category_number','arcade_newgame','arcade_ratings','arcade_searching','arcade_whosonline','arcade_onlineimage','scoresperpage','arcade_editcomment','arcade_maxcommentlength','statsperpage','gamesperpageoptions','scoresperpageoptions','arcade_postbit','arcade_postbitlimit','enabletournaments','tournaments_numrounds','tournaments_numtries','tournaments_numdays','tournaments_canceltime')");
	$db->delete_query("settinggroups", "name IN('arcade','tournaments')");
	rebuild_settings();

	$db->delete_query("templategroups", "prefix IN('arcade','tournaments')");
	$db->delete_query("themestylesheets", "name='arcade.css'");
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

	$query = $db->simple_select("themes", "tid");
	while($row = $db->fetch_array($query))
	{
		update_theme_stylesheet_list($row['tid']);
		@unlink(MYBB_ROOT."cache/themes/theme{$row['tid']}/arcade.css");
	}

	$query = $db->simple_select("tasks", "tid", "file='arcade'");
	$task = $db->fetch_array($query);

	$db->delete_query("tasks", "tid='{$task['tid']}'");
	$db->delete_query("tasklog", "tid='{$task['tid']}'");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$arcadeprofile}')."#i", '', 0);
	find_replace_templatesets("postbit", "#".preg_quote('<br />{$post[\'champions\']}')."#i", '', 0);
	find_replace_templatesets("postbit_classic", "#".preg_quote('<br />{$post[\'champions\']}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$arcade}')."#i", '', 0);

	change_admin_permission('arcade', 'games', -1);
	change_admin_permission('arcade', 'categories', -1);
	change_admin_permission('arcade', 'scores', -1);
	change_admin_permission('arcade', 'logs', -1);
}

// Insert score (for IBProArcade games)
function myarcade_index()
{
	global $mybb, $db, $lang, $arcade_session;
	$lang->load("arcade");

	if($mybb->input['act'] != "Arcade" && $mybb->input['autocom'] != "arcade")
	{
		return;
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

	require_once MYBB_ROOT."inc/functions_arcade.php";
	require_once MYBB_ROOT."inc/class_arcade.php";
	$arcade = new Arcade;

	$sid = $mybb->cookies['arcadesession'];

	$query = $db->query("
		SELECT s.*, g.gid, g.sortby
		FROM ".TABLE_PREFIX."arcadesessions s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
		WHERE s.sid='{$sid}'
	");
	$game = $db->fetch_array($query);

	$game_name = $mybb->input['gname'];
	$game_score = $mybb->input['gscore'];
	$perpage = intval($mybb->settings['scoresperpage']);

	// IBProArcade v2 insert of a score
	switch($mybb->input['act'])
	{
		case 'Arcade':
			switch($mybb->input['do'])
			{
				case 'newscore':
					if($game['tid'])
					{
						$message = $arcade->submit_tournament($game_score, $game_name, $sid);
						redirect("tournaments.php?action=view&tid={$game['tid']}", $message);
					}
					else
					{
						$message = $arcade->submit_score($game_score, $game_name, $sid);

						$rank = get_rank($mybb->user['uid'], $game['gid'], $game['sortby']);
						$pagenum = ceil($rank/$perpage);
						if($pagenum > 1)
						{
							$page = "&page={$pagenum}";
						}
						else
						$page = "";

						redirect("arcade.php?action=scores&gid={$game['gid']}&newscore=1{$page}", $message);
					}
				break;
			}
		break;
	}

	// IBProArcade v32 insert of a score
	switch($mybb->input['autocom'])
	{
		case 'arcade':
			switch($mybb->input['do'])
			{
				case 'verifyscore':
					$randchar1 = rand(1, 200);
					$randchar2 = rand(1, 200);

					$update_session = array(
						"randchar1" => $randchar1,
						"randchar2" => $randchar2
					);
					$db->update_query("arcadesessions", $update_session, "sid='{$sid}'");

					echo("&randchar=".$randchar1."&randchar2=".$randchar2."&savescore=1&blah=OK");
					exit;
				break;
				case 'savescore':
					if($game['tid'])
					{
						$message = $arcade->submit_tournament($game_score, $game_name, $sid);
						redirect("tournaments.php?action=view&tid={$game['tid']}", $message);
					}
					else
					{
						$message = $arcade->submit_score($game_score, $game_name, $sid);

						$rank = get_rank($mybb->user['uid'], $game['gid'], $game['sortby']);
						$pagenum = ceil($rank/$perpage);
						if($pagenum > 1)
						{
							$page = "&page={$pagenum}";
						}
						else
						$page = "";

						redirect("arcade.php?action=scores&gid={$game['gid']}&newscore=1{$page}", $message);
					}
				break;
				case 'newscore':
					if($game['tid'])
					{
						$message = $arcade->submit_tournament($game_score, $game_name, $sid);
						redirect("tournaments.php?action=view&tid={$game['tid']}", $message);
					}
					else
					{
						$message = $arcade->submit_score($game_score, $game_name, $sid);

						$rank = get_rank($mybb->user['uid'], $game['gid'], $game['sortby']);
						$pagenum = ceil($rank/$perpage);
						if($pagenum > 1)
						{
							$page = "&page={$pagenum}";
						}
						else
						$page = "";

						redirect("arcade.php?action=scores&gid={$game['gid']}&newscore=1{$page}", $message);
					}
				break;
			}
		break;
	}
}

// Arcade header link
function myarcade_link()
{
	global $db, $mybb, $templates, $lang, $arcade, $theme;
	$lang->load("arcade");

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1)
	{
		$arcade = "<li><a href=\"{$mybb->settings['bburl']}/arcade.php\"><img src=\"images/arcade/arcade.png\" alt=\"\" title=\"\" />{$lang->arcade}</a> </li>";
	}
}

// Gets arcade unviewable categories (for postbit display, to reduce number of queries)
function myarcade_categories()
{
	global $db, $mybb, $unviewable;
	require_once MYBB_ROOT."inc/functions_arcade.php";

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1 && $mybb->settings['arcade_postbit'] == 1 && $mybb->user['champdisplaypostbit'] == 1)
	{
		$unviewable = get_unviewable_categories($mybb->user['usergroup']);
	}
}

// Postbit list of championships (post only)
// If get_unviewable_categories function is used here, it would re-quered the database for every post visible
function myarcade_postbit_post($post)
{
	global $db, $mybb, $templates, $lang, $unviewable;
	$lang->load("arcade");
	$usergroup = user_permissions($post['uid']);

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1 && $usergroup['canviewarcade'] == 1 && $mybb->settings['arcade_postbit'] == 1 && $mybb->user['champdisplaypostbit'] == 1)
	{
		if($unviewable)
		{
			$cat_sql .= " AND g.cid NOT IN ($unviewable)";
		}

		// Championship hard limit (to prevent overloading of the postbit)
		if(!$mybb->settings['arcade_postbitlimit'] || $mybb->settings['arcade_postbitlimit'] > 100)
		{
			$mybb->settings['arcade_postbitlimit'] = 100;
		}

		$query = $db->query("
			SELECT c.*, g.active, g.name, g.smallimage
			FROM ".TABLE_PREFIX."arcadechampions c
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
			WHERE g.active ='1'AND c.uid='{$post['uid']}'{$cat_sql}
			ORDER BY c.dateline DESC
			LIMIT {$mybb->settings['arcade_postbitlimit']}
		");
		while($champ = $db->fetch_array($query))
		{
			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&gid={$champ['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&gid={$champ['gid']}";
			}

			$champion_of = $lang->sprintf($lang->champion_of, $champ['name']);
			eval("\$post['champions'] .= \"".$templates->get('global_arcade_bit')."\";");
		}
	}
	else
	{
		$post['champions'] = "";
	}

	return $post;
}

// Postbit list of championships (PMs, Announcements and preview)
function myarcade_postbit_other($post)
{
	global $db, $mybb, $templates, $lang;
	$lang->load("arcade");
	$usergroup = user_permissions($post['uid']);

	require_once MYBB_ROOT."inc/functions_arcade.php";

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1 && $usergroup['canviewarcade'] == 1 && $mybb->settings['arcade_postbit'] ==1 && $mybb->user['champdisplaypostbit'] == 1)
	{
		$unviewable = get_unviewable_categories($mybb->user['usergroup']);
		if($unviewable)
		{
			$cat_sql .= " AND g.cid NOT IN ($unviewable)";
		}

		// Championship hard limit (to prevent overloading of the postbit)
		if(!$mybb->settings['arcade_postbitlimit'] || $mybb->settings['arcade_postbitlimit'] > 100)
		{
			$mybb->settings['arcade_postbitlimit'] = 100;
		}

		$query = $db->query("
			SELECT c.*, g.active, g.name, g.smallimage
			FROM ".TABLE_PREFIX."arcadechampions c
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
			WHERE g.active ='1'AND c.uid='{$post['uid']}'{$cat_sql}
			ORDER BY c.dateline DESC
			LIMIT {$mybb->settings['arcade_postbitlimit']}
		");
		while($champ = $db->fetch_array($query))
		{
			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&gid={$champ['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&gid={$champ['gid']}";
			}

			$champion_of = $lang->sprintf($lang->champion_of, $champ['name']);
			eval("\$post['champions'] .= \"".$templates->get('global_arcade_bit')."\";");
		}
	}
	else
	{
		$post['champions'] = "";
	}

	return $post;
}

// Profile list of basic arcade stats
function myarcade_profile()
{
	global $db, $mybb, $theme, $templates, $lang, $arcadeprofile, $memprofile;
	$lang->load("arcade");
	$usergroup = user_permissions($memprofile['uid']);

	require_once MYBB_ROOT."inc/functions_arcade.php";

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1 && $usergroup['canviewarcade'] == 1)
	{
		$unviewable = get_unviewable_categories($mybb->user['usergroup']);
		if($unviewable)
		{
			$cat_sql .= " AND g.cid NOT IN ($unviewable)";
		}

		$lang->arcade_profile = $lang->sprintf($lang->arcade_profile, $memprofile['username']);

		$query = $db->query("
			SELECT c.*, g.active, g.name, g.smallimage
			FROM ".TABLE_PREFIX."arcadechampions c
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
			WHERE g.active ='1'AND c.uid='{$memprofile['uid']}'{$cat_sql}
			ORDER BY c.dateline DESC
		");
		while($champ = $db->fetch_array($query))
		{
			if($mybb->usergroup['canplayarcade'] == 1)
			{
				$gamelink = "arcade.php?action=play&gid={$champ['gid']}";
			}
			else
			{
				$gamelink = "arcade.php?action=scores&gid={$champ['gid']}";
			}

			$champion_of = $lang->sprintf($lang->champion_of, $champ['name']);
			eval("\$champ_bit .= \"".$templates->get("global_arcade_bit")."\";");
		}

		if($mybb->usergroup['canviewgamestats'] == 1)
		{
			$lang->view_game_stats = $lang->sprintf($lang->view_game_stats, $memprofile['username']);
			$profilelink = "<a href=\"arcade.php?action=stats&uid={$memprofile['uid']}\">{$lang->view_game_stats}</a><br />";
		}

		$query2 = $db->query("
			SELECT COUNT(s.sid) AS score_count, g.active, g.cid
			FROM ".TABLE_PREFIX."arcadescores s
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=s.gid)
			WHERE g.active='1' AND s.uid={$memprofile['uid']}{$cat_sql}
		");
		$score_count = $db->fetch_field($query2, "score_count");

		if($score_count > 0)
		{
			eval("\$arcadeprofile = \"".$templates->get("member_profile_arcade")."\";");
		}
	}
}

// Online activity
function myarcade_online_activity($user_activity)
{
	global $user;
	if(my_strpos($user['location'], "arcade.php?action=play") !== false)
	{
		$user_activity['activity'] = "arcade_play";
		$user_activity['gid'] = $parameters['gid'];
	}
	else if(my_strpos($user['location'], "arcade.php?action=scores") !== false)
	{
		$user_activity['activity'] = "arcade_scores";
		$user_activity['gid'] = $parameters['gid'];
	}
	else if(my_strpos($user['location'], "arcade.php?action=champions") !== false)
	{
		$user_activity['activity'] = "arcade_champions";
	}
	else if(my_strpos($user['location'], "arcade.php?action=scoreboard") !== false)
	{
		$user_activity['activity'] = "arcade_scoreboard";
	}
	else if(my_strpos($user['location'], "arcade.php?action=favorites") !== false)
	{
		$user_activity['activity'] = "arcade_favorites";
	}
	else if(my_strpos($user['location'], "arcade.php?action=settings") !== false)
	{
		$user_activity['activity'] = "arcade_settings";
	}
	else if(my_strpos($user['location'], "arcade.php?action=stats") !== false)
	{
		$user_activity['activity'] = "arcade_stats";
	}
	else if(my_strpos($user['location'], "arcade.php") !== false)
	{
		$user_activity['activity'] = "arcade_home";
	}
	else if(my_strpos($user['location'], "tournaments.php?action=view") !== false)
	{
		$user_activity['activity'] = "tournaments_view";
	}
	else if(my_strpos($user['location'], "tournaments.php?action=create") !== false)
	{
		$user_activity['activity'] = "tournaments_create";
	}
	else if(my_strpos($user['location'], "tournaments.php?action=waiting") !== false)
	{
		$user_activity['activity'] = "tournaments_waiting";
	}
	else if(my_strpos($user['location'], "tournaments.php?action=running") !== false)
	{
		$user_activity['activity'] = "tournaments_running";
	}
	else if(my_strpos($user['location'], "tournaments.php?action=finished") !== false)
	{
		$user_activity['activity'] = "tournaments_finished";
	}

	return $user_activity;
}

function myarcade_online_location($plugin_array)
{
    global $db, $mybb, $lang, $parameters;
	$lang->load("arcade");

	$query = $db->simple_select("arcadegames", "gid, name", "gid='{$parameters['gid']}'");
	$online = $db->fetch_array($query);

	if($plugin_array['user_activity']['activity'] == "arcade_play")
	{
		$plugin_array['location_name'] = $lang->sprintf($lang->playing_game, $online['gid'], $online['name']);
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_scores")
	{
		$plugin_array['location_name'] = $lang->sprintf($lang->viewing_scores, $online['gid'], $online['name']);
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_champions")
	{
		$plugin_array['location_name'] = $lang->viewing_champions;
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_scoreboard")
	{
		$plugin_array['location_name'] = $lang->viewing_scoreboard;
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_favorites")
	{
		$plugin_array['location_name'] = $lang->viewing_arcade_favorites;
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_settings")
	{
		$plugin_array['location_name'] = $lang->updating_arcade_settings;
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_stats")
	{
		$plugin_array['location_name'] = $lang->viewing_arcade_stats;
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_home")
	{
		$plugin_array['location_name'] = $lang->viewing_arcade_home;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_view")
	{
		$plugin_array['location_name'] = $lang->viewing_a_tournament;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_create")
	{
		$plugin_array['location_name'] = $lang->creating_a_tournament;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_waiting")
	{
		$plugin_array['location_name'] = $lang->viewing_waiting_tournaments;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_running")
	{
		$plugin_array['location_name'] = $lang->viewing_running_tournaments;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_finished")
	{
		$plugin_array['location_name'] = $lang->viewing_finished_tournaments;
	}

	return $plugin_array;
}

// Update user's username if changed
function myarcade_user_update(&$user)
{
	global $old_user, $db;
	if($user->user_update_data['username'] != $old_user['username'] && $user->user_update_data['username'] != '')
	{
		$username_update = array(
			"username" => $user->user_update_data['username']
		);

		$db->update_query("arcadechampions", $username_update, "uid='{$user->uid}'");
		$db->update_query("arcadescores", $username_update, "uid='{$user->uid}'");
		$db->update_query("arcadetournamentplayers", $username_update, "uid='{$user->uid}'");
	}
}

// Show template group language in template list
function myarcade_templates()
{
	global $lang;
	$lang->load("arcade_module_meta");
}

// Merge everything if users are merged
function myarcade_merge()
{
    global $db, $mybb, $source_user, $destination_user;
	$username = array(
		"username" => $destination_user['username']
	);
	$db->update_query("arcadechampions", $username, "uid='{$source_user['uid']}'");
	$db->update_query("arcadescores", $username, "uid='{$source_user['uid']}'");
	$db->update_query("arcadetournamentplayers", $username, "uid='{$source_user['uid']}'");

	$uid = array(
		"uid" => $destination_user['uid']
	);
	$db->update_query("arcadechampions", $uid, "uid='{$source_user['uid']}'");
	$db->update_query("arcadescores", $uid, "uid='{$source_user['uid']}'");
	$db->update_query("arcaderatings", $uid, "uid='{$source_user['uid']}'");
	$db->update_query("arcadefavorites", $uid, "uid='{$source_user['uid']}'");
	$db->update_query("arcadelogs", $uid, "uid='{$source_user['uid']}'");
	$db->update_query("arcadesessions", $uid, "uid='{$source_user['uid']}'");
	$db->update_query("arcadetournamentplayers", $uid, "uid='{$source_user['uid']}'");

	$last_player = array(
		"lastplayeduid" => $destination_user['uid']
	);
	$db->update_query("arcadegames", $last_player, "lastplayeduid='{$source_user['uid']}'");

	$champion = array(
		"champion" => $destination_user['uid']
	);
	$db->update_query("arcadetournaments", $champion, "champion='{$source_user['uid']}'");
}

// Delete everything if user is deleted
function myarcade_delete()
{
	global $db, $mybb, $user;
	require_once MYBB_ROOT."inc/functions_arcade.php";

	// Update game ratings
	$query = $db->query("
		SELECT r.*, g.numratings, g.totalratings
		FROM ".TABLE_PREFIX."arcaderatings r
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=r.gid)
		WHERE r.uid='{$user['uid']}'
	");
	while($rating = $db->fetch_array($query))
	{
		$update_game = array(
			"numratings" => $rating['numratings'] - 1,
			"totalratings" => $rating['totalratings'] - $rating['rating']
		);
		$db->update_query("arcadegames", $update_game, "gid='{$rating['gid']}'");
	}

	$db->delete_query("arcadescores", "uid='{$user['uid']}'");
	$db->delete_query("arcadefavorites", "uid='{$user['uid']}'");
	$db->delete_query("arcadesessions", "uid='{$user['uid']}'");
	$db->delete_query("arcaderatings", "uid='{$user['uid']}'");
	$db->delete_query("arcadelogs", "uid='{$user['uid']}'");

	// Update game champion
	$query = $db->simple_select("arcadechampions", "gid", "uid='{$user['uid']}'");
	while($champion = $db->fetch_array($query))
	{
		update_champion($champion['gid']);
	}
}

// Usergroup permissions
function myarcade_usergroups_permission($tabs)
{
	global $lang;
	$lang->load("arcade_module_meta");

	$tabs['arcade'] = $lang->arcade;
	return $tabs;
}

function myarcade_usergroups_graph()
{
	global $lang, $form, $mybb, $plugins;
	$lang->load("arcade_module_meta");

	echo "<div id=\"tab_arcade\">";	
	$form_container = new FormContainer($lang->arcade);

	$arcade_options = array(
		$form->generate_check_box("canviewarcade", 1, $lang->can_view_arcade, array("checked" => $mybb->input['canviewarcade'])),
		$form->generate_check_box("cansearchgames", 1, $lang->can_search_arcade, array("checked" => $mybb->input['cansearchgames'])),
		$form->generate_check_box("canviewgamestats", 1, $lang->view_other_game_stats."<br />\n<small>{$lang->view_other_game_stats_desc}</small>", array("checked" => $mybb->input['canviewgamestats'])),
		$form->generate_check_box("canmoderategames", 1, $lang->can_moderate_games, array("checked" => $mybb->input['canmoderategames']))
	);
	$form_container->output_row($lang->general_arcade, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $arcade_options)."</div>");

	$game_options = array(
		$form->generate_check_box("canplayarcade", 1, $lang->can_play_games, array("checked" => $mybb->input['canplayarcade'])),
		$form->generate_check_box("canrategames", 1, $lang->can_rate_games, array("checked" => $mybb->input['canrategames'])),
		"{$lang->max_plays_day}:<br /><small>{$lang->max_plays_day_desc}</small><br />".$form->generate_text_box('maxplaysday', $mybb->input['maxplaysday'], array('id' => 'maxplaysday', 'class' => 'field50'))
	);
	$form_container->output_row($lang->games, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $game_options)."</div>");

	$tournaments_options = array(
		$form->generate_check_box("canviewtournaments", 1, $lang->can_view_tournaments, array("checked" => $mybb->input['canviewtournaments'])),
		$form->generate_check_box("canjointournaments", 1, $lang->can_join_tournaments, array("checked" => $mybb->input['canjointournaments'])),
		$form->generate_check_box("cancreatetournaments", 1, $lang->can_create_tournaments, array("checked" => $mybb->input['cancreatetournaments']))
	);
	$form_container->output_row($lang->tournaments, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $tournaments_options)."</div>");

	$plugins->run_hooks("admin_user_groups_edit_arcade", $form_container);

	$form_container->end();
	echo "</div>";
}

function myarcade_usergroups_commit()
{
	global $updated_group, $mybb;
	$updated_group['canviewarcade'] = intval($mybb->input['canviewarcade']);
	$updated_group['canplayarcade'] = intval($mybb->input['canplayarcade']);
	$updated_group['maxplaysday'] = intval($mybb->input['maxplaysday']);
	$updated_group['canrategames'] = intval($mybb->input['canrategames']);
	$updated_group['cansearchgames'] = intval($mybb->input['cansearchgames']);
	$updated_group['canviewgamestats'] = intval($mybb->input['canviewgamestats']);
	$updated_group['canmoderategames'] = intval($mybb->input['canmoderategames']);
	$updated_group['canviewtournaments'] = intval($mybb->input['canviewtournaments']);
	$updated_group['canjointournaments'] = intval($mybb->input['canjointournaments']);
	$updated_group['cancreatetournaments'] = intval($mybb->input['cancreatetournaments']);
}

// Rebuild arcade caches in Admin CP
function myarcade_datacache_class()
{
	global $cache;
	require_once MYBB_ROOT."inc/functions_arcade.php";

	if(class_exists('MyDatacache'))
	{
		class ArcadeDatacache extends MyDatacache
		{
			function update_tournaments_stats()
			{
				update_tournaments_stats();
			}

			function reload_arcade_mostonline()
			{
				global $db, $cache;

				$query = $db->simple_select("datacache", "title,cache", "title='arcade_mostonline'");
				$cache->update("arcade_mostonline", @unserialize($db->fetch_field($query, "cache")));
			}
		}

		$cache = null;
		$cache = new ArcadeDatacache;
	}
	else
	{
		class MyDatacache extends datacache
		{
			function update_tournaments_stats()
			{
				update_tournaments_stats();
			}

			function reload_arcade_mostonline()
			{
				global $db, $cache;

				$query = $db->simple_select("datacache", "title,cache", "title='arcade_mostonline'");
				$cache->update("arcade_mostonline", @unserialize($db->fetch_field($query, "cache")));
			}
		}

		$cache = null;
		$cache = new MyDatacache;
	}
}

// Admin Log display
function myarcade_admin_adminlog($plugin_array)
{
  	global $lang;
	$lang->load("arcade_module_meta");

	if($plugin_array['lang_string'] == admin_log_arcade_scores_prune)
	{
		if($plugin_array['logitem']['data'][1] && !$plugin_array['logitem']['data'][2])
		{
			$plugin_array['lang_string'] = admin_log_arcade_scores_prune_user;
		}
		elseif($plugin_array['logitem']['data'][2] && !$plugin_array['logitem']['data'][1])
		{
			$plugin_array['lang_string'] = admin_log_arcade_scores_prune_game;
		}
		elseif($plugin_array['logitem']['data'][1] && $plugin_array['logitem']['data'][2])
		{
			$plugin_array['lang_string'] = admin_log_arcade_scores_prune_user_game;
		}
	}

	else if($plugin_array['lang_string'] == admin_log_arcade_logs_prune)
	{
		if($plugin_array['logitem']['data'][1] && !$plugin_array['logitem']['data'][2])
		{
			$plugin_array['lang_string'] = admin_log_arcade_logs_prune_user;
		}
		elseif($plugin_array['logitem']['data'][2] && !$plugin_array['logitem']['data'][1])
		{
			$plugin_array['lang_string'] = admin_log_arcade_logs_prune_game;
		}
		elseif($plugin_array['logitem']['data'][1] && $plugin_array['logitem']['data'][2])
		{
			$plugin_array['lang_string'] = admin_log_arcade_logs_prune_user_game;
		}
	}

	return $plugin_array;
}

?>