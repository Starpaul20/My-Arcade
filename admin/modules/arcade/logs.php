<?php
/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->arcade_logs, "index.php?module=arcade-logs");

$sub_tabs['arcade_logs'] = array(
	'title' => $lang->arcade_logs,
	'link' => "index.php?module=arcade-logs",
	'description' => $lang->arcade_logs_desc
);

$sub_tabs['prune_arcade_logs'] = array(
	'title' => $lang->prune_arcade_logs,
	'link' => "index.php?module=arcade-logs&amp;action=prune",
	'description' => $lang->prune_arcade_logs_desc
);

$plugins->run_hooks("admin_arcade_logs_begin");

if($mybb->input['action'] == 'prune')
{
	$plugins->run_hooks("admin_arcade_logs_prune");

	if($mybb->request_method == 'post')
	{
		$is_today = false;
		$mybb->input['older_than'] = $mybb->get_input('older_than', MyBB::INPUT_INT);
		if($mybb->input['older_than'] <= 0)
		{
			$is_today = true;
			$mybb->input['older_than'] = 1;
		}
		$where = 'dateline < '.(TIME_NOW-($mybb->input['older_than']*86400));

		// Searching for entries by a particular user
		if($mybb->input['uid'])
		{
			$where .= " AND uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
		}

		// Searching for entries for a specific game
		if($mybb->input['gid'])
		{
			$where .= " AND gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'";
		}

		$db->delete_query("arcadelogs", $where);
		$num_deleted = $db->affected_rows();

		$plugins->run_hooks("admin_arcade_logs_prune_commit");

		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['uid'], $mybb->input['gid'], $num_deleted);

		$success = $lang->success_pruned_arcade_logs;
		if($is_today == true && $num_deleted > 0)
		{
			$success .= ' '.$lang->note_logs_locked;
		}
		elseif($is_today == true && $num_deleted == 0)
		{
			flash_message($lang->note_logs_locked, 'error');
			admin_redirect("index.php?module=arcade-logs");
		}
		flash_message($success, 'success');
		admin_redirect("index.php?module=arcade-logs");
	}

	$page->add_breadcrumb_item($lang->prune_arcade_logs, "index.php?module=arcade-logs&amp;action=prune");
	$page->output_header($lang->prune_arcade_logs);
	$page->output_nav_tabs($sub_tabs, 'prune_arcade_logs');

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."arcadelogs l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = $user['username'];
	}

	$game_options[''] = $lang->all_games;
	$game_options['0'] = '----------';

	$query2 = $db->query("
		SELECT DISTINCT l.gid, g.name
		FROM ".TABLE_PREFIX."arcadelogs l
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (l.gid=g.gid)
		ORDER BY g.name ASC
	");
	while($game = $db->fetch_array($query2))
	{
		$selected = '';
		if($mybb->input['gid'] == $game['gid'])
		{
			$selected = "selected=\"selected\"";
		}
		$game_options[$game['gid']] = $game['name'];
	}

	$form = new Form("index.php?module=arcade-logs&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_arcade_logs);
	$form_container->output_row($lang->filter_username, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');	
	$form_container->output_row($lang->filter_game_name, "", $form->generate_select_box('gid', $game_options, $mybb->input['gid'], array('id' => 'gid')), 'gid');
	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '30';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_numeric_field('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 50px', 'min' => 0)).' '.$lang->days, 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_arcade_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_arcade_logs_start");

	$page->output_header($lang->arcade_logs);

	$page->output_nav_tabs($sub_tabs, 'arcade_logs');

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage)
	{
		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = 'WHERE 1=1';

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND l.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries for specific game
	if($mybb->input['gid'] > 0)
	{
		$where .= " AND l.gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		case "game":
			$sortby = "g.name";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."arcadelogs l
		{$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}

	$table = new Table;
	$table->construct_header($lang->username, array('width' => '10%'));
	$table->construct_header($lang->date, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->action, array("class" => "align_center", 'width' => '35%'));
	$table->construct_header($lang->information, array("class" => "align_center", 'width' => '30%'));
	$table->construct_header($lang->ipaddress, array("class" => "align_center", 'width' => '10%'));

	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, g.name AS gamename
		FROM ".TABLE_PREFIX."arcadelogs l
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=l.gid)
		LEFT JOIN ".TABLE_PREFIX."arcadetournaments t ON (t.gid=l.gid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['action'] = htmlspecialchars_uni($logitem['action']);
		$logitem['dateline'] = my_date('relative', $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		if($logitem['gid'])
		{
			$information = "<strong>{$lang->game}</strong> <a href=\"{$mybb->settings['bburl']}/arcade.php?action=scores&gid={$logitem['gid']}\" target=\"_blank\">".htmlspecialchars_uni($logitem['gamename'])."</a><br />";
		}

		if($logitem['tid'])
		{
			$information .= "<strong>{$lang->tournament}</strong> <a href=\"{$mybb->settings['bburl']}/tournaments.php?action=view&tid={$logitem['tid']}\" target=\"_blank\">{$lang->view}</a><br />";
		}

		$data = unserialize($logitem['data']);
		if($data['uid'])
		{
			$information .= "<strong>{$lang->user}</strong> <a href=\"".get_profile_link($data['uid'])."\" target=\"_blank\">".htmlspecialchars_uni($data['username'])."</a>";
		}

		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['dateline'], array("class" => "align_center"));
		$table->construct_cell($logitem['action'], array("class" => "align_center"));
		$table->construct_cell($information);
		$table->construct_cell(my_inet_ntop($db->unescape_binary($logitem['ipaddress'])), array("class" => "align_center"));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_arcade_logs, array("colspan" => "5"));
		$table->construct_row();
	}

	$table->output($lang->arcade_logs);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=arcade-logs&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;gid={$mybb->input['gid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."arcadelogs l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = $user['username'];
	}

	$game_options[''] = $lang->all_games;
	$game_options['0'] = '----------';

	$query2 = $db->query("
		SELECT DISTINCT l.gid, g.name
		FROM ".TABLE_PREFIX."arcadelogs l
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (l.gid=g.gid)
		ORDER BY g.name ASC
	");
	while($game = $db->fetch_array($query2))
	{
		$selected = '';
		if($mybb->input['gid'] == $game['gid'])
		{
			$selected = "selected=\"selected\"";
		}
		$game_options[$game['gid']] = $game['name'];
	}

	$sort_by = array(
		'dateline' => $lang->date,
		'username' => $lang->username,
		'game' => $lang->game_name
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=arcade-logs", "post");
	$form_container = new FormContainer($lang->filter_arcade_logs);
	$form_container->output_row($lang->filter_username, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');	
	$form_container->output_row($lang->filter_game_name, "", $form->generate_select_box('gid', $game_options, $mybb->input['gid'], array('id' => 'gid')), 'gid');	
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');	
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_arcade_logs);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

?>