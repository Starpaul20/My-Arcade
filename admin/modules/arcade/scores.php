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

require_once MYBB_ROOT."inc/functions_arcade.php";

$page->add_breadcrumb_item($lang->scores, "index.php?module=arcade-scores");

$sub_tabs['scores'] = array(
	'title' => $lang->scores,
	'link' => "index.php?module=arcade-scores",
	'description' => $lang->scores_desc
);

$sub_tabs['prune_scores'] = array(
	'title' => $lang->prune_scores,
	'link' => "index.php?module=arcade-scores&amp;action=prune",
	'description' => $lang->prune_scores_desc
);

$plugins->run_hooks("admin_arcade_scores_begin");

if($mybb->input['action'] == 'prune')
{
	$plugins->run_hooks("admin_arcade_scores_prune");

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

		$db->delete_query("arcadescores", $where);
		$num_deleted = $db->affected_rows();

		$plugins->run_hooks("admin_arcade_scores_prune_commit");

		// Update game champion
		$query = $db->simple_select("arcadechampions", "gid", "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
		while($champion = $db->fetch_array($query))
		{
			update_champion($champion['gid']);
		}

		// Log admin action
		log_admin_action($mybb->input['older_than'], $mybb->input['uid'], $mybb->input['gid'], $num_deleted);

		$success = $lang->success_pruned_scores;
		if($is_today == true && $num_deleted > 0)
		{
			$success .= ' '.$lang->note_scores_locked;
		}
		elseif($is_today == true && $num_deleted == 0)
		{
			flash_message($lang->note_scores_locked, 'error');
			admin_redirect("index.php?module=arcade-scores");
		}
		flash_message($success, 'success');
		admin_redirect("index.php?module=arcade-scores");
	}

	$page->add_breadcrumb_item($lang->prune_scores, "index.php?module=arcade-scores&amp;action=prune");
	$page->output_header($lang->prune_scores);
	$page->output_nav_tabs($sub_tabs, 'prune_scores');

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = 'selected="selected"';
	$ordersel[$mybb->input['order']] = 'selected="selected"';

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';
	
	$query = $db->query("
		SELECT DISTINCT s.uid, u.username
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
	}

	$game_options[''] = $lang->all_games;
	$game_options['0'] = '----------';

	$query2 = $db->query("
		SELECT DISTINCT s.gid, g.name
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
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

	$form = new Form("index.php?module=arcade-scores&amp;action=prune", "post");
	$form_container = new FormContainer($lang->prune_scores);
	$form_container->output_row($lang->filter_username, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');	
	$form_container->output_row($lang->filter_game_name, "", $form->generate_select_box('gid', $game_options, $mybb->input['gid'], array('id' => 'gid')), 'gid');
	if(!$mybb->input['older_than'])
	{
		$mybb->input['older_than'] = '90';
	}
	$form_container->output_row($lang->date_range, "", $lang->older_than.$form->generate_numeric_field('older_than', $mybb->input['older_than'], array('id' => 'older_than', 'style' => 'width: 50px', 'min' => 0)).' '.$lang->days, 'older_than');
	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->prune_scores);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == 'edit')
{
	$query = $db->simple_select("arcadescores", "*", "sid='".$mybb->get_input('sid', MyBB::INPUT_INT)."'");
	$score = $db->fetch_array($query);

	if(!$score['sid'])
	{
		flash_message($lang->error_invalid_score, 'error');
		admin_redirect("index.php?module=arcade-scores");
	}

	if($mybb->request_method == "post")
	{
		if(my_strlen($mybb->input['comment']) > $mybb->settings['arcade_maxcommentlength'])
		{
			$errors[] = $lang->sprintf($lang->error_score_comment_too_long, $mybb->settings['arcade_maxcommentlength']);
		}

		if(!$errors)
		{
			$updated_score = array(
				'comment' => $db->escape_string($mybb->input['comment'])
			);
			$db->update_query("arcadescores", $updated_score, "sid='{$score['sid']}'");

			// Log admin action
			log_admin_action($score['sid'], $score['uid']);

			flash_message($lang->success_score_updated, 'success');
			admin_redirect('index.php?module=arcade-scores');
		}
	}

	$page->add_breadcrumb_item($lang->edit_score);
	$page->output_header($lang->scores." - ".$lang->edit_score);

	$sub_tabs['edit_score'] = array(
		'title'	=> $lang->edit_score,
		'link'	=> "index.php?module=arcade-scores",
		'description'	=> $lang->edit_score_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_score');

	$form = new Form("index.php?module=arcade-scores&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("sid", $score['sid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $score;
	}

	$form_container = new FormContainer($lang->edit_score);
	$form_container->output_row($lang->comment, "", $form->generate_text_area('comment', $mybb->input['comment'], array('id' => 'comment', 'maxlength' => '255')), 'comment');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->edit_score);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == 'delete')
{
	$query = $db->simple_select("arcadescores", "*", "sid='".$mybb->get_input('sid', MyBB::INPUT_INT)."'");
	$score = $db->fetch_array($query);

	if(!$score['sid'])
	{
		flash_message($lang->error_invalid_score, 'error');
		admin_redirect("index.php?module=arcade-scores");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=arcade-scores");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("arcadescores", "sid='{$score['sid']}'");

		update_champion($score['gid']);

		// Log admin action
		log_admin_action($score['sid'], $score['uid']);

		flash_message($lang->success_score_deleted, 'success');
		admin_redirect("index.php?module=arcade-scores");
	}
	else
	{
		$page->output_confirm_action("index.php?module=arcade-scores&amp;action=delete&amp;sid={$score['sid']}", $lang->confirm_score_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_arcade_scores_start");

	$page->output_header($lang->scores);

	$page->output_nav_tabs($sub_tabs, 'scores');

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
		$where .= " AND s.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries for a specific game
	if($mybb->input['gid'] > 0)
	{
		$where .= " AND s.gid='".$mybb->get_input('gid', MyBB::INPUT_INT)."'";
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
			$sortby = "s.dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(s.dateline) AS count
		FROM ".TABLE_PREFIX."arcadescores s
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
	$table->construct_header($lang->game, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->score, array("class" => "align_center", 'width' => '5%'));
	$table->construct_header($lang->comment, array("class" => "align_center"));
	$table->construct_header($lang->date, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->ipaddress, array("class" => "align_center", 'width' => '10%'));
	$table->construct_header($lang->controls, array('class' => "align_center", 'colspan' => 2, "width" => "180"));

	$query = $db->query("
		SELECT s.*, u.username, u.usergroup, u.displaygroup, g.name
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (g.gid=s.gid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($logitem = $db->fetch_array($query))
	{
		$logitem['comment'] = htmlspecialchars_uni($logitem['comment']);
		$logitem['dateline'] = my_date('relative', $logitem['dateline']);
		$trow = alt_trow();

		$logitem['username'] = htmlspecialchars_uni($logitem['username']);
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		$logitem['game'] = "<a href=\"../arcade.php?action=scores&gid={$logitem['gid']}\" target=\"_blank\">".htmlspecialchars_uni($logitem['name'])."</a>";

		$table->construct_cell($logitem['profilelink']);
		$table->construct_cell($logitem['game']);
		$table->construct_cell($logitem['score'], array("class" => "align_center"));
		$table->construct_cell($logitem['comment']);
		$table->construct_cell($logitem['dateline'], array("class" => "align_center"));
		$table->construct_cell(my_inet_ntop($db->unescape_binary($logitem['ipaddress'])), array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=arcade-scores&amp;action=edit&amp;sid={$logitem['sid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '90'));
		$table->construct_cell("<a href=\"index.php?module=arcade-scores&amp;action=delete&amp;sid={$logitem['sid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_score_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_scores, array("colspan" => "8"));
		$table->construct_row();
	}

	$table->output($lang->scores);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=arcade-scores&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;gid={$mybb->input['gid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT s.uid, u.username
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
	}

	$game_options[''] = $lang->all_games;
	$game_options['0'] = '----------';

	$query2 = $db->query("
		SELECT DISTINCT s.gid, g.name
		FROM ".TABLE_PREFIX."arcadescores s
		LEFT JOIN ".TABLE_PREFIX."arcadegames g ON (s.gid=g.gid)
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

	$form = new Form("index.php?module=arcade-scores", "post");
	$form_container = new FormContainer($lang->filter_scores);
	$form_container->output_row($lang->filter_username, "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');	
	$form_container->output_row($lang->filter_game_name, "", $form->generate_select_box('gid', $game_options, $mybb->input['gid'], array('id' => 'gid')), 'gid');	
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');	
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_scores);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
