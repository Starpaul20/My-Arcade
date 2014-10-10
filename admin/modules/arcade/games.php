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

require_once MYBB_ROOT."inc/functions_upload.php";

$page->add_breadcrumb_item($lang->games, "index.php?module=arcade-games");

$plugins->run_hooks("admin_arcade_games_begin");

if($mybb->input['action'] == "add_simple")
{
	$plugins->run_hooks("admin_arcade_games_add_simple");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!trim($mybb->input['about']))
		{
			$errors[] = $lang->error_missing_about;
		}

		if(!trim($mybb->input['controls']))
		{
			$errors[] = $lang->error_missing_controls;
		}

		if(!file_exists(MYBB_ROOT."arcade/swf/".$mybb->input['file'].".swf"))
		{
			$errors[] = $lang->error_invalid_swf_file;
		}

		if(!file_exists(MYBB_ROOT."arcade/largeimages/".$mybb->input['largeimage'].".gif"))
		{
			$errors[] = $lang->error_invalid_large_image;
		}

		if(!file_exists(MYBB_ROOT."arcade/smallimages/".$mybb->input['smallimage'].".gif"))
		{
			$errors[] = $lang->error_invalid_small_image;
		}

		$query = $db->simple_select("arcadegames", "file", "file='{$mybb->input['file']}'");
		$file = $db->fetch_array($query);

		if($file['file'])
		{
			$errors[] = $lang->error_swf_already_used;
		}

		if($mybb->input['cid'] != 0)
		{
			$query = $db->simple_select("arcadecategories", "cid", "cid='{$mybb->input['cid']}'");
			$category = $db->fetch_array($query);

			if(!$category['cid'])
			{
				$errors[] = $lang->error_category_does_not_exist;
			}
		}

		if(!$errors)
		{
			$new_game = array(
				"name" => $db->escape_string($mybb->input['name']),
				"description" => $db->escape_string($mybb->input['description']),
				"about" => $db->escape_string($mybb->input['about']),
				"controls" => $db->escape_string($mybb->input['controls']),
				"file" => $db->escape_string($mybb->input['file']),
				"smallimage" => $db->escape_string($mybb->input['smallimage']),
				"largeimage" => $db->escape_string($mybb->input['largeimage']),
				"cid" => (int)$mybb->input['cid'],
				"dateline" => TIME_NOW,
				"bgcolor" => $db->escape_string($mybb->input['bgcolor']),
				"width" => (int)$mybb->input['width'],
				"height" => (int)$mybb->input['height'],
				"sortby" => $db->escape_string($mybb->input['sortby']),
				"tournamentselect" => (int)$mybb->input['tournamentselect'],
				"active" => (int)$mybb->input['active']
			);
			$gid = $db->insert_query("arcadegames", $new_game);

			$plugins->run_hooks("admin_arcade_games_add_simple_commit");

			// Log admin action
			log_admin_action($gid, $mybb->input['name']);

			flash_message($lang->success_game_added, 'success');
			admin_redirect("index.php?module=arcade-games");
		}
	}
	$page->add_breadcrumb_item($lang->add_new_game_simple);
	$page->output_header($lang->games." - ".$lang->add_new_game_simple);

	$sub_tabs['games'] = array(
		'title' => $lang->games,
		'link' => "index.php?module=arcade-games",
	);

	$sub_tabs['add_game_simple'] = array(
		'title' => $lang->add_game_simple,
		'link' => "index.php?module=arcade-games&amp;action=add_simple",
		'description' => $lang->add_game_simple_desc
	);

	$sub_tabs['add_game_tar'] = array(
		'title' => $lang->add_game_tar,
		'link' => "index.php?module=arcade-games&amp;action=add_tar",
	);

	$page->output_nav_tabs($sub_tabs, 'add_game_simple');
	$form = new Form("index.php?module=arcade-games&amp;action=add_simple", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['active'] = 1;
		$mybb->input['tournamentselect'] = 1;
		$mybb->input['bgcolor'] = "000000";
		$mybb->input['width'] = 500;
		$mybb->input['height'] = 500;
	}
	$form_container = new FormContainer($lang->add_new_game_simple);
	$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->description." <em>*</em>", "", $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->about." <em>*</em>", $lang->about_desc, $form->generate_text_area('about', $mybb->input['about'], array('id' => 'about')), 'about');
	$form_container->output_row($lang->game_controls." <em>*</em>", $lang->game_controls_desc, $form->generate_text_area('controls', $mybb->input['controls'], array('id' => 'controls')), 'controls');

	$swf_list = array();
	$swf_files = scandir(MYBB_ROOT."arcade/swf/");
	foreach($swf_files as $swf_file)
	{
		if(is_file(MYBB_ROOT."arcade/swf/{$swf_file}") && get_extension($swf_file) == "swf")
		{
			$swf_file_id = preg_replace("#\.".get_extension($swf_file)."$#i", "$1", $swf_file);
			$swf_list[$swf_file_id] = $swf_file;
		}
	}
	$form_container->output_row($lang->swf_file." <em>*</em>", $lang->swf_file_desc, $form->generate_select_box("file", $swf_list, $mybb->input['file'], array('id' => 'file')), 'file');

	$small_list = array();
	$small_files = scandir(MYBB_ROOT."arcade/smallimages/");
	foreach($small_files as $small_file)
	{
		if(is_file(MYBB_ROOT."arcade/smallimages/{$small_file}") && get_extension($small_file) == "gif")
		{
			$small_file_id = preg_replace("#\.".get_extension($small_file)."$#i", "$1", $small_file);
			$small_list[$small_file_id] = $small_file;
		}
	}
	$form_container->output_row($lang->small_image." <em>*</em>", $lang->small_image_desc, $form->generate_select_box("smallimage", $small_list, $mybb->input['smallimage'], array('id' => 'smallimage')), 'smallimage');

	$large_list = array();
	$large_files = scandir(MYBB_ROOT."arcade/largeimages/");
	foreach($large_files as $large_file)
	{
		if(is_file(MYBB_ROOT."arcade/largeimages/{$large_file}") && get_extension($large_file) == "gif")
		{
			$large_file_id = preg_replace("#\.".get_extension($large_file)."$#i", "$1", $large_file);
			$large_list[$large_file_id] = $large_file;
		}
	}
	$form_container->output_row($lang->large_image." <em>*</em>", $lang->large_image_desc, $form->generate_select_box("largeimage", $large_list, $mybb->input['largeimage'], array('id' => 'largeimage')), 'largeimage');

	$categories['0'] = $lang->no_category;

	$query = $db->simple_select("arcadecategories", "cid, name", "active='1'", array("order_by" => "name", "order_dir" => "asc"));
	while($category = $db->fetch_array($query))
	{
		$categories[$category['cid']] = $category['name'];
	}
	$form_container->output_row($lang->category, "", $form->generate_select_box("cid", $categories, $mybb->input['cid'], array('id' => 'cid')), 'cid');

	$form_container->output_row($lang->bg_color, "", $form->generate_text_box('bgcolor', $mybb->input['bgcolor'], array('id' => 'bgcolor')), 'bgcolor');
	$form_container->output_row($lang->width, "", $form->generate_text_box('width', $mybb->input['width'], array('id' => 'width')), 'width');
	$form_container->output_row($lang->height, "", $form->generate_text_box('height', $mybb->input['height'], array('id' => 'height')), 'height');

	$sort_by = array(
		'desc' => $lang->desc_score,
		'asc' => $lang->asc_score
	);

	$form_container->output_row($lang->score_sort_by." <em>*</em>", "", $form->generate_select_box('sortby', $sort_by, array('id' => 'sortby')), 'sortby');
	$form_container->output_row($lang->tournament_select." <em>*</em>", "", $form->generate_yes_no_radio("tournamentselect", $mybb->input['tournamentselect'], true));
	$form_container->output_row($lang->active." <em>*</em>", "", $form->generate_yes_no_radio("active", $mybb->input['active'], true));

	$plugins->run_hooks("admin_arcade_games_add_simple_end");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_game);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "add_tar")
{
	$plugins->run_hooks("admin_arcade_games_add_tar");

	if($mybb->request_method == "post")
	{
		if(!is_uploaded_file($_FILES['tar_file']['tmp_name']))
		{
			$errors[] = $lang->error_missing_tar;
		}

		if(get_extension($_FILES['tar_file']['name']) != "tar")
		{
			$errors[] = $lang->error_not_tar;
		}

		$filename = explode(".tar", $_FILES['tar_file']['name']);
		$filename = my_substr($filename[0], 5);

		$query = $db->simple_select("arcadegames", "file", "file='{$filename}'");
		$file = $db->fetch_array($query);

		if($file['file'])
		{
			$errors[] = $lang->error_game_already_used;
		}

		if($mybb->input['cid'] != 0)
		{
			$query = $db->simple_select("arcadecategories", "cid", "cid='{$mybb->input['cid']}'");
			$category = $db->fetch_array($query);

			if(!$category['cid'])
			{
				$errors[] = $lang->error_category_does_not_exist;
			}
		}

		// Upload file
		$file_tar = upload_file($_FILES['tar_file'], MYBB_ROOT."arcade", $_FILES['tar_file']['name']);
		if($file_tar['error'])
		{
			$errors[] = $lang->error_uploadfailed;
		}

		if(!$errors)
		{
			// Unpack tar
			require_once MYBB_ROOT."inc/3rdparty/tar/pcltar.lib.php";
			$tar = PclTarExtract(MYBB_ROOT."arcade/".$_FILES['tar_file']['name'], MYBB_ROOT."arcade", "", "tar");

			if($tar == 0)
			{
				$errors[] = $lang->tar_problem;
			}

			if(!$errors)
			{
				// Delete tar
				@unlink(MYBB_ROOT."arcade/".$_FILES['tar_file']['name']);

				// SWF file
				if(!@copy(MYBB_ROOT."arcade/".$filename.".swf", MYBB_ROOT."arcade/swf/".$filename.".swf"))
				{
					$errors[] = $lang->error_missing_game_tar_swf;
				}
				else
				{
					@my_chmod(MYBB_ROOT."arcade/swf/".$filename.".swf", 0777);
					@unlink(MYBB_ROOT."arcade/".$filename.".swf");
				}

				// PHP file
				if(!@copy(MYBB_ROOT."arcade/".$filename.".php", MYBB_ROOT."arcade/php/".$filename.".php"))
				{
					$errors[] = $lang->error_missing_game_tar_php;
				}
				else
				{
					@my_chmod(MYBB_ROOT."arcade/php/".$filename.".php", 0777);
					@unlink(MYBB_ROOT."arcade/".$filename.".php");
				}

				// Large image file
				if(!@copy(MYBB_ROOT."arcade/".$filename."1.gif", MYBB_ROOT."arcade/largeimages/".$filename."1.gif"))
				{
					$errors[] = $lang->error_missing_game_tar_largeimage;
				}
				else
				{
					@my_chmod(MYBB_ROOT."arcade/largeimages/".$filename."1.gif", 0777);
					@unlink(MYBB_ROOT."arcade/".$filename."1.gif");
				}

				// Small image file
				if(!@copy(MYBB_ROOT."arcade/".$filename."2.gif", MYBB_ROOT."arcade/smallimages/".$filename."2.gif"))
				{
					$errors[] = $lang->error_missing_game_tar_smallimage;
				}
				else
				{
					@my_chmod(MYBB_ROOT."arcade/smallimages/".$filename."2.gif", 0777);
					@unlink(MYBB_ROOT."arcade/".$filename."2.gif");
				}

				if(!$errors)
				{
					// Load PHP file and insert game into database
					require_once(MYBB_ROOT."arcade/php/".$filename.".php");

					if($config['highscore_type'] == "low" || $config['highscore_type'] == "asc")
					{
						$sortby = "asc";
					}
					else
					{
						$sortby = "desc";
					}

					$new_game = array(
						"name" => $db->escape_string($config['gtitle']),
						"description" => $db->escape_string($config['gwords']),
						"about" => $db->escape_string($config['object']),
						"controls" => $db->escape_string($config['gkeys']),
						"file" => $db->escape_string($config['gname']),
						"smallimage" => $db->escape_string($config['gname']."2"),
						"largeimage" => $db->escape_string($config['gname']."1"),
						"cid" => (int)$mybb->input['cid'],
						"dateline" => TIME_NOW,
						"bgcolor" => $db->escape_string($config['bgcolor']),
						"width" => (int)$config['gwidth'],
						"height" => (int)$config['gheight'],
						"sortby" => $db->escape_string($sortby),
						"tournamentselect" => (int)$mybb->input['tournamentselect'],
						"active" => (int)$mybb->input['active']
					);
					$gid = $db->insert_query("arcadegames", $new_game);

					$plugins->run_hooks("admin_arcade_games_add_tar_commit");

					// Log admin action
					log_admin_action($gid, $config['gtitle']);
				}
			}

			flash_message($lang->success_game_added, 'success');
			admin_redirect("index.php?module=arcade-games");
		}
	}
	$page->add_breadcrumb_item($lang->add_new_game_tar);
	$page->output_header($lang->games." - ".$lang->add_new_game_tar);

	$sub_tabs['games'] = array(
		'title' => $lang->games,
		'link' => "index.php?module=arcade-games",
	);

	$sub_tabs['add_game_simple'] = array(
		'title' => $lang->add_game_simple,
		'link' => "index.php?module=arcade-games&amp;action=add_simple",
	);

	$sub_tabs['add_game_tar'] = array(
		'title' => $lang->add_game_tar,
		'link' => "index.php?module=arcade-games&amp;action=add_tar",
		'description' => $lang->add_game_tar_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_game_tar');
	$form = new Form("index.php?module=arcade-games&amp;action=add_tar", "post", false, true);
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['active'] = 1;
		$mybb->input['tournamentselect'] = 1;
	}
	$form_container = new FormContainer($lang->add_new_game_tar);
	$form_container->output_row($lang->tar_file." <em>*</em>", "", $form->generate_file_upload_box('tar_file', array('id' => 'tar_file')), 'tar_file');

	$categories['0'] = $lang->no_category;

	$query = $db->simple_select("arcadecategories", "cid, name", "active='1'", array("order_by" => "name", "order_dir" => "asc"));
	while($category = $db->fetch_array($query))
	{
		$categories[$category['cid']] = $category['name'];
	}
	$form_container->output_row($lang->category, "", $form->generate_select_box("cid", $categories, $mybb->input['cid'], array('id' => 'cid')), 'cid');
	$form_container->output_row($lang->tournament_select." <em>*</em>", "", $form->generate_yes_no_radio("tournamentselect", $mybb->input['tournamentselect'], true));
	$form_container->output_row($lang->active." <em>*</em>", "", $form->generate_yes_no_radio("active", $mybb->input['active'], true));

	$plugins->run_hooks("admin_arcade_games_add_tar_end");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_game);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_arcade_games_edit");

	$query = $db->simple_select("arcadegames", "*", "gid='".(int)$mybb->input['gid']."'");
	$game = $db->fetch_array($query);

	if(!$game['gid'])
	{
		flash_message($lang->error_invalid_game, 'error');
		admin_redirect("index.php?module=arcade-games");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if(!trim($mybb->input['description']))
		{
			$errors[] = $lang->error_missing_description;
		}

		if(!trim($mybb->input['about']))
		{
			$errors[] = $lang->error_missing_about;
		}

		if(!trim($mybb->input['controls']))
		{
			$errors[] = $lang->error_missing_controls;
		}

		if(!file_exists(MYBB_ROOT."arcade/swf/".$mybb->input['file'].".swf"))
		{
			$errors[] = $lang->error_invalid_swf_file;
		}

		if(!file_exists(MYBB_ROOT."arcade/largeimages/".$mybb->input['largeimage'].".gif"))
		{
			$errors[] = $lang->error_invalid_large_image;
		}

		if(!file_exists(MYBB_ROOT."arcade/smallimages/".$mybb->input['smallimage'].".gif"))
		{
			$errors[] = $lang->error_invalid_small_image;
		}

		if($mybb->input['cid'] != 0)
		{
			$query = $db->simple_select("arcadecategories", "cid", "cid='{$mybb->input['cid']}'");
			$category = $db->fetch_array($query);

			if(!$category['cid'])
			{
				$errors[] = $lang->error_category_does_not_exist;
			}
		}

		if(!$errors)
		{
			$update_game = array(
				"name" => $db->escape_string($mybb->input['name']),
				"description" => $db->escape_string($mybb->input['description']),
				"about" => $db->escape_string($mybb->input['about']),
				"controls" => $db->escape_string($mybb->input['controls']),
				"file" => $db->escape_string($mybb->input['file']),
				"smallimage" => $db->escape_string($mybb->input['smallimage']),
				"largeimage" => $db->escape_string($mybb->input['largeimage']),
				"cid" => (int)$mybb->input['cid'],
				"bgcolor" => $db->escape_string($mybb->input['bgcolor']),
				"width" => (int)$mybb->input['width'],
				"height" => (int)$mybb->input['height'],
				"sortby" => $db->escape_string($mybb->input['sortby']),
				"tournamentselect" => (int)$mybb->input['tournamentselect'],
				"active" => (int)$mybb->input['active']
			);
			$db->update_query("arcadegames", $update_game, "gid='{$game['gid']}'");

			$plugins->run_hooks("admin_arcade_games_edit_commit");

			// Log admin action
			log_admin_action($game['gid'], $mybb->input['name']);

			flash_message($lang->success_game_updated, 'success');
			admin_redirect("index.php?module=arcade-games");
		}
	}

	$page->add_breadcrumb_item($lang->edit_game);
	$page->output_header($lang->arcade." - ".$lang->edit_game);

	$sub_tabs['edit_game'] = array(
		'title' => $lang->edit_game,
		'link' => "index.php?module=arcade-games&amp;action=edit",
		'description' => $lang->edit_games_desc
	);

	$page->output_nav_tabs($sub_tabs, 'edit_game');

	$form = new Form("index.php?module=arcade-games&amp;action=edit", "post", "edit");
	echo $form->generate_hidden_field("gid", $game['gid']);

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input = $game;
	}

	$form_container = new FormContainer($lang->edit_game);
	$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->description." <em>*</em>", "", $form->generate_text_area('description', $mybb->input['description'], array('id' => 'description')), 'description');
	$form_container->output_row($lang->about." <em>*</em>", $lang->about_desc, $form->generate_text_area('about', $mybb->input['about'], array('id' => 'about')), 'about');
	$form_container->output_row($lang->game_controls." <em>*</em>", $lang->game_controls_desc, $form->generate_text_area('controls', $mybb->input['controls'], array('id' => 'controls')), 'controls');

	$swf_list = array();
	$swf_files = scandir(MYBB_ROOT."arcade/swf/");
	foreach($swf_files as $swf_file)
	{
		if(is_file(MYBB_ROOT."arcade/swf/{$swf_file}") && get_extension($swf_file) == "swf")
		{
			$swf_file_id = preg_replace("#\.".get_extension($swf_file)."$#i", "$1", $swf_file);
			$swf_list[$swf_file_id] = $swf_file;
		}
	}
	$form_container->output_row($lang->swf_file." <em>*</em>", $lang->swf_file_desc, $form->generate_select_box("file", $swf_list, $mybb->input['file'], array('id' => 'file')), 'file');

	$small_list = array();
	$small_files = scandir(MYBB_ROOT."arcade/smallimages/");
	foreach($small_files as $small_file)
	{
		if(is_file(MYBB_ROOT."arcade/smallimages/{$small_file}") && get_extension($small_file) == "gif")
		{
			$small_file_id = preg_replace("#\.".get_extension($small_file)."$#i", "$1", $small_file);
			$small_list[$small_file_id] = $small_file;
		}
	}
	$form_container->output_row($lang->small_image." <em>*</em>", $lang->small_image_desc, $form->generate_select_box("smallimage", $small_list, $mybb->input['smallimage'], array('id' => 'smallimage')), 'smallimage');

	$large_list = array();
	$large_files = scandir(MYBB_ROOT."arcade/largeimages/");
	foreach($large_files as $large_file)
	{
		if(is_file(MYBB_ROOT."arcade/largeimages/{$large_file}") && get_extension($large_file) == "gif")
		{
			$large_file_id = preg_replace("#\.".get_extension($large_file)."$#i", "$1", $large_file);
			$large_list[$large_file_id] = $large_file;
		}
	}
	$form_container->output_row($lang->large_image." <em>*</em>", $lang->large_image_desc, $form->generate_select_box("largeimage", $large_list, $mybb->input['largeimage'], array('id' => 'largeimage')), 'largeimage');

	$categories['0'] = $lang->no_category;

	$query = $db->simple_select("arcadecategories", "cid, name", "active='1'", array("order_by" => "name", "order_dir" => "asc"));
	while($category = $db->fetch_array($query))
	{
		$categories[$category['cid']] = $category['name'];
	}
	$form_container->output_row($lang->category, "", $form->generate_select_box("cid", $categories, $mybb->input['cid'], array('id' => 'cid')), 'cid');

	$form_container->output_row($lang->bg_color, "", $form->generate_text_box('bgcolor', $mybb->input['bgcolor'], array('id' => 'bgcolor')), 'bgcolor');
	$form_container->output_row($lang->width, "", $form->generate_text_box('width', $mybb->input['width'], array('id' => 'width')), 'width');
	$form_container->output_row($lang->height, "", $form->generate_text_box('height', $mybb->input['height'], array('id' => 'height')), 'height');

	$sort_by = array(
		'desc' => $lang->desc_score,
		'asc' => $lang->asc_score
	);

	$form_container->output_row($lang->score_sort_by." <em>*</em>", "", $form->generate_select_box('sortby', $sort_by, array('id' => 'sortby')), 'sortby');
	$form_container->output_row($lang->tournament_select." <em>*</em>", "", $form->generate_yes_no_radio("tournamentselect", $mybb->input['tournamentselect'], true));
	$form_container->output_row($lang->active." <em>*</em>", "", $form->generate_yes_no_radio("active", $mybb->input['active'], true));

	$plugins->run_hooks("admin_arcade_games_edit_end");

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_game);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_arcade_games_delete");

	$query = $db->simple_select("arcadegames", "*", "gid='".(int)$mybb->input['gid']."'");
	$game = $db->fetch_array($query);

	if(!$game['gid'])
	{
		flash_message($lang->error_invalid_game, 'error');
		admin_redirect("index.php?module=arcade-games");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=arcade-games");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("arcadegames", "gid='{$game['gid']}'");
		$db->delete_query("arcadechampions", "gid='{$game['gid']}'");
		$db->delete_query("arcadefavorites", "gid='{$game['gid']}'");
		$db->delete_query("arcaderatings", "gid='{$game['gid']}'");
		$db->delete_query("arcadescores", "gid='{$game['gid']}'");
		$db->delete_query("arcadesessions", "gid='{$game['gid']}'");
		$db->delete_query("arcadelogs", "gid='{$game['gid']}'");

		$query = $db->simple_select("arcadetournaments", "tid", "gid='{$game['gid']}'");
		while($tournament = $db->fetch_array($query))
		{
			$db->delete_query("arcadetournamentplayers", "tid='{$tournament['tid']}'");
		}

		$db->delete_query("arcadetournaments", "gid='{$game['gid']}'");

		require_once MYBB_ROOT."inc/functions_arcade.php";
		update_tournaments_stats();

		$plugins->run_hooks("admin_arcade_games_delete_commit");

		// Log admin action
		log_admin_action($game['gid'], $game['name']);

		flash_message($lang->success_game_deleted, 'success');
		admin_redirect("index.php?module=arcade-games");
	}
	else
	{
		$page->output_confirm_action("index.php?module=arcade-games&amp;action=delete&amp;gid={$game['gid']}", $lang->confirm_game_deletion);
	}
}

if($mybb->input['action'] == "disable")
{
	$plugins->run_hooks("admin_arcade_games_disable");

	$query = $db->simple_select("arcadegames", "*", "gid='".(int)$mybb->input['gid']."'");
	$game = $db->fetch_array($query);

	if(!$game['gid'])
	{
		flash_message($lang->error_invalid_game, 'error');
		admin_redirect("index.php?module=arcade-games");
	}

	$active = array(
		"active" => 0
	);
	$db->update_query("arcadegames", $active, "gid='{$game['gid']}'");

	$plugins->run_hooks("admin_arcade_games_disable_commit");

	// Log admin action
	log_admin_action($game['gid'], $game['name']);

	flash_message($lang->success_game_disabled, 'success');
	admin_redirect("index.php?module=arcade-games");
}

if($mybb->input['action'] == "enable")
{
	$plugins->run_hooks("admin_arcade_games_enable");

	$query = $db->simple_select("arcadegames", "*", "gid='".(int)$mybb->input['gid']."'");
	$game = $db->fetch_array($query);

	if(!$game['gid'])
	{
		flash_message($lang->error_invalid_game, 'error');
		admin_redirect("index.php?module=arcade-games");
	}

	$active = array(
		"active" => 1
	);
	$db->update_query("arcadegames", $active, "gid='{$game['gid']}'");

	$plugins->run_hooks("admin_arcade_games_enable_commit");

	// Log admin action
	log_admin_action($game['gid'], $game['name']);

	flash_message($lang->success_game_enabled, 'success');
	admin_redirect("index.php?module=arcade-games");
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_arcade_games_start");

	$page->output_header($lang->games);

	$sub_tabs['games'] = array(
		'title' => $lang->games,
		'link' => "index.php?module=arcade-games",
		'description' => $lang->games_desc
	);

	$sub_tabs['add_game_simple'] = array(
		'title' => $lang->add_game_simple,
		'link' => "index.php?module=arcade-games&amp;action=add_simple",
	);

	$sub_tabs['add_game_tar'] = array(
		'title' => $lang->add_game_tar,
		'link' => "index.php?module=arcade-games&amp;action=add_tar",
	);

	$page->output_nav_tabs($sub_tabs, 'games');

	$pagenum = $mybb->get_input('page', 1);
	if($pagenum)
	{
		$start = ($pagenum - 1) * 20;
	}
	else
	{
		$start = 0;
		$pagenum = 1;
	}

	$table = new Table;
	$table->construct_header($lang->name, array('width' => '15%'));
	$table->construct_header($lang->description, array("class" => "align_center", 'width' => '40%'));
	$table->construct_header($lang->total_plays, array("class" => "align_center", 'width' => '10%'));
	$table->construct_header($lang->last_played, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => '15%'));

	$query = $db->simple_select("arcadegames", "*", "", array('limit_start' => $start, 'limit' => 20, 'order_by' => 'name'));
	while($arcade_game = $db->fetch_array($query))
	{
		if($arcade_game['lastplayed'])
		{
			$arcade_game['lastplayed'] = my_date('relative', $arcade_game['lastplayed']);
		}
		else
		{
			$arcade_game['lastplayed'] = $lang->na;
		}
		$trow = alt_trow();

		$table->construct_cell("<strong><a href=\"index.php?module=arcade-games&amp;action=edit&amp;gid={$arcade_game['gid']}\">{$arcade_game['name']}</a></strong><br />
<a href=\"index.php?module=arcade-games&amp;action=edit&amp;gid={$arcade_game['gid']}\"><img src=\"../arcade/largeimages/".$arcade_game['largeimage'].".gif\" border=\"0\" alt=\"\" />", array("class" => "align_center"));
		$table->construct_cell($arcade_game['description']);
		$table->construct_cell($arcade_game['plays'], array("class" => "align_center"));
		$table->construct_cell($arcade_game['lastplayed'], array("class" => "align_center"));

		$popup = new PopupMenu("game_{$arcade_game['gid']}", $lang->options);
		$popup->add_item($lang->edit_game, "index.php?module=arcade-games&amp;action=edit&amp;gid={$arcade_game['gid']}");
		if($arcade_game['active'] == 1)
		{
			$popup->add_item($lang->disable_game, "index.php?module=arcade-games&amp;action=disable&amp;gid={$arcade_game['gid']}&amp;my_post_key={$mybb->post_code}");
		}
		else
		{
			$popup->add_item($lang->enable_game, "index.php?module=arcade-games&amp;action=enable&amp;gid={$arcade_game['gid']}&amp;my_post_key={$mybb->post_code}");
		}
		$popup->add_item($lang->delete_game, "index.php?module=arcade-games&amp;action=delete&amp;gid={$arcade_game['gid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_game_deletion}')");
		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_games, array('colspan' => 5));
		$table->construct_row();
	}

	$table->output($lang->games);

	$query = $db->simple_select("arcadegames", "COUNT(gid) AS games");
	$total_rows = $db->fetch_field($query, "games");

	echo draw_admin_pagination($pagenum, "20", $total_rows, "index.php?module=arcade-games&amp;page={page}");

	$page->output_footer();
}

?>