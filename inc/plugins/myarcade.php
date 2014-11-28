<?php
/**
 * My Arcade
 * Copyright 2014 Starpaul20
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

if(my_strpos($_SERVER['PHP_SELF'], 'private.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'global_arcade_bit';
}

if(my_strpos($_SERVER['PHP_SELF'], 'announcements.php'))
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
$plugins->add_hook("global_start", "myarcade_link_cache");
$plugins->add_hook("global_intermediate", "myarcade_link");
$plugins->add_hook("showthread_start", "myarcade_categories");
$plugins->add_hook("newreply_do_newreply_start", "myarcade_categories");
$plugins->add_hook("postbit", "myarcade_postbit_post");
$plugins->add_hook("postbit_pm", "myarcade_postbit_other");
$plugins->add_hook("postbit_announcement", "myarcade_postbit_other");
$plugins->add_hook("postbit_prev", "myarcade_postbit_other");
$plugins->add_hook("member_profile_end", "myarcade_profile");
$plugins->add_hook("online_start", "myarcade_online_unviewable");
$plugins->add_hook("fetch_wol_activity_end", "myarcade_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "myarcade_online_location");
$plugins->add_hook("datahandler_user_update", "myarcade_user_update");
$plugins->add_hook("datahandler_user_delete_content", "myarcade_delete");

$plugins->add_hook("myalerts_load_lang", "myarcade_load_lang");
$plugins->add_hook("myalerts_alerts_output_start", "myarcade_alerts_output");
$plugins->add_hook("admin_config_plugins_activate_commit", "myarcade_alerts_activate");

$plugins->add_hook("admin_style_templates_set", "myarcade_templates");
$plugins->add_hook("admin_user_users_merge_commit", "myarcade_merge");
$plugins->add_hook("admin_user_users_edit_graph_tabs", "myarcade_user_options");
$plugins->add_hook("admin_user_users_edit_graph", "myarcade_user_graph");
$plugins->add_hook("admin_user_users_edit_commit_start", "myarcade_user_commit");
$plugins->add_hook("admin_user_groups_edit_graph_tabs", "myarcade_usergroups_permission");
$plugins->add_hook("admin_user_groups_edit_graph", "myarcade_usergroups_graph");
$plugins->add_hook("admin_user_groups_edit_commit", "myarcade_usergroups_commit");
$plugins->add_hook("admin_tools_cache_begin", "myarcade_datacache_class");
$plugins->add_hook("admin_tools_get_admin_log_action", "myarcade_admin_adminlog");

// The information that shows up on the plugin manager
function myarcade_info()
{
	global $lang;
	$lang->load("arcade_module_meta");

	return array(
		"name"				=> $lang->myarcade_info_name,
		"description"		=> $lang->myarcade_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"compatibility"		=> "18*"
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
				plays int(10) unsigned NOT NULL default '0',
				lastplayed int unsigned NOT NULL default '0',
				lastplayeduid int(10) unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0',
				bgcolor varchar(6) NOT NULL default '',
				width varchar(4) NOT NULL default '',
				height varchar(4) NOT NULL default '',
				sortby varchar(10) NOT NULL default 'desc',
				numratings smallint(5) unsigned NOT NULL default '0',
				totalratings smallint(5) unsigned NOT NULL default '0',
				tournamentselect tinyint(1) NOT NULL default '1',
				active tinyint(1) NOT NULL default '1',
				KEY cid (cid),
				PRIMARY KEY(gid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadecategories (
				cid smallint(5) unsigned NOT NULL auto_increment,
				name varchar(50) NOT NULL default '',
				image varchar(200) NOT NULL default '',
				groups text NOT NULL,
				active tinyint(1) NOT NULL default '1',
				PRIMARY KEY(cid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadechampions (
				cid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				score float NOT NULL,
				dateline int unsigned NOT NULL default '0',
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
				dateline int unsigned NOT NULL default '0',
				gid int(10) unsigned NOT NULL default '0',
				tid int(10) unsigned NOT NULL default '0',
				action text NOT NULL,
				data text NOT NULL,
				ipaddress varbinary(16) NOT NULL,
				KEY gid (gid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcaderatings (
				rid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				rating smallint(5) unsigned NOT NULL default '0',
				ipaddress varbinary(16) NOT NULL default '',
				KEY gid (gid, uid),
				PRIMARY KEY(rid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadescores (
				sid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				username varchar(120) NOT NULL default '',
				score float NOT NULL,
				dateline int unsigned NOT NULL default '0',
				timeplayed int(10) unsigned NOT NULL default '0',
				comment varchar(200) NOT NULL default '',
				ipaddress varbinary(16) NOT NULL default '',
				KEY gid (gid),
				KEY uid (uid),
				PRIMARY KEY(sid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadesessions (
				sid varchar(32) NOT NULL,
				uid int(10) unsigned NOT NULL default '0',
				gid int(10) unsigned NOT NULL default '0',
				tid int(10) unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0',
				randchar1 varchar(100) NOT NULL default '',
				randchar2 varchar(100) NOT NULL default '',
				gname varchar(40) NOT NULL default '',
				gtitle varchar(50) NOT NULL default '',
				ipaddress varbinary(16) NOT NULL default '',
				KEY uid (uid)
			) ENGINE=MyISAM{$collation}");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."arcadetournaments (
				tid int(10) unsigned NOT NULL auto_increment,
				gid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0',
				status tinyint(1) NOT NULL default '1',
				rounds tinyint(2) unsigned NOT NULL default '0',
				tries tinyint(2) unsigned NOT NULL default '0',
				numplayers smallint(5) unsigned NOT NULL default '1',
				days tinyint(2) unsigned NOT NULL default '0',
				round tinyint(2) unsigned NOT NULL default '0',
				champion int(10) unsigned NOT NULL default '0',
				finishdateline int unsigned NOT NULL default '0',
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
				round tinyint(2) unsigned NOT NULL default '0',
				attempts smallint(5) unsigned NOT NULL default '0',
				scoreattempt tinyint(2) unsigned NOT NULL default '0',
				timeplayed int unsigned NOT NULL default '0',
				status tinyint(1) NOT NULL default '1',
				KEY tid (tid),
				KEY uid (uid),
				PRIMARY KEY(pid)
			) ENGINE=MyISAM{$collation}");

	$db->add_column("users", "gamesperpage", "smallint(6) NOT NULL default '0'");
	$db->add_column("users", "scoresperpage", "smallint(6) NOT NULL default '0'");
	$db->add_column("users", "gamessortby", "varchar(10) NOT NULL default ''");
	$db->add_column("users", "gamesorder", "varchar(4) NOT NULL default ''");
	$db->add_column("users", "whosonlinearcade", "tinyint(1) NOT NULL default '1'");
	$db->add_column("users", "champdisplaypostbit", "tinyint(1) NOT NULL default '1'");
	$db->add_column("users", "tournamentnotify", "tinyint(1) NOT NULL default '0'");
	$db->add_column("users", "champnotify", "tinyint(1) NOT NULL default '0'");

	$db->add_column("usergroups", "canviewarcade", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canplayarcade", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "maxplaysday", "int(3) NOT NULL default '25'");
	$db->add_column("usergroups", "canmoderategames", "tinyint(1) NOT NULL default '0'");
	$db->add_column("usergroups", "canrategames", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "cansearchgames", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canviewgamestats", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canviewtournaments", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canjointournaments", "tinyint(1) NOT NULL default '1'");
	$db->add_column("usergroups", "cancreatetournaments", "tinyint(1) NOT NULL default '0'");
	$db->add_column("usergroups", "maxtournamentsday", "int(3) NOT NULL default '2'");

	// Setting some basic arcade permissions...
	$update_array = array(
		"maxplaysday" => 0,
		"canmoderategames" => 1,
		"cancreatetournaments" => 1,
		"maxtournamentsday" => 0
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
		"canjointournaments" => 0,
		"maxtournamentsday" => 0
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

	if($db->field_exists("maxtournamentsday", "usergroups"))
	{
		$db->drop_column("usergroups", "maxtournamentsday");
	}

	$cache->update_usergroups();

	$cache->delete('tournaments_stats');
	$cache->delete('arcade_mostonline');

	$db->delete_query("templates", "title LIKE 'arcade_%'");
	$db->delete_query("templates", "title LIKE 'tournaments_%'");
	$db->delete_query("templates", "title='arcade'"); // The wildcard deletion above misses this template
	$db->delete_query("templates", "title IN('global_arcade_bit','member_profile_arcade','header_menu_arcade')");

	// MyAlerts support
	if($db->table_exists("alerts"))
	{
		$db->delete_query("alerts", "alert_type IN('arcade_champship','arcade_newround')");
	}
}

// This function runs when the plugin is activated.
function myarcade_activate()
{
	global $db;
	require_once MYBB_ROOT."inc/class_xml.php";

	$settings = @file_get_contents(MYBB_ROOT.'inc/plugins/arcade/settings.xml');
	$parser = new XMLParser($settings);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	// Insert settings
	foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
	{
		$groupdata = array(
			'name' => $db->escape_string($settinggroup['attributes']['name']),
			'title' => $db->escape_string($settinggroup['attributes']['title']),
			'description' => $db->escape_string($settinggroup['attributes']['description']),
			'disporder' => (int)$settinggroup['attributes']['disporder'],
			'isdefault' => $settinggroup['attributes']['isdefault'],
		);
		$gid = $db->insert_query('settinggroups', $groupdata);
		foreach($settinggroup['setting'] as $setting)
		{
			$settingdata = array(
				'name' => $db->escape_string($setting['attributes']['name']),
				'title' => $db->escape_string($setting['title'][0]['value']),
				'description' => $db->escape_string($setting['description'][0]['value']),
				'optionscode' => $db->escape_string($setting['optionscode'][0]['value']),
				'value' => $db->escape_string($setting['settingvalue'][0]['value']),
				'disporder' => (int)$setting['disporder'][0]['value'],
				'gid' => $gid,
				'isdefault' => 0
			);
			$db->insert_query('settings', $settingdata);
		}
	}

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
	$contents = @file_get_contents(MYBB_ROOT.'inc/plugins/arcade/templates.xml');
	require_once MYBB_ADMIN_DIR."inc/functions.php";
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !is_array($tree['theme']))
	{
		return -1;
	}

	$theme = $tree['theme'];

	$templates = $theme['templates']['template'];
	if(is_array($templates))
	{
		// Theme only has one custom template
		if(array_key_exists("attributes", $templates))
		{
			$templates = array($templates);
		}
	}

	foreach($templates as $template)
	{
		// PostgreSQL causes apache to stop sending content sometimes and
		// causes the page to stop loading during many queries all at one time
		if($db->engine == "pgsql")
		{
			echo " ";
			flush();
		}

		$db->delete_query("templates", "title='{$template['attributes']['name']}'");

		$new_template = array(
			"title" => $db->escape_string($template['attributes']['name']),
			"template" => $db->escape_string($template['value']),
			"sid" => -2,
			"version" => $db->escape_string($template['attributes']['version']),
			"dateline" => TIME_NOW
		);
		$db->insert_query("templates", $new_template);
	}

	// If we have any stylesheets, process them
	if(!empty($theme['stylesheets']['stylesheet']) && empty($options['no_stylesheets']))
	{
		// Are we dealing with a single stylesheet?
		if(isset($theme['stylesheets']['stylesheet']['tag']))
		{
			// Trick the system into thinking we have a good array =P
			$theme['stylesheets']['stylesheet'] = array($theme['stylesheets']['stylesheet']);
		}

		$loop = 1;
		foreach($theme['stylesheets']['stylesheet'] as $stylesheet)
		{
			if(substr($stylesheet['attributes']['name'], -4) != ".css")
			{
				continue;
			}

			if(empty($stylesheet['attributes']['lastmodified']))
			{
				$stylesheet['attributes']['lastmodified'] = TIME_NOW;
			}

			if(empty($stylesheet['attributes']['disporder']))
			{
				$stylesheet['attributes']['disporder'] = $loop;
			}

			if(empty($stylesheet['attributes']['attachedto']))
			{
				$stylesheet['attributes']['attachedto'] = '';
			}

			$new_stylesheet = array(
				"name" => $db->escape_string($stylesheet['attributes']['name']),
				"tid" => 1,
				"attachedto" => $db->escape_string($stylesheet['attributes']['attachedto']),
				"stylesheet" => $db->escape_string($stylesheet['value']),
				"lastmodified" => (int)$stylesheet['attributes']['lastmodified'],
				"cachefile" => $db->escape_string($stylesheet['attributes']['name'])
			);
			$sid = $db->insert_query("themestylesheets", $new_stylesheet);
			$css_url = "css.php?stylesheet={$sid}";
			$cached = cache_stylesheet(1, $stylesheet['attributes']['name'], $stylesheet['value']);
			if($cached)
			{
				$css_url = $cached;
			}

			$attachedto = $stylesheet['attributes']['attachedto'];
			if(!$attachedto)
			{
				$attachedto = "global";
			}

			// private.php?compose,folders|usercp.php,global|global
			$attachedto = explode("|", $attachedto);
			foreach($attachedto as $attached_file)
			{
				$attached_actions = explode(",", $attached_file);
				$attached_file = array_shift($attached_actions);
				if(count($attached_actions) == 0)
				{
					$attached_actions = array("global");
				}

				foreach($attached_actions as $action)
				{
					$theme_stylesheets[$attached_file][$action][] = $css_url;
				}
			}

			++$loop;
		}
		// Now we have our list of built stylesheets, save them
		$updated_theme = array(
			"stylesheets" => $db->escape_string(serialize($theme_stylesheets))
		);
		$db->update_query("themes", $updated_theme, "tid='1'");

		$query = $db->simple_select("themes", "*");
		while($theme = $db->fetch_array($query))
		{
			update_theme_stylesheet_list($theme['tid'], $theme, true);
		}
	}

	// Insert templates (global)
	$insert_array = array(
		'title'		=> 'global_arcade_bit',
		'template'	=> $db->escape_string('<a href="arcade.php?action=scores&gid={$champ[\'gid\']}"><img src="arcade/smallimages/{$champ[\'smallimage\']}.gif" alt="{$champ[\'name\']}" title="{$champion_of}" width="20" height="20" /></a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_arcade',
		'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->arcade_profile}</strong></td>
</tr>
<tr>
<td class="trow1"><strong>{$profilelink}</strong>
{$lang->total_scores} {$score_count}<br />
<br />{$champ_bit}</td>
</tr>
</table>
<br />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'header_menu_arcade',
		'template'	=> $db->escape_string('<li><a href="{$mybb->settings[\'bburl\']}/arcade.php" style="padding-left: 20px; background-image: url(images/arcade/arcade.png); background-repeat: no-repeat; display: inline-block;">{$lang->arcade}</a></li>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	// Update templates
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$signature}')."#i", '{$signature}{$arcadeprofile}');
	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'user_details\']}')."#i", '{$post[\'user_details\']}<br />{$post[\'champions\']}');
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'user_details\']}')."#i", '{$post[\'user_details\']}<br />{$post[\'champions\']}');
	find_replace_templatesets("header", "#".preg_quote('<ul class="menu top_links">')."#i", '<ul class="menu top_links">{$menu_arcade}');

	// Inserts arcade task
	require_once MYBB_ROOT."inc/functions_task.php";
	$arcadetask_insert = array(
		"title"			=> "Arcade Cleanup",
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

	// Change Admin CP permissions
	change_admin_permission('arcade', 'games');
	change_admin_permission('arcade', 'categories');
	change_admin_permission('arcade', 'scores');
	change_admin_permission('arcade', 'logs');

	// MyAlerts support
	if($db->table_exists("alert_settings"))
	{
		$insertarray = array(
			'code'		=>	'arcade_champship'
		);
		$db->insert_query("alert_settings", $insertarray);

		$insertarray = array(
			'code'		=>	'arcade_newround'
		);
		$db->insert_query("alert_settings", $insertarray);
	}
}

// This function runs when the plugin is deactivated.
function myarcade_deactivate()
{
	global $db;

	$db->delete_query("templates", "title LIKE 'arcade_%' AND sid='-2'");
	$db->delete_query("templates", "title LIKE 'tournaments_%' AND sid='-2'");
	$db->delete_query("templates", "title='arcade' AND sid='-2'"); // The wildcard deletion above misses this template
	$db->delete_query("templates", "title IN('global_arcade_bit','member_profile_arcade','header_menu_arcade')");
	$db->delete_query("settings", "name IN('enablearcade','arcade_stats','arcade_stats_newgames','arcade_stats_newchamps','arcade_stats_newscores','arcade_stats_bestplayers','arcade_stats_avatar','gamesperpage','gamessortby','gamesorder','arcade_category_number','arcade_newgame','arcade_ratings','arcade_searching','arcade_whosonline','arcade_onlineimage','scoresperpage','arcade_editcomment','arcade_maxcommentlength','statsperpage','gamesperpageoptions','scoresperpageoptions','arcade_postbit','arcade_postbitlimit','enabletournaments','tournaments_numrounds','tournaments_numtries','tournaments_numdays','tournaments_canceltime','myalerts_alert_arcade_newround','myalerts_alert_arcade_champship')");
	$db->delete_query("settinggroups", "name IN('arcade','tournaments')");
	rebuild_settings();

	$db->delete_query("templategroups", "prefix IN('arcade','tournaments')");
	$db->delete_query("themestylesheets", "name='arcade.css'");
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

	$query = $db->simple_select("themes", "*");
	while($theme = $db->fetch_array($query))
	{
		update_theme_stylesheet_list($theme['tid'], $theme, true);
		@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/arcade.css");
		@unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/arcade.min.css");
	}

	$query = $db->simple_select("tasks", "tid", "file='arcade'");
	$task = $db->fetch_array($query);

	$db->delete_query("tasks", "tid='{$task['tid']}'");
	$db->delete_query("tasklog", "tid='{$task['tid']}'");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$arcadeprofile}')."#i", '', 0);
	find_replace_templatesets("postbit", "#".preg_quote('<br />{$post[\'champions\']}')."#i", '', 0);
	find_replace_templatesets("postbit_classic", "#".preg_quote('<br />{$post[\'champions\']}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$menu_arcade}')."#i", '', 0);

	change_admin_permission('arcade', 'games', -1);
	change_admin_permission('arcade', 'categories', -1);
	change_admin_permission('arcade', 'scores', -1);
	change_admin_permission('arcade', 'logs', -1);

	// MyAlerts support
	if($db->table_exists("alert_settings"))
	{
		$db->delete_query("alert_settings", "code IN('arcade_champship','arcade_newround')");
	}
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

	if(!$mybb->settings['scoresperpage'] || (int)$mybb->settings['scoresperpage'] < 1)
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
	$perpage = $mybb->settings['scoresperpage'];

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

						$page = "";
						if($pagenum > 1)
						{
							$page = "&page={$pagenum}";
						}

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

						$page = "";
						if($pagenum > 1)
						{
							$page = "&page={$pagenum}";
						}

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

						$page = "";
						if($pagenum > 1)
						{
							$page = "&page={$pagenum}";
						}

						redirect("arcade.php?action=scores&gid={$game['gid']}&newscore=1{$page}", $message);
					}
				break;
			}
		break;
	}
}

// Cache the header link template
function myarcade_link_cache()
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'header_menu_arcade';
}

// Arcade header link
function myarcade_link()
{
	global $db, $mybb, $templates, $lang, $menu_arcade;
	$lang->load("arcade");

	$menu_arcade = '';
	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1)
	{
		eval('$menu_arcade = "'.$templates->get('header_menu_arcade').'";');
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
	global $db, $mybb, $templates, $lang, $unviewable, $champ_cache, $pids, $champnum, $champs;
	$lang->load("arcade");
	$usergroup = user_permissions($post['uid']);

	if($mybb->user['uid'] == 0)
	{
		$champdisplaypostbit = 1;
	}
	else
	{
		$champdisplaypostbit = $mybb->user['champdisplaypostbit'];
	}

	$champnum = 0;
	$post['champions'] = "";
	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1 && $usergroup['canviewarcade'] == 1 && $mybb->settings['arcade_postbit'] == 1 && $champdisplaypostbit == 1)
	{
		// Championship hard limit (to prevent overloading of the postbit)
		if(!$mybb->settings['arcade_postbitlimit'] || $mybb->settings['arcade_postbitlimit'] > 50)
		{
			$mybb->settings['arcade_postbitlimit'] = 50;
		}

		if(!empty($mybb->input['ajax']) || $mybb->input['mode'] == "threaded")
		{
			if($unviewable)
			{
				$cat_sql .= " AND g.cid NOT IN ($unviewable)";
			}

			$query = $db->query("
				SELECT c.gid, c.uid, g.active, g.name, g.smallimage
				FROM ".TABLE_PREFIX."arcadechampions c
				LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
				WHERE g.active ='1' AND c.uid='{$post['uid']}'{$cat_sql}
				ORDER BY c.dateline DESC
				LIMIT {$mybb->settings['arcade_postbitlimit']}
			");
			while($champ = $db->fetch_array($query))
			{
				$champion_of = $lang->sprintf($lang->champion_of, $champ['name']);
				eval("\$post['champions'] .= \"".$templates->get('global_arcade_bit')."\";");
			}
		}
		else
		{
			$categories = explode(",", $unviewable);
			if(!$champ_cache)
			{
				$champ_cache = true;

				$query = $db->query("
					SELECT p.pid, c.gid, c.uid, g.active, g.name, g.smallimage, g.cid
					FROM ".TABLE_PREFIX."arcadechampions c
					LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
					LEFT JOIN ".TABLE_PREFIX."posts p ON (p.uid=c.uid)
					WHERE {$pids}
					ORDER BY c.dateline DESC
				");
				while($value = $db->fetch_array($query))
				{
					$champs[] = $value;
				}
			}

			if(is_array($champs))
			{
				foreach($champs as $champ)
				{
					if($champ['pid'] == $post['pid'] && $champ['active'] == 1 && $champnum < $mybb->settings['arcade_postbitlimit'] && !in_array($champ['cid'], $categories))
					{
						$champion_of = $lang->sprintf($lang->champion_of, $champ['name']);
						eval("\$post['champions'] .= \"".$templates->get('global_arcade_bit')."\";");
						++$champnum;
					}
				}
			}
		}
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

	if($mybb->user['uid'] == 0)
	{
		$champdisplaypostbit = 1;
	}
	else
	{
		$champdisplaypostbit = $mybb->user['champdisplaypostbit'];
	}

	$post['champions'] = "";
	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1 && $usergroup['canviewarcade'] == 1 && $mybb->settings['arcade_postbit'] == 1 && $champdisplaypostbit == 1)
	{
		$unviewable = get_unviewable_categories($mybb->user['usergroup']);
		if($unviewable)
		{
			$cat_sql .= " AND g.cid NOT IN ($unviewable)";
		}

		// Championship hard limit (to prevent overloading of the postbit)
		if(!$mybb->settings['arcade_postbitlimit'] || $mybb->settings['arcade_postbitlimit'] > 50)
		{
			$mybb->settings['arcade_postbitlimit'] = 50;
		}

		$query = $db->query("
			SELECT c.*, g.active, g.name, g.smallimage
			FROM ".TABLE_PREFIX."arcadechampions c
			LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (c.gid=g.gid)
			WHERE g.active ='1' AND c.uid='{$post['uid']}'{$cat_sql}
			ORDER BY c.dateline DESC
			LIMIT {$mybb->settings['arcade_postbitlimit']}
		");
		while($champ = $db->fetch_array($query))
		{
			$champion_of = $lang->sprintf($lang->champion_of, $champ['name']);
			eval("\$post['champions'] .= \"".$templates->get('global_arcade_bit')."\";");
		}
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

	$arcadeprofile = "";
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

// Get unviewable games (for who's online, to reduce number of queries)
function myarcade_online_unviewable()
{
	global $db, $mybb, $unviewable;
	require_once MYBB_ROOT."inc/functions_arcade.php";

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1)
	{
		$unviewable = get_unviewable_categories($mybb->user['usergroup']);
	}
}

// Online activity
function myarcade_online_activity($user_activity)
{
	global $user, $gid_list, $parameters;

	$gid_list = array();

	$split_loc = explode(".php", $user_activity['location']);
	if($split_loc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch($filename)
	{
		case "arcade":
			if($parameters['action'] == "play")
			{
				if(is_numeric($parameters['gid']))
				{
					$gid_list[] = $parameters['gid'];
				}
				$user_activity['activity'] = "arcade_play";
				$user_activity['gid'] = $parameters['gid'];
			}
			elseif($parameters['action'] == "scores")
			{
				if(is_numeric($parameters['gid']))
				{
					$gid_list[] = $parameters['gid'];
				}
				$user_activity['activity'] = "arcade_scores";
				$user_activity['gid'] = $parameters['gid'];
			}
			elseif($parameters['action'] == "settings" || $parameters['action'] == "do_settings")
			{
				$user_activity['activity'] = "arcade_settings";
			}
			elseif($parameters['action'] == "champions")
			{
				$user_activity['activity'] = "arcade_champions";
			}
			elseif($parameters['action'] == "scoreboard")
			{
				$user_activity['activity'] = "arcade_scoreboard";
			}
			elseif($parameters['action'] == "favorites" || $parameters['action'] == "addfavorite" || $parameters['action'] == "removefavorite")
			{
				$user_activity['activity'] = "arcade_favorites";
			}
			elseif($parameters['action'] == "stats")
			{
				$user_activity['activity'] = "arcade_stats";
			}
			elseif($parameters['action'] == "results" || $parameters['action'] == "do_search")
			{
				$user_activity['activity'] = "arcade_search";
			}
			else
			{
				$user_activity['activity'] = "arcade_home";
			}
			break;
		case "tournaments":
			if($parameters['action'] == "create" || $parameters['action'] == "do_create")
			{
				$user_activity['activity'] = "tournaments_create";
			}
			elseif($parameters['action'] == "view")
			{
				$user_activity['activity'] = "tournaments_view";
			}
			elseif($parameters['action'] == "join")
			{
				$user_activity['activity'] = "tournaments_join";
			}
			elseif($parameters['action'] == "waiting")
			{
				$user_activity['activity'] = "tournaments_waiting";
			}
			elseif($parameters['action'] == "running")
			{
				$user_activity['activity'] = "tournaments_running";
			}
			elseif($parameters['action'] == "finished")
			{
				$user_activity['activity'] = "tournaments_finished";
			}
			else
			{
				$user_activity['activity'] = "arcade_home";
			}
			break;
	}

	return $user_activity;
}

function myarcade_online_location($plugin_array)
{
	global $db, $mybb, $lang, $parameters, $unviewable, $gid_list, $games;
	$lang->load("arcade");

	if($unviewable)
	{
		$unview = "AND cid NOT IN ($unviewable)";
	}

	// Fetch any games
	if(!is_array($games) && count($gid_list) > 0)
	{
		$gid_sql = implode(",", $gid_list);
		$query = $db->simple_select("arcadegames", "gid, name", "gid IN ($gid_sql) {$unview}");
		while($game = $db->fetch_array($query))
		{
			$games[$game['gid']] = htmlspecialchars_uni($game['name']);
		}
	}

	if($plugin_array['user_activity']['activity'] == "arcade_play")
	{
		if($games[$parameters['gid']])
		{
			$plugin_array['location_name'] = $lang->sprintf($lang->playing_game2, $plugin_array['user_activity']['gid'], $games[$parameters['gid']]);
		}
		else
		{
			$plugin_array['location_name'] = $lang->playing_game;
		}
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_scores")
	{
		if($games[$parameters['gid']])
		{
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_scores2, $plugin_array['user_activity']['gid'], $games[$parameters['gid']]);
		}
		else
		{
			$plugin_array['location_name'] = $lang->viewing_scores;
		}
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
	else if($plugin_array['user_activity']['activity'] == "arcade_search")
	{
		$plugin_array['location_name'] = $lang->searching_arcade;
	}
	else if($plugin_array['user_activity']['activity'] == "arcade_home")
	{
		$plugin_array['location_name'] = $lang->viewing_arcade_home;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_view")
	{
		$plugin_array['location_name'] = $lang->viewing_tournament;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_create")
	{
		$plugin_array['location_name'] = $lang->creating_tournament;
	}
	else if($plugin_array['user_activity']['activity'] == "tournaments_join")
	{
		$plugin_array['location_name'] = $lang->joining_tournament;
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

// Delete everything if user is deleted
function myarcade_delete($delete)
{
	global $db;
	require_once MYBB_ROOT."inc/functions_arcade.php";

	// Update game ratings
	$query = $db->query("
		SELECT r.*, g.numratings, g.totalratings
		FROM ".TABLE_PREFIX."arcaderatings r
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=r.gid)
		WHERE r.uid IN({$delete->delete_uids})
	");
	while($rating = $db->fetch_array($query))
	{
		$update_game = array(
			"numratings" => $rating['numratings'] - 1,
			"totalratings" => $rating['totalratings'] - $rating['rating']
		);
		$db->update_query("arcadegames", $update_game, "gid='{$rating['gid']}'");
	}

	$db->delete_query('arcadescores', 'uid IN('.$delete->delete_uids.')');
	$db->delete_query('arcadefavorites', 'uid IN('.$delete->delete_uids.')');
	$db->delete_query('arcadesessions', 'uid IN('.$delete->delete_uids.')');
	$db->delete_query('arcaderatings', 'uid IN('.$delete->delete_uids.')');
	$db->delete_query('arcadelogs', 'uid IN('.$delete->delete_uids.')');

	// Update game champion
	$query = $db->simple_select("arcadechampions", "gid", "uid IN({$delete->delete_uids})");
	while($champion = $db->fetch_array($query))
	{
		update_champion($champion['gid']);
	}

	return $delete;
}

// Add MyAlerts settings
function myarcade_load_lang()
{
	global $lang;
	if(!$lang->arcade)
	{
		$lang->load('arcade');
	}
}

// Add MyAlerts alert output
function myarcade_alerts_output(&$alert)
{
	global $mybb, $lang;
	$lang->load("arcade");

	if($mybb->settings['enablearcade'] == 1 && $mybb->usergroup['canviewarcade'] == 1)
	{
		if($alert['alert_type'] == 'arcade_champship' AND $mybb->settings['myalerts_alert_arcade_champship'] AND $mybb->user['myalerts_settings']['arcade_champship'])
		{
			$alert['gamelink'] = $mybb->settings['bburl']."/arcade.php?action=scores&gid={$alert['content']['gid']}";
			$alert['message'] = $lang->sprintf($lang->myalerts_arcade_champship_alert, $alert['user'], $alert['gamelink'], htmlspecialchars_uni($alert['content']['name']), $alert['dateline']);
			$alert['rowType'] = 'champshipAlert';
		}

		if($alert['alert_type'] == 'arcade_newround' AND $mybb->settings['myalerts_alert_arcade_newround'] AND $mybb->user['myalerts_settings']['arcade_newround'])
		{
			$alert['tournamentlink'] = $mybb->settings['bburl']."/tournaments.php?action=view&tid={$alert['content']['tid']}";
			$alert['message'] = $lang->sprintf($lang->myalerts_arcade_new_round, $alert['tournamentlink'], htmlspecialchars_uni($alert['content']['name']), $alert['dateline']);
			$alert['rowType'] = 'roundAlert';
		}
	}
}

// MyAlerts actvation support - if MyAlerts is added after My Arcade, this will add the settings.
function myarcade_alerts_activate()
{
	global $db, $codename;

	if($codename == 'myalerts')
	{
		$insertarray = array(
			'code'		=>	'arcade_champship'
		);
		$db->insert_query("alert_settings", $insertarray);

		$insertarray = array(
			'code'		=>	'arcade_newround'
		);
		$db->insert_query("alert_settings", $insertarray);
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
	$db->update_query("arcadetournaments", $uid, "uid='{$source_user['uid']}'");
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

// Edit user options in Admin CP
function myarcade_user_options($tabs)
{
	global $lang;
	$lang->load("arcade_module_meta");

	$tabs['arcade'] = $lang->arcade;
	return $tabs;
}

function myarcade_user_graph()
{
	global $lang, $form, $mybb, $plugins, $user;
	$lang->load("arcade_module_meta");

	echo "<div id=\"tab_arcade\">";
	$form_container = new FormContainer($lang->arcade.": {$user['username']}");

	$gpp_options = array($lang->use_default);
	if($mybb->settings['gamesperpageoptions'])
	{
		$explodedgames = explode(",", $mybb->settings['gamesperpageoptions']);
		if(is_array($explodedgames))
		{
			foreach($explodedgames as $gpp)
			{
				if($gpp <= 0) continue;
				$gpp_options[$gpp] = $gpp;
			}
		}
	}

	$spp_options = array($lang->use_default);
	if($mybb->settings['scoresperpageoptions'])
	{
		$explodedgames = explode(",", $mybb->settings['scoresperpageoptions']);
		if(is_array($explodedgames))
		{
			foreach($explodedgames as $spp)
			{
				if($spp <= 0) continue;
				$spp_options[$spp] = $spp;
			}
		}
	}

	$game_sort_options = array(
		'' => $lang->use_default,
		'name' => $lang->name,
		'date' => $lang->date_added,
		'plays' => $lang->times_played,
		'lastplayed' => $lang->last_played,
		'rating' => $lang->rating
	);

	$game_order_options = array(
		'' => $lang->use_default,
		'asc' => $lang->ascending,
		'desc' => $lang->descending
	);

	$games_scores_options = array(
		"<label for=\"gamesperpage\">{$lang->games_per_page}:</label><br />".$form->generate_select_box("gamesperpage", $gpp_options, $mybb->input['gamesperpage'], array('id' => 'gamesperpage')),
		"<label for=\"scoresperpage\">{$lang->scores_per_page}:</label><br />".$form->generate_select_box("scoresperpage", $spp_options, $mybb->input['scoresperpage'], array('id' => 'scoresperpage')),
		"<label for=\"gamessortby\">{$lang->sort_games_by}:</label><br />".$form->generate_select_box("gamessortby", $game_sort_options, $mybb->input['gamessortby'], array('id' => 'gamessortby')),
		"<label for=\"gamesorder\">{$lang->sort_order}:</label><br />".$form->generate_select_box("gamesorder", $game_order_options, $mybb->input['gamesorder'], array('id' => 'gamesorder'))
	);
	$form_container->output_row($lang->games_scores_options, "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $games_scores_options)."</div>");

	$notify_options = array(
		0 => $lang->do_not_notify,
		1 => $lang->notify_via_pm,
		2 => $lang->notify_via_email
	);

	$general_options = array(
		$form->generate_check_box("whosonlinearcade", 1, $lang->whos_online_arcade, array("checked" => $mybb->input['whosonlinearcade'])),
		$form->generate_check_box("champdisplaypostbit", 1, $lang->champ_display_postbit, array("checked" => $mybb->input['champdisplaypostbit'])),
		"<label for=\"champnotify\">{$lang->champ_notify}</label><br />".$form->generate_select_box("champnotify", $notify_options, $mybb->input['champnotify'], array('id' => 'champnotify')),
		"<label for=\"tournamentnotify\">{$lang->tournament_notify}</label><br />".$form->generate_select_box("tournamentnotify", $notify_options, $mybb->input['tournamentnotify'], array('id' => 'tournamentnotify'))
	);
	$form_container->output_row($lang->general_options, "", "<div class=\"user_settings_bit\">".implode("</div><div class=\"user_settings_bit\">", $general_options)."</div>");

	$plugins->run_hooks("admin_user_users_edit_arcade", $form_container);

	$form_container->end();
	echo "</div>";
}

function myarcade_user_commit()
{
	global $db, $extra_user_updates, $mybb;
	$extra_user_updates['gamesperpage'] = (int)$mybb->input['gamesperpage'];
	$extra_user_updates['scoresperpage'] = (int)$mybb->input['scoresperpage'];
	$extra_user_updates['gamessortby'] = $db->escape_string($mybb->input['gamessortby']);
	$extra_user_updates['gamesorder'] = $db->escape_string($mybb->input['gamesorder']);
	$extra_user_updates['whosonlinearcade'] = (int)$mybb->input['whosonlinearcade'];
	$extra_user_updates['champdisplaypostbit'] = (int)$mybb->input['champdisplaypostbit'];
	$extra_user_updates['champnotify'] = (int)$mybb->input['champnotify'];
	$extra_user_updates['tournamentnotify'] = (int)$mybb->input['tournamentnotify'];
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
		$form->generate_check_box("cancreatetournaments", 1, $lang->can_create_tournaments, array("checked" => $mybb->input['cancreatetournaments'])),
		"{$lang->max_tournaments_day}:<br /><small>{$lang->max_tournaments_day_desc}</small><br />".$form->generate_text_box('maxtournamentsday', $mybb->input['maxtournamentsday'], array('id' => 'maxtournamentsday', 'class' => 'field50'))
	);
	$form_container->output_row($lang->tournaments, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $tournaments_options)."</div>");

	$plugins->run_hooks("admin_user_groups_edit_arcade", $form_container);

	$form_container->end();
	echo "</div>";
}

function myarcade_usergroups_commit()
{
	global $updated_group, $mybb;
	$updated_group['canviewarcade'] = (int)$mybb->input['canviewarcade'];
	$updated_group['canplayarcade'] = (int)$mybb->input['canplayarcade'];
	$updated_group['maxplaysday'] = (int)$mybb->input['maxplaysday'];
	$updated_group['canrategames'] = (int)$mybb->input['canrategames'];
	$updated_group['cansearchgames'] = (int)$mybb->input['cansearchgames'];
	$updated_group['canviewgamestats'] = (int)$mybb->input['canviewgamestats'];
	$updated_group['canmoderategames'] = (int)$mybb->input['canmoderategames'];
	$updated_group['canviewtournaments'] = (int)$mybb->input['canviewtournaments'];
	$updated_group['canjointournaments'] = (int)$mybb->input['canjointournaments'];
	$updated_group['cancreatetournaments'] = (int)$mybb->input['cancreatetournaments'];
	$updated_group['maxtournamentsday'] = (int)$mybb->input['maxtournamentsday'];
}

// Rebuild arcade caches in Admin CP
function myarcade_datacache_class()
{
	global $cache;
	require_once MYBB_ROOT."inc/functions_arcade.php";
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

	elseif($plugin_array['lang_string'] == admin_log_arcade_logs_prune)
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