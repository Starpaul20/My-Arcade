<?php
/**
 * My Arcade
 * Copyright 2013 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function arcade_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "games", "title" => $lang->games, "link" => "index.php?module=arcade-games");
	$sub_menu['20'] = array("id" => "categories", "title" => $lang->categories, "link" => "index.php?module=arcade-categories");
	$sub_menu['30'] = array("id" => "scores", "title" => $lang->scores, "link" => "index.php?module=arcade-scores");
	$sub_menu['40'] = array("id" => "logs", "title" => $lang->logs, "link" => "index.php?module=arcade-logs");

	$sub_menu = $plugins->run_hooks("admin_arcade_menu", $sub_menu);

	$page->add_menu_item($lang->arcade, "arcade", "index.php?module=arcade", 100, $sub_menu);

	return true;
}

function arcade_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = "arcade";

	$actions = array(
		'games' => array('active' => 'games', 'file' => 'games.php'),
		'categories' => array('active' => 'categories', 'file' => 'categories.php'),
		'scores' => array('active' => 'scores', 'file' => 'scores.php'),
		'logs' => array('active' => 'logs', 'file' => 'logs.php')
	);

	$actions = $plugins->run_hooks("admin_arcade_action_handler", $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "games";
		return "games.php";
	}
}

function arcade_admin_permissions()
{
	global $lang, $plugins;

	$admin_permissions = array(
		"games" => $lang->can_manage_games,
		"categories" => $lang->can_manage_categories,
		"scores" => $lang->can_manage_scores,
		"logs" => $lang->can_manage_logs,
	);

	$admin_permissions = $plugins->run_hooks("admin_arcade_permissions", $admin_permissions);

	return array("name" => $lang->arcade, "permissions" => $admin_permissions, "disporder" => 100);
}

?>