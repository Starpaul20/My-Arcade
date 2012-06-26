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

$page->add_breadcrumb_item($lang->categories, "index.php?module=arcade-categories");

$plugins->run_hooks("admin_arcade_categories_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_arcade_categories_add");

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if($mybb->input['group_type'] == 2)
		{
			if(count($mybb->input['group_1_groups']) < 1)
			{
				$errors[] = $lang->error_no_groups_selected;
			}

			$group_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$group_checked[1] = "checked=\"checked\"";
			$mybb->input['group_1_groups'] = '';
		}

		if(!$errors)
		{
			$new_categories = array(
				"name" => $db->escape_string($mybb->input['name']),
				"image" => $db->escape_string($mybb->input['image']),
				"active" => intval($mybb->input['active'])
			);

			if($mybb->input['group_type'] == 2)
			{
				if(is_array($mybb->input['group_1_groups']))
				{
					$checked = array();
					foreach($mybb->input['group_1_groups'] as $gid)
					{
						$checked[] = intval($gid);
					}

					$new_categories['groups'] = implode(',', $checked);
				}
			}
			else
			{
				$new_categories['groups'] = '-1';
			}

			$cid = $db->insert_query("arcadecategories", $new_categories);

			$plugins->run_hooks("admin_arcade_categories_add_commit");

			// Log admin action
			log_admin_action($cid, $mybb->input['name']);

			flash_message($lang->success_category_added, 'success');
			admin_redirect("index.php?module=arcade-categories");
		}
	}
	$page->add_breadcrumb_item($lang->add_new_category);
	$page->output_header($lang->categories." - ".$lang->add_new_category);

	$sub_tabs['categories'] = array(
		'title' => $lang->categories,
		'link' => "index.php?module=arcade-categories",
	);

	$sub_tabs['add_category'] = array(
		'title' => $lang->add_category,
		'link' => "index.php?module=arcade-categories&amp;action=add",
		'description' => $lang->add_category_desc
	);

	$page->output_nav_tabs($sub_tabs, 'add_category');
	$form = new Form("index.php?module=arcade-categories&amp;action=add", "post", "add");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$mybb->input['image'] = 'arcade/categories/';
		$mybb->input['active'] = 1;
		$mybb->input['group_1_groups'] = '';
		$group_checked[1] = "checked=\"checked\"";
		$group_checked[2] = '';
	}

	$form_container = new FormContainer($lang->add_new_category);
	$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->image, "", $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');
		$group_select = "<script type=\"text/javascript\">
		function checkAction(id)
		{
			var checked = '';

			$$('.'+id+'s_check').each(function(e)
			{
     	       if(e.checked == true)
     	       {
     	           checked = e.value;
     	       }
     	   });
      	  $$('.'+id+'s').each(function(e)
     	   {
     	   	Element.hide(e);
      	  });
      	  if($(id+'_'+checked))
      	  {
      	      Element.show(id+'_'+checked);
			}
		}    
	</script>
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"1\" {$group_checked[1]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"2\" {$group_checked[2]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"group_2\" class=\"groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('group_1_groups[]', $mybb->input['group_1_groups'], array('multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
		checkAction('group');
	</script>";
	$form_container->output_row($lang->available_to_groups." <em>*</em>", '', $group_select);

	$form_container->output_row($lang->active." <em>*</em>", "", $form->generate_yes_no_radio("active", $mybb->input['active'], true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_category);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_arcade_categories_edit");

	$query = $db->simple_select("arcadecategories", "*", "cid='".intval($mybb->input['cid'])."'");
	$category = $db->fetch_array($query);

	// Does the category not exist?
	if(!$category['cid'])
	{
		flash_message($lang->error_invalid_category, 'error');
		admin_redirect("index.php?module=arcade-categories");
	}

	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['name']))
		{
			$errors[] = $lang->error_missing_name;
		}

		if($mybb->input['group_type'] == 2)
		{
			if(count($mybb->input['group_1_groups']) < 1)
			{
				$errors[] = $lang->error_no_groups_selected;
			}

			$group_checked[2] = "checked=\"checked\"";
		}
		else
		{
			$group_checked[1] = "checked=\"checked\"";
			$mybb->input['group_1_groups'] = '';
		}

		if(!$errors)
		{
			$updated_category = array(
				"name" => $db->escape_string($mybb->input['name']),
				"image" => $db->escape_string($mybb->input['image']),
				"active" => intval($mybb->input['active'])
			);

			if($mybb->input['group_type'] == 2)
			{
				if(is_array($mybb->input['group_1_groups']))
				{
					$checked = array();
					foreach($mybb->input['group_1_groups'] as $gid)
					{
						$checked[] = intval($gid);
					}
					
					$updated_category['groups'] = implode(',', $checked);
				}
			}
			else
			{
				$updated_category['groups'] = '-1';
			}

			$db->update_query("arcadecategories", $updated_category, "cid='{$category['cid']}'");

			$plugins->run_hooks("admin_arcade_categories_edit_commit");

			// Log admin action
			log_admin_action($category['cid'], $mybb->input['name']);

			flash_message($lang->success_category_updated, 'success');
			admin_redirect("index.php?module=arcade-categories");
		}
	}

	$page->add_breadcrumb_item($lang->edit_category);
	$page->output_header($lang->categories." - ".$lang->edit_category);

	$sub_tabs['edit_category'] = array(
		'title' => $lang->edit_category,
		'description' => $lang->edit_category_desc,
		'link' => "index.php?module=arcade-categories&amp;action=edit&amp;cid={$category['cid']}"
	);

	$page->output_nav_tabs($sub_tabs, 'edit_category');

	$form = new Form("index.php?module=arcade-categories&amp;action=edit", "post");

	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select("arcadecategories", "*", "cid = '".intval($mybb->input['cid'])."'");
		$category = $db->fetch_array($query);
		$mybb->input['name'] = $category['name'];
		$mybb->input['image'] = $category['image'];
		$mybb->input['active'] = $category['active'];

		$mybb->input['group_1_groups'] = explode(",", $category['groups']);

		if(!$category['groups'] || $category['groups'] == -1)
		{
			$group_checked[1] = "checked=\"checked\"";
			$group_checked[2] = '';
		}
		else
		{
			$group_checked[1] = '';
			$group_checked[2] = "checked=\"checked\"";
		}
		$category_data = $category;
	}

	$form_container = new FormContainer($lang->edit_category);
	echo $form->generate_hidden_field("cid", $category['cid']);
	$form_container->output_row($lang->name." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
	$form_container->output_row($lang->image, "", $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');
		$group_select = "<script type=\"text/javascript\">
		function checkAction(id)
		{
			var checked = '';

			$$('.'+id+'s_check').each(function(e)
			{
     	       if(e.checked == true)
     	       {
     	           checked = e.value;
     	       }
     	   });
      	  $$('.'+id+'s').each(function(e)
     	   {
     	   	Element.hide(e);
      	  });
      	  if($(id+'_'+checked))
      	  {
      	      Element.show(id+'_'+checked);
			}
		}    
	</script>
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"1\" {$group_checked[1]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"group_type\" value=\"2\" {$group_checked[2]} class=\"groups_check\" onclick=\"checkAction('group');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"group_2\" class=\"groups\">
			<table cellpadding=\"4\">
				<tr>
					<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
					<td>".$form->generate_group_select('group_1_groups[]', $mybb->input['group_1_groups'], array('multiple' => true, 'size' => 5))."</td>
				</tr>
			</table>
		</dd>
	</dl>
	<script type=\"text/javascript\">
		checkAction('group');
	</script>";
	$form_container->output_row($lang->available_to_groups." <em>*</em>", '', $group_select);

	$form_container->output_row($lang->active." <em>*</em>", "", $form->generate_yes_no_radio("active", $mybb->input['active'], true));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_category);

	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_arcade_categories_delete");

	$query = $db->simple_select("arcadecategories", "*", "cid='".intval($mybb->input['cid'])."'");
	$category = $db->fetch_array($query);

	if(!$category['cid'])
	{
		flash_message($lang->error_invalid_category, 'error');
		admin_redirect("index.php?module=arcade-categories");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=arcade-categories");
	}

	if($mybb->request_method == "post")
	{
		$updated_category = array(
			"cid" => 0
		);

		$db->update_query("arcadegames", $updated_category, "cid='{$category['cid']}'");
		$db->delete_query("arcadecategories", "cid='{$category['cid']}'");

		$plugins->run_hooks("admin_arcade_categories_delete_commit");

		// Log admin action
		log_admin_action($category['cid'], $category['name']);

		flash_message($lang->success_category_deleted, 'success');
		admin_redirect("index.php?module=arcade-categories");
	}
	else
	{
		$page->output_confirm_action("index.php?module=arcade-categories&amp;action=delete&amp;cid={$category['cid']}", $lang->confirm_category_deletion);
	}
}

if($mybb->input['action'] == "disable")
{
	$plugins->run_hooks("admin_arcade_categories_disable");

	$query = $db->simple_select("arcadecategories", "*", "cid='".intval($mybb->input['cid'])."'");
	$category = $db->fetch_array($query);

	if(!$category['cid'])
	{
		flash_message($lang->error_invalid_category, 'error');
		admin_redirect("index.php?module=arcade-categories");
	}

	$active = array(
		"active" => 0
	);
	$db->update_query("arcadecategories", $active, "cid='{$category['cid']}'");

	$plugins->run_hooks("admin_arcade_categories_disable_commit");

	// Log admin action
	log_admin_action($category['cid'], $category['name']);

	flash_message($lang->success_category_disabled, 'success');
	admin_redirect("index.php?module=arcade-categories");
}

if($mybb->input['action'] == "enable")
{
	$plugins->run_hooks("admin_arcade_categories_enable");

	$query = $db->simple_select("arcadecategories", "*", "cid='".intval($mybb->input['cid'])."'");
	$category = $db->fetch_array($query);

	if(!$category['cid'])
	{
		flash_message($lang->error_invalid_category, 'error');
		admin_redirect("index.php?module=arcade-categories");
	}

	$active = array(
		"active" => 1
	);

	$db->update_query("arcadecategories", $active, "cid='{$category['cid']}'");

	$plugins->run_hooks("admin_arcade_categories_enable_commit");

	// Log admin action
	log_admin_action($category['cid'], $category['name']);

	flash_message($lang->success_category_enabled, 'success');
	admin_redirect("index.php?module=arcade-categories");
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_arcade_categories_start");

	$page->output_header($lang->categories);

	$sub_tabs['categories'] = array(
		'title' => $lang->categories,
		'link' => "index.php?module=arcade-categories",
		'description' => $lang->categories_desc
	);

	$sub_tabs['add_category'] = array(
		'title' => $lang->add_category,
		'link' => "index.php?module=arcade-categories&amp;action=add",
	);

	$page->output_nav_tabs($sub_tabs, 'categories');

	$table = new Table;
	$table->construct_header($lang->name);
	$table->construct_header($lang->controls, array("class" => "align_center", 'width' => '150'));

	$query = $db->simple_select("arcadecategories", "*", "", array('order_by' => 'name'));
	while($arcade_category = $db->fetch_array($query))
	{
		$arcade_category['name'] = htmlspecialchars_uni($arcade_category['name']);
		if($arcade_category['active'] == 1)
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.gif\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}\"  style=\"vertical-align: middle;\" /> ";
		}
		else
		{
			$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.gif\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}\"  style=\"vertical-align: middle;\" /> ";
		}
		$table->construct_cell("<div>{$icon}<strong><a href=\"index.php?module=arcade-categories&amp;action=edit&amp;cid={$arcade_category['cid']}\">{$arcade_category['name']}</a></strong></div>");

		$popup = new PopupMenu("category_{$arcade_category['cid']}", $lang->options);
		$popup->add_item($lang->edit_category, "index.php?module=arcade-categories&amp;action=edit&amp;cid={$arcade_category['cid']}");
		if($arcade_category['active'] == 1)
		{
			$popup->add_item($lang->disable_category, "index.php?module=arcade-categories&amp;action=disable&amp;cid={$arcade_category['cid']}&amp;my_post_key={$mybb->post_code}");
		}
		else
		{
			$popup->add_item($lang->enable_category, "index.php?module=arcade-categories&amp;action=enable&amp;cid={$arcade_category['cid']}&amp;my_post_key={$mybb->post_code}");
		}
		$popup->add_item($lang->delete_category, "index.php?module=arcade-categories&amp;action=delete&amp;cid={$arcade_category['cid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_category_deletion}')");
		$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_categories, array('colspan' => 2));
		$table->construct_row();
	}

	$table->output($lang->categories);

	$page->output_footer();
}

?>