<?php
/*
Custom Menu Plugin for MyBB
Copyright (C) 2016 Dieter Gobbers

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/* Exported by Hooks plugin Wed, 28 Aug 2013 10:37:27 GMT */

/* idea based on Menu Manager by vbgamer (http://www.mybbhacks.com) */


if (!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY"))
{
        define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

/* --- Plugin API: --- */

function custommenu_info()
{
	return array(
		'name' => 'Custom Menu',
		'description' => 'Adds an easy to add menu system for MyBB. Allows you to add/remove/disable menu items.',
		'website' => 'http://opt-community.de/',
		'author' => 'Dieter Gobbers',
		'authorsite' => 'http://opt-community.de/',
		'version' => '2.1.1',
		'guid' => '',
		'compatibility' => '18*'
	);
}

/**
 * function custommenu_deactivate()
 */

function custommenu_activate()
{
	if ( !file_exists( PLUGINLIBRARY ) )
	{
		flash_message( "PluginLibrary is missing.", "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}
	
	global $PL;
	$PL or require_once PLUGINLIBRARY;
	
	if ( $PL->version < 12 )
	{
		flash_message( "PluginLibrary is too old: " . $PL->version, "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}

	custommenu_deactivate();

	// activate stylesheet
	custommenu_setup_stylessheet();

	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
	
	$regex = '#' . '(\<ul class="menu top_links"\>.*$.*)\{.+($.^\s*<\/ul>)' . '#smUi';
	find_replace_templatesets("header", $regex, '${1}<custom_menu>${2}');
	
	change_admin_permission('tools','custommenu');
	
	custommenu_cache_menu();
}

function custommenu_deactivate()
{
	global $PL;
	$PL or require_once PLUGINLIBRARY;
	
	if ( !file_exists( PLUGINLIBRARY ) )
	{
		flash_message( "PluginLibrary is missing.", "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}
	
	global $PL;
	$PL or require_once PLUGINLIBRARY;
	
	if ( $PL->version < 12 )
	{
		flash_message( "PluginLibrary is too old: " . $PL->version, "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}

	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
	
	find_replace_templatesets("header", "#" . preg_quote('<custom_menu>') . "#i", '{$menu_portal}
						{$menu_search}
						{$menu_memberlist}
						{$menu_calendar}
						<li><a href="{$mybb->settings[\'bburl\']}/misc.php?action=help" class="help">{$lang->toplinks_help}</a></li>', 0);
	change_admin_permission('tools','custommenu', -1);
	
	$PL->stylesheet_delete('custommenu');
}

function custommenu_install()
{
	if ( !file_exists( PLUGINLIBRARY ) )
	{
		flash_message( "PluginLibrary is missing.", "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}
	
	global $PL;
	$PL or require_once PLUGINLIBRARY;
	
	if ( $PL->version < 12 )
	{
		flash_message( "PluginLibrary is too old: " . $PL->version, "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}
	
	global $db, $lang, $cache;
	
	// tables definition statements
	{
		$create_table_custommenu = "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."custommenu` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`id_name` varchar(255) CHARACTER SET utf8 NOT NULL COMMENT 'identify the menu item for CSS',
			`title` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'name of the menu entry, used for the forum header',
			`link` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
			`icon` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
			`hovericon` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
			`alt-text` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'IMG ALT Tag',
			`usergroups` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
			`ignoregroups` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
			`newwindow` tinyint(1) NOT NULL DEFAULT '0',
			`id_order` int(5) NOT NULL DEFAULT '10000',
			`disable` tinyint(1) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			UNIQUE KEY `id_name` (`id_name`),
			KEY `id_order` (`id_order`),
			KEY `disable` (`disable`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf32";
		$create_table_custommenu_sub = "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."custommenu_sub` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`cm_id` int(10) unsigned NOT NULL COMMENT 'id of the main menu',
			`title` varchar(255) NOT NULL COMMENT 'title of the submenu',
			`link` varchar(255) NOT NULL COMMENT 'URL of the target',
			`usergroups` varchar(255) NOT NULL DEFAULT '' COMMENT 'usergroups allowed to access the submenu',
			`ignoregroups` varchar(255) NOT NULL DEFAULT '' COMMENT 'usergroups not allowed to access the menu',
			`newwindow` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'is the submenu enabled?',
			`id_order` int(5) unsigned NOT NULL DEFAULT '10000' COMMENT 'what order to display the submenu entry',
			`disable` tinyint(1) unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			KEY `id_order` (`id_order`),
			KEY `disable` (`disable`),
			KEY `cm_id` (`cm_id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8";
		$alter_table_custommenu_sub = "ALTER TABLE `".TABLE_PREFIX."custommenu_sub`
			ADD CONSTRAINT `".TABLE_PREFIX."custommenu_sub_ibfk_1` FOREIGN KEY (`cm_id`) REFERENCES `".TABLE_PREFIX."custommenu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE";
		
		// create tables
		$db->write_query($create_table_custommenu);
		$db->write_query($create_table_custommenu_sub);

		// alter tables
		$db->write_query($alter_table_custommenu_sub);
		}
	
	// Default Menu
	// Portal
	$db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "custommenu
				(id,id_name,title,link,icon,id_order)
			  VALUES('1','portal','Portal','\$mybburl/portal.php','',1)");
	
	// Search
	$db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "custommenu
				(id,id_name,title,link,icon,id_order)
			  VALUES('2','search','Search','\$mybburl/search.php','',2)");
	
	// Memberlist
	$db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "custommenu
				(id,id_name,title,link,icon,id_order)
			  VALUES('3','memberlist','Memberlist','\$mybburl/memberlist.php','',3)");
	
	// Calender
	$db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "custommenu
				(id,id_name,title,link,icon,id_order)
			  VALUES('4','calendar','Calendar','\$mybburl/calendar.php','',4)");
	
	// Help
	$db->write_query("INSERT IGNORE INTO " . TABLE_PREFIX . "custommenu
				(id,id_name,title,link,icon,id_order)
			  VALUES('5','help','Help','\$mybburl/misc.php?action=help','',5)");
	
}

function custommenu_is_installed()
{
	if ( !file_exists( PLUGINLIBRARY ) )
	{
		flash_message( "PluginLibrary is missing.", "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}
	
	global $PL;
	$PL or require_once PLUGINLIBRARY;
	
	if ( $PL->version < 12 )
	{
		flash_message( "PluginLibrary is too old: " . $PL->version, "error" );
		admin_redirect( "index.php?module=config-plugins" );
	}

	global $db;

	// definitions:
	$tables=array(
		'custommenu',
		'custommenu_sub'
	);
	
	// now check if the DB is setup
	$is_installed=true;
	foreach($tables as $table)
	{
		if (!$db->table_exists($table))
		{
			$is_installed=false;
		}
	}
	
	return $is_installed;
}

function custommenu_uninstall()
{
	global $PL;
	$PL or require_once PLUGINLIBRARY;

	global $db;
	
	// drop tables
	$tables=array(
		'custommenu_sub',
		'custommenu'
	);
	foreach($tables as $table)
	{
		$db->write_query("DROP TABLE ".TABLE_PREFIX.$table);
	}

	$PL->stylesheet_delete('custommenu');
}

/* --- Hooks: --- */

/* --- Hook #12 - admin_config_action_handler --- */

$plugins->add_hook('admin_config_action_handler', 'custommenu_admin_config_action_handler_12', 10);

function custommenu_admin_config_action_handler_12(&$action)
{
	$action['custommenu'] = array(
		'active' => 'custommenu'
	);
}

/* --- Hook #11 - Admin Config Menu --- */

$plugins->add_hook('admin_config_menu', 'custommenu_admin_config_menu_11', 10);

function custommenu_admin_config_menu_11(&$adminmenu)
{
	global $lang;
	
	$lang->load('custommenu');
	
	$adminmenu[] = array(
		'id' => 'custommenu',
		'title' => $lang->custommenu_title,
		'link' => 'index.php?module=config-custommenu'
	);
}

/* --- Hook #13 - Custom Menu Settings Tab --- */

$plugins->add_hook('admin_load', 'custommenu_admin_load_13', 10);

function custommenu_admin_load_13()
{
	global $lang, $mybb, $db, $page, $cache;
	
	if ($page->active_action != 'custommenu')
		return false;

	$lang->load('custommenu');
	
	$page->add_breadcrumb_item($lang->custommenu_title, 'index.php?module=config-custommenu');
	
	// Create Admin Tabs
	$tabs['custommenu']     = array(
		'title' => $lang->custommenu_list,
		'link' => 'index.php?module=config-custommenu',
		'description' => $lang->custommenu_description
	);
	$tabs['custommenu_add'] = array(
		'title' => $lang->custommenu_add,
		'link' => 'index.php?module=config-custommenu&action=add',
		'description' => $lang->custommenu_add_description
	);
	
	// No action: display what we've got so far
	if (!$mybb->input['action'])
	{
		$page->add_breadcrumb_item($lang->custommenu_list, 'index.php?module=config-custommenu');
		$page->output_header($lang->custommenu_title);
		$page->output_nav_tabs($tabs, 'custommenu');
		
		$usergroups=custommenu_get_usergroups_selection();
		
		$form  = new Form("index.php?module=config-custommenu&amp;action=update", "post");
		$table = new Table;
		$table->construct_header($lang->custommenu_table_id_name);
		$table->construct_header($lang->custommenu_table_title);
		$table->construct_header($lang->custommenu_table_link);
		$table->construct_header($lang->custommenu_table_icon);
		$table->construct_header($lang->custommenu_table_hovericon);
		$table->construct_header($lang->custommenu_table_alt);
		$table->construct_header($lang->custommenu_table_groups, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_ignoregroups, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_order, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_status, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_options);
		
		$query=$db->simple_select(
			'custommenu',
			'*',
			'',
			array(
				'order_by' => 'id_order',
				'order_dir' => 'ASC'
			)
		);
		
		while($menuRow=$db->fetch_array($query))
		{
			
			$finalIconUrl = $menuRow['icon'];
			$finalIconUrl = str_replace('$mybburl', $mybb->settings['bburl'], $finalIconUrl);
			$finalIconUrl = str_replace('$imgdir', $theme['imgdir'], $finalIconUrl);
			
			$finalHoverIconUrl = $menuRow['hovericon'];
			$finalHoverIconUrl = str_replace('$mybburl', $mybb->settings['bburl'], $finalHoverIconUrl);
			$finalHoverIconUrl = str_replace('$imgdir', $theme['imgdir'], $finalHoverIconUrl);
			
			$table->construct_cell($menuRow['id_name']);
			$table->construct_cell($menuRow['title']);
			$table->construct_cell($menuRow['link']);
			$table->construct_cell((!empty($menuRow['icon']) ? '<img src="' . $finalIconUrl . '" /> ' : ' '));
			$table->construct_cell((!empty($menuRow['hovericon']) ? '<img src="' . $finalHoverIconUrl . '" /> ' : ' '));
			$table->construct_cell($menuRow['alt-text']);

			$tusergroups = array();
			foreach ( explode( ',', $menuRow[ 'usergroups' ] ) as $usergroup )
			{
				$tusergroups[] = $usergroups[ $usergroup ];
			}
			$table->construct_cell( implode( '<br>', $tusergroups ), array(
				 'class' => 'align_center' 
			) );

			$tusergroups = array();
			foreach ( explode( ',', $menuRow[ 'ignoregroups' ] ) as $usergroup )
			{
				$tusergroups[] = $usergroups[ $usergroup ];
			}
			$table->construct_cell( implode( '<br>', $tusergroups ), array(
				 'class' => 'align_center' 
			) );
			
			$table->construct_cell('<input type="text" name="menu[' . $menuRow['id'] . ']" value="' . $menuRow['id_order'] . '" size="3"/>', array(
				 'class' => 'align_center' 
			) );
			$table->construct_cell('<a href="index.php?module=config-custommenu&amp;action=disable&id=' . $menuRow['id'] . '">' . ($menuRow['disable'] ? '<font color="#FF0000">' . $lang->custommenu_disabled . '</font>' : $lang->custommenu_enabled) . '</a>', array(
				'class' => 'align_center'
			));

			$popup = new PopupMenu( "menu_options_{$menuRow['id']}", $lang->options );
			$popup->add_item( $lang->custommenu_edit, "index.php?module=config-custommenu&action=edit&amp;id={$menuRow['id']}" );
			$popup->add_item( $lang->custommenu_sub_list, "index.php?module=config-custommenu&action=listsub&amp;id={$menuRow['id']}" );
			$table->construct_cell( $popup->fetch(), array(
				 'class' => 'align_center' 
			) );

			$table->construct_row();
		}
		$db->free_result($query);
		
		if ($table->num_rows() == 0)
		{
			$table->construct_cell($lang->custommenu_no_menus, array(
				'colspan' => 11
			));
			$table->construct_row();
		}
		else
		{
			$table->construct_cell('<input type="submit" value="' . $lang->custommenu_update . '" />', array(
				'colspan' => 11
			));
			$table->construct_row();
		}
		
		$form->end;
		$table->output($lang->custommenu_table_menuitems);
		
		$page->output_footer();
	}
	
	if ($mybb->input['action'] == 'add' || $mybb->input['action'] == 'edit')
	{
		if ($mybb->input['action'] == 'add')
		{
			$id_name	  = '';
			$title        = '';
			$link         = '';
			$icon         = '';
			$hovericon    = '';
			$alttext      = '';
			$groups       = '';
			$ignoregroups = '';
		}
		else
		{
			$query = $db->simple_select('custommenu', '*', 'id=' . $mybb->input['id'], array(
				'limit' => '1'
			));
			$menuRow = $db->fetch_array($query);
			$db->free_result($query);
			
			$id_name      = $menuRow['id_name'];
			$title        = $menuRow['title'];
			$link         = $menuRow['link'];
			$icon         = $menuRow['icon'];
			$hovericon    = $menuRow['hovericon'];
			$alttext      = $menuRow['alt-text'];
			$usergroups   = $menuRow['usergroups'];
			$ignoregroups = $menuRow['ignoregroups'];
			$newwindow    = $menuRow['newwindow'];
		}
		
		if ( $mybb->request_method == 'post' )
		{
			// Check Post
			$id_name      = $mybb->input['id_name'];
			$title        = $mybb->input['title'];
			$link         = $mybb->input['link'];
			$icon         = $mybb->input['icon'];
			$hovericon    = $mybb->input['hovericon'];
			$alttext      = $mybb->input['alttext'];
			$usergroups   = implode( ",", $mybb->input[ 'usergroups' ] );
			$ignoregroups = implode( ",", $mybb->input[ 'ignoregroups' ] );
			$newwindow    = isset($_REQUEST['newwindow']) ? 1 : 0;
			
			if (empty($title))
			{
				$title = '';
			}
			
			if (empty($link))
			{
				$errors[] = $lang->custommenu_error_no_link;
			}
			
			if ($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{
				$record = array(
					"id_name" => $db->escape_string( $id_name ),
					"title" => $db->escape_string( $title ),
					"link" => $db->escape_string( $link ),
					"icon" => $db->escape_string( $icon ),
					"hovericon" => $db->escape_string( $hovericon ),
					"alt-text" => $db->escape_string( $alttext ),
					"usergroups" => $db->escape_string( $usergroups ),
					"ignoregroups" => $db->escape_string( $ignoregroups ),
					"newwindow" => intval( $newwindow )
				);
				if ($mybb->input['action'] == 'add')
				{
					$db->insert_query( 'custommenu', $record );
					flash_message( $lang->custommenu_menu_added, 'success' );
				}
				else
				{
					$db->update_query( 'custommenu', $record, "id='" . $db->escape_string( $mybb->input['id'] ) . "'" );
					flash_message( $lang->custommenu_menu_edited, 'success' );
				}
				
				custommenu_reordermenuitems();
				
				admin_redirect("index.php?module=config-custommenu");
			}
		}
		
		if ($mybb->input['action'] == 'add')
		{
			$page->add_breadcrumb_item($lang->custommenu_add, 'index.php?module=config-custommenu&amp;action=add');
			$page->output_header($lang->custommenu_add);
			$page->output_nav_tabs($tabs, 'menumanage');
			$form  = new Form("index.php?module=config-custommenu&amp;action=add", "post");
			$submit=$lang->custommenu_add;
		}
		else
		{
			$page->add_breadcrumb_item($lang->custommenu_edit, 'index.php?module=config-custommenu&amp;action=edit');
			$page->output_header($lang->custommenu_edit);
			$page->output_nav_tabs($tabs, 'menumanage');
			$form  = new Form("index.php?module=config-custommenu&amp;action=edit", "post");
			$submit=$lang->custommenu_edit;
		}

		$table = new Table;
		
		$table->construct_cell($lang->custommenu_table_id_name);
		$table->construct_cell('<input type="text" size="50" name="id_name" value="' . $id_name . '" />');
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_table_title);
		$table->construct_cell('<input type="text" size="50" name="title" value="' . $title . '" />');
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_link);
		$table->construct_cell('<input type="text" size="50" name="link" value="' . $link . '" />');
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_icon);
		$table->construct_cell('<input type="text" size="50" name="icon" value="' . $icon . '" />');
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_hovericon);
		$table->construct_cell('<input type="text" size="50" name="hovericon" value="' . $hovericon . '" />');
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_alt);
		$table->construct_cell('<input type="text" size="50" name="alttext" value="' . $alttext . '" />');
		$table->construct_row();
		
		// $table->construct_cell($lang->custommenu_groups);
		// $table->construct_cell('<input type="text" size="50" name="usergroups" value="' . $usergroups . '" />');
		// $table->construct_row();
		$options          = array();
		$query            = $db->simple_select( "usergroups", "gid, title", "", array(
			 'order_by' => 'title' 
		) );
		while ( $usergroup = $db->fetch_array( $query ) )
		{
			$options[ (int) $usergroup[ 'gid' ] ] = $usergroup[ 'title' ];
		}
		$db->free_result( $query );
		$table->construct_cell( $lang->custommenu_groups );
		$table->construct_cell( $form->generate_select_box( 'usergroups[]', $options, explode( ",", $usergroups ), array(
			 'id' => 'usergroups',
			'multiple' => true,
			'size' => 5 
		) ) );
		$table->construct_row();
		
		// $table->construct_cell($lang->custommenu_ignoregroups);
		// $table->construct_cell('<input type="text" size="50" name="ignoregroups" value="' . $ignoregroups . '" />');
		// $table->construct_row();
		$options          = array();
		$query            = $db->simple_select( "usergroups", "gid, title", "", array(
			 'order_by' => 'title' 
		) );
		while ( $usergroup = $db->fetch_array( $query ) )
		{
			$options[ (int) $usergroup[ 'gid' ] ] = $usergroup[ 'title' ];
		}
		$db->free_result( $query );
		$table->construct_cell( $lang->custommenu_ignoregroups );
		$table->construct_cell( $form->generate_select_box( 'ignoregroups[]', $options, explode( ",", $ignoregroups ), array(
			 'id' => 'usergroups',
			'multiple' => true,
			'size' => 5 
		) ) );
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_window);
		$table->construct_cell('<input type="checkbox" name="newwindow" ' . ($newwindow == 1 ? ' checked="checked" ' : '') . '/>');
		$table->construct_row();
		
		$table->construct_cell('
		<input type="hidden" name="id" value="' . $mybb->input['id'] . '" />
		<input type="submit" value="' . $submit . '" />', array(
			'colspan' => 2
		));
		$table->construct_row();
		
		$form->end;
		$table->output($submit);
		
		$page->output_footer();
	}
	
	if ($mybb->input['action'] == 'disable')
	{
		custommenu_reordermenuitems();
		
		$query = $db->simple_select('custommenu', '*', 'id=' . intval($mybb->input['id']));
		$menuRow = $db->fetch_array($query);
		$db->free_result($query);
		
		$updated_record=array(
			'disable' => intval($menuRow['disable'] == 0 ? 1 : 0)
		);
		$db->update_query(
			'custommenu',
			$updated_record,
			'id='.intval($mybb->input['id'])
		);
		
		custommenu_reordermenuitems();
		
		admin_redirect("index.php?module=config-custommenu");
	}
	
	if ($mybb->input['action'] == 'delete')
	{
		// disabled code, needs some confirmation dialog
		// $db->delete_query('custommenu','id='.$id);
		
		custommenu_reordermenuitems();
		
		admin_redirect("index.php?module=config-custommenu");
	}
	
	if ($mybb->input['action'] == 'update')
	{
		$menuItems = $_REQUEST['menu'];
		
		foreach ($menuItems as $menu => $order)
		{
			$updated_record=array(
				'id_order' => intval($order)
			);
			$db->update_query(
				'custommenu',
				$updated_record,
				'id='.intval($menu)
			);
		}
		
		custommenu_reordermenuitems();
		
		admin_redirect("index.php?module=config-custommenu");
	}

	if ($mybb->input['action'] == 'listsub')
	{
		if(empty($mybb->input['id']))
		{
			$errors[]=$lang->custommenu_error_missing_id;
		}
		$query=$db->simple_select(
			'custommenu',
			'id_name',
			'id='.intval($mybb->input['id'])
		);
		$custommenu=$db->fetch_field($query, 'id_name');
		$db->free_result($query);
		
		$placeholders=array(
			'menuname' => $custommenu
		);
		$menuname=custommenu_fill_placeholders($lang->custommenu_sub_list_menu, $placeholders);
		$tabs['custommenu_submenu_list'] = array(
			'title' => $menuname,
			'link' => 'index.php?module=config-custommenu&action=listsub',
			'description' => custommenu_fill_placeholders($lang->custommenu_sub_list_description, $placeholders)
		);
		$tabs['custommenu_submenu_add'] = array(
			'title' => $lang->custommenu_sub_add,
			'link' => 'index.php?module=config-custommenu&action=addsub&cm_id='.intval($mybb->input['id']),
			'description' => custommenu_fill_placeholders($lang->custommenu_sub_add_description, $placeholders)
		);

		$page->add_breadcrumb_item($menuname, 'index.php?module=config-custommenu&amp;id='.$mybb->input['id']);
		$page->output_header($lang->custommenu_title);
		$page->output_nav_tabs($tabs, 'custommenu_submenu_list');

		// page content
		$usergroups=custommenu_get_usergroups_selection();
		
		$form  = new Form("index.php?module=config-custommenu&amp;action=updatesub&cm_id=".intval($mybb->input['id']), "post");
		$table = new Table;
		$table->construct_header($lang->custommenu_table_title);
		$table->construct_header($lang->custommenu_table_link);
		$table->construct_header($lang->custommenu_table_groups, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_ignoregroups, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_order, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_status, array(
			'class' => 'align_center'
		));
		$table->construct_header($lang->custommenu_table_options);

		$query=$db->simple_select(
			'custommenu_sub',
			'*',
			'cm_id='.intval($mybb->input['id']),
			array(
				'order_by' => 'id_order',
				'order_dir' => 'ASC'
			)
		);
		while($submenu = $db->fetch_array($query))
		{
			$table->construct_cell($submenu['title']);
			$table->construct_cell($submenu['link']);

			$tusergroups = array();
			foreach ( explode( ',', $submenu[ 'usergroups' ] ) as $usergroup )
			{
				$tusergroups[] = $usergroups[ $usergroup ];
			}
			$table->construct_cell( implode( '<br>', $tusergroups ), array(
				 'class' => 'align_center' 
			) );

			$tusergroups = array();
			foreach ( explode( ',', $submenu[ 'ignoregroups' ] ) as $usergroup )
			{
				$tusergroups[] = $usergroups[ $usergroup ];
			}
			$table->construct_cell( implode( '<br>', $tusergroups ), array(
				 'class' => 'align_center' 
			) );
			
			$table->construct_cell('<input type="text" name="menu[' . $submenu['id'] . ']" value="' . $submenu['id_order'] . '" size="3" />', array(
				 'class' => 'align_center' 
			) );
			$table->construct_cell('<a href="index.php?module=config-custommenu&amp;action=disablesub&id=' . $submenu['id'] . '&cm_id='.intval($mybb->input['id']).'">' . ($submenu['disable'] ? '<font color="#FF0000">' . $lang->custommenu_disabled . '</font>' : $lang->custommenu_enabled) . '</a>', array(
				'class' => 'align_center'
			) );

			$table->construct_cell('<a href="index.php?module=config-custommenu&action=editsub&amp;id='.$submenu['id'].'&amp;cm_id='.intval($mybb->input['id']).'">'.$lang->custommenu_sub_editsub.'</a>', array(
				'class' => 'align_center'
			));
			$table->construct_row();
		}
		$db->free_result($query);

		if ($table->num_rows() == 0)
		{
			$table->construct_cell($lang->custommenu_no_menus, array(
				'colspan' => 7
			));
			$table->construct_row();
		}
		else
		{
			$table->construct_cell('<input type="submit" value="' . $lang->custommenu_update . '" />', array(
				'colspan' => 7
			));
			$table->construct_row();
		}

		if (!empty($errors))
		{
			$page->output_inline_error($errors);
		}
		
		$form->end;
		$table->output($lang->custommenu_table_submenuitems);

		$page->output_footer();

		die("'listsub' not implemented");
	}

	if ($mybb->input['action'] == 'addsub' || $mybb->input['action'] == 'editsub')
	{
		if ($mybb->input['action'] == 'addsub')
		{
			$title        = '';
			$link         = '';
			$usergroups   = '';
			$ignoregroups = '';
		}
		else
		{
			$query = $db->simple_select('custommenu_sub', '*', 'id=' . $mybb->input['id'], array(
				'limit' => '1'
			));
			$menuRow = $db->fetch_array($query);
			$db->free_result($query);
			
			$title        = $menuRow['title'];
			$link         = $menuRow['link'];
			$usergroups   = $menuRow['usergroups'];
			$ignoregroups = $menuRow['ignoregroups'];
			$newwindow    = $menuRow['newwindow'];
		}
		
		if ( $mybb->request_method == 'post' )
		{
			// Check Post
			$title        = $mybb->input['title'];
			$link         = $mybb->input['link'];
			$usergroups   = implode( ",", $mybb->input[ 'usergroups' ] );
			$ignoregroups = implode( ",", $mybb->input[ 'ignoregroups' ] );
			$newwindow    = isset($_REQUEST['newwindow']) ? 1 : 0;
			
			if (empty($title))
			{
				$errors[] = $lang->custommenu_error_no_title;
			}
			
			if (empty($link))
			{
				$errors[] = $lang->custommenu_error_no_link;
			}
			
			if ($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{
				$record = array(
					"title" => $db->escape_string( $title ),
					"link" => $db->escape_string( $link ),
					"usergroups" => $db->escape_string( $usergroups ),
					"ignoregroups" => $db->escape_string( $ignoregroups ),
					"newwindow" => intval( $newwindow )
				);
				if ($mybb->input['action'] == 'addsub')
				{
					$record['cm_id']=intval( $mybb->input['cm_id']);
					$db->insert_query( 'custommenu_sub', $record );
					flash_message( $lang->custommenu_menu_added, 'success' );
				}
				else
				{
					$db->update_query( 'custommenu_sub', $record, "id='" . $db->escape_string( $mybb->input['id'] ) . "'" );
					flash_message( $lang->custommenu_menu_edited, 'success' );
				}
				
				custommenu_reordersubmenuitems($mybb->input['cm_id']);
				
				admin_redirect("index.php?module=config-custommenu&action=listsub&id=".$mybb->input['cm_id']);
			}
		}
		
		$query=$db->simple_select(
			'custommenu',
			'id_name',
			'id='.intval($mybb->input['cm_id'])
		);
		$custommenu=$db->fetch_field($query, 'id_name');
		$db->free_result($query);
		
		$placeholders=array(
			'menuname' => $custommenu
		);
		$menuname=custommenu_fill_placeholders($lang->custommenu_sub_list_menu, $placeholders);
		$page->add_breadcrumb_item($menuname, 'index.php?module=config-custommenu&amp;action=listsub&id='.$mybb->input['cm_id']);
		if ($mybb->input['action'] == 'addsub')
		{
			$page->add_breadcrumb_item($lang->custommenu_sub_add, 'index.php?module=config-custommenu&amp;action=addsub');
			$page->output_header($lang->custommenu_add);
			$page->output_nav_tabs($tabs, 'menumanage');
			$form  = new Form("index.php?module=config-custommenu&amp;action=addsub", "post");
			$submit=$lang->custommenu_add;
		}
		else
		{
			$page->add_breadcrumb_item($lang->custommenu_sub_editsub, 'index.php?module=config-custommenu&amp;action=editsub');
			$page->output_header($lang->custommenu_edit);
			$page->output_nav_tabs($tabs, 'menumanage');
			$form  = new Form("index.php?module=config-custommenu&amp;action=editsub", "post");
			$submit=$lang->custommenu_edit;
		}

		$table = new Table;
		
		$table->construct_cell($lang->custommenu_table_title);
		$table->construct_cell('<input type="text" size="50" name="title" value="' . $title . '" />');
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_link);
		$table->construct_cell('<input type="text" size="50" name="link" value="' . $link . '" />');
		$table->construct_row();
		
		$options          = array();
		$query            = $db->simple_select( "usergroups", "gid, title", "", array(
			 'order_by' => 'title' 
		) );
		while ( $usergroup = $db->fetch_array( $query ) )
		{
			$options[ (int) $usergroup[ 'gid' ] ] = $usergroup[ 'title' ];
		}
		$db->free_result( $query );
		$table->construct_cell( $lang->custommenu_groups );
		$table->construct_cell( $form->generate_select_box( 'usergroups[]', $options, explode( ",", $usergroups ), array(
			 'id' => 'usergroups',
			'multiple' => true,
			'size' => 5 
		) ) );
		$table->construct_row();
		
		$options          = array();
		$query            = $db->simple_select( "usergroups", "gid, title", "", array(
			 'order_by' => 'title' 
		) );
		while ( $usergroup = $db->fetch_array( $query ) )
		{
			$options[ (int) $usergroup[ 'gid' ] ] = $usergroup[ 'title' ];
		}
		$db->free_result( $query );
		$table->construct_cell( $lang->custommenu_ignoregroups );
		$table->construct_cell( $form->generate_select_box( 'ignoregroups[]', $options, explode( ",", $ignoregroups ), array(
			 'id' => 'usergroups',
			'multiple' => true,
			'size' => 5 
		) ) );
		$table->construct_row();
		
		$table->construct_cell($lang->custommenu_window);
		$table->construct_cell('<input type="checkbox" name="newwindow" ' . ($newwindow == 1 ? ' checked="checked" ' : '') . '/>');
		$table->construct_row();
		
		$table->construct_cell('
		<input type="hidden" name="id" value="' . $mybb->input['id'] . '" />
		<input type="hidden" name="cm_id" value="' . $mybb->input['cm_id'] . '" />
		<input type="submit" value="' . $submit . '" />', array(
			'colspan' => 2
		));
		$table->construct_row();
		
		$form->end;
		$table->output($submit);
		
		$page->output_footer();
	}

	if ($mybb->input['action'] == 'disablesub')
	{
		custommenu_reordermenuitems();
		
		$query = $db->simple_select('custommenu_sub', '*', 'id=' . intval($mybb->input['id']));
		$menuRow = $db->fetch_array($query);
		$db->free_result($query);
		
		$updated_record=array(
			'disable' => intval($menuRow['disable'] == 0 ? 1 : 0)
		);
		$db->update_query(
			'custommenu_sub',
			$updated_record,
			'id='.intval($mybb->input['id'])
		);
		
		custommenu_reordermenuitems();
		
		admin_redirect("index.php?module=config-custommenu&action=listsub&id=". intval($mybb->input['cm_id']));
	}
	
	if ($mybb->input['action'] == 'updatesub')
	{
		$menuItems = $_REQUEST['menu'];
		
		foreach ($menuItems as $menu => $order)
		{
			$updated_record=array(
				'id_order' => intval($order)
			);
			$db->update_query(
				'custommenu_sub',
				$updated_record,
				'id='.intval($menu)
			);
		}

		custommenu_reordersubmenuitems($mybb->input['cm_id']);
		
		admin_redirect("index.php?module=config-custommenu&action=listsub&id=".$mybb->input['cm_id']);
	}
}

/* --- Hook #10 - custom menu --- */

$plugins->add_hook('pre_output_page', 'custommenu_pre_output_page_10', 10);

function custommenu_pre_output_page_10(&$contents)
{
	global $db, $mybb, $theme, $lang, $custommenu, $cache;
	
	$custommenu = '';
	$usergroups = array_merge(array(
		$mybb->user['usergroup']
	), explode(',', $mybb->user['additionalgroups']));
	
	$menuentries=$cache->read('custommenu');
	foreach ($menuentries as $menuitem)
	{
		$menuurl = $menuitem['link'];
		// $menuurl = str_replace('$mybburl', $mybb->settings['bburl'], $menuurl);
		
		$menuicon = $menuitem['icon'];
		// $menuicon = str_replace('$mybburl', $mybb->settings['bburl'], $menuicon);
		$menuicon = str_replace('$imgdir', $theme['imgdir'], $menuicon);
		
		$menuhovericon = $menuitem['hovericon'];
		// $menuhovericon = str_replace('$mybburl', $mybb->settings['bburl'], $menuhovericon);
		$menuhovericon = str_replace('$imgdir', $theme['imgdir'], $menuhovericon);
		
		$imgalt = $menuitem['alt-text'];
		
		// check usergroup membership
		$visible = false;
		if (empty($menuitem['usergroups']))
		{
			$visible = true;
		}
		else
		{
			$visgroups = explode(',', $menuitem['usergroups']);
			foreach ($usergroups as $group)
			{
				foreach ($visgroups as $visgroup)
				{
					if ($group == $visgroup)
					{
						$visible = true;
					}
				}
			}
		}
		if (!empty($menuitem['ignoregroups']))
		{
			$ignoregroups = explode(',', $menuitem['ignoregroups']);
			foreach ($usergroups as $group)
			{
				foreach ($ignoregroups as $igngroup)
				{
					if ($group == $igngroup)
					{
						$visible = false;
					}
				}
			}
		}
		
		if (!$visible)
			continue;
		
		if (!empty($menuurl))
		{
			$menuurl2 = '</a>';
			$menuclass=$menuitem['id_name'];
			if (!empty($menuitem['submenus']))
			{
				// test if any of the submenus is visible for the logged in user
				$visible=false;
				foreach($menuitem['submenus'] as $key => $submenu)
				{
					if (empty($submenu['usergroups']) && empty($submenu['ignoregroups']))
					{
						$visible=true;
					}
					else
					{
						$userallowed=false;
						$userdenied=false;
						$permittedgroups=explode(',', $submenu['usergroups']);
						if (!empty($permittedgroups))
						{
							$userallowed=custommenu_user_is_member_of($usergroups, $permittedgroups);
						}
						$ignoredgroups=explode(',', $submenu['ignoregroups']);
						if (!empty($ignoredgroups))
						{
							$userdenied=custommenu_user_is_member_of($usergroups, $ignoredgroups);
						}
						if ($userallowed && !$userdenied)
						{
							$visible=true;
						}
						if (!$userallowed || $userdenied)
						{
							unset($menuitem['submenus'][$key]); // remove invisible menu entries
						}
					}
				}
				if($visible)
				{
					$menuclass='menu_submenu';
					$menuurl2 .= 
								'<div id="menu_'.$menuitem['id_name'].'_popup" class="submenu_popup" style="display: none; position: absolute; z-index: 100; top: 1224px; left: 1224px; visibility: visible;">';
					foreach($menuitem['submenus'] as $submenu)
					{
						$menuurl2 .= 
									'<div class="submenu_item">'.
									'<a href="' . $submenu['link'] . '"' . ($submenu['newwindow'] == 1 ? ' target="_blank" ' : '') . ' class="submenu_link">'.$submenu['title'].'</a>'.
									'</div>';
					}			
					$menuurl2 .= 
								'</div>'.
								'<script type="text/javascript">if(use_xmlhttprequest=="1")'.
								'{$("#menu_'.$menuitem['id_name'].'").popupMenu();}</script>';
				}
			}
			$menuurl1 = '<a href="' . $menuurl . '"' . ($menuitem['newwindow'] == 1 ? ' target="_blank" ' : '') . ' id="menu_'.$menuitem['id_name'].'" class="'.$menuclass.'">';
		}
		else
		{
			$menuurl1 = '';
			$menuurl2 = '';
		}
		if (!empty($menuhovericon) & !empty($menuicon))
		{
			$menuhovericon = ' onmouseover="this.src=\'' . $menuhovericon . '\'" onmouseout="this.src=\'' . $menuicon . '\'"';
		}
		if (!empty($imgalt))
		{
			$imgalt = ' alt="' . $imgalt . '" title="' . $imgalt . '"';
		}
		else
		{
			$imgalt = '';
		}
		if (!empty($menuicon))
		{
			$menuicon = '<img src="' . $menuicon . '"' . $menuhovericon . $imgalt . '>';
		}
		
		$custommenu .= '<li>' . $menuurl1 . $menuicon . $menuitem['title'] . $menuurl2 . '</li>';
	}
	
	$contents = str_replace('<custom_menu>', $custommenu, $contents);
	
	return $contents;
}

$plugins->add_hook('admin_config_permissions','custommenu_admin_permissions');

function custommenu_admin_permissions(&$admin_permissions)
{
	global $lang;
	
	$lang->load('custommenu');
	
	$admin_permissions['custommenu']=$lang->custommenu_can_manage_menus;
}

function custommenu_cache_menu( $clear = false )
{
	global $cache;
	if ( $clear == true )
	{
		$cache->update( 'custommenu', false );
	}
	else
	{
		global $db, $mybb;
		$custommenu = array();
		$query  = $db->simple_select( 'custommenu', '*', 'disable=0', array('order_by' => 'id_order', 'order_dir' => 'ASC') );
		while ( $menu = $db->fetch_array( $query ) )
		{
			$menu['link']                = str_replace('$mybburl', $mybb->settings['bburl'], $menu['link']);
			$menu['icon']                = str_replace('$mybburl', $mybb->settings['bburl'], $menu['icon']);
			$menu['hovericon']           = str_replace('$mybburl', $mybb->settings['bburl'], $menu['hovericon']);
			// $menu['icon']                = str_replace('$imgdir', $mybb->settings['imgdir'], $menu['icon']);
			// $menu['hovericon']           = str_replace('$imgdir', $mybb->settings['imgdir'], $menu['hovericon']);
			$custommenu[ $menu[ 'id' ] ] = $menu;
		}
		$db->free_result( $query );
		$query  = $db->simple_select( 'custommenu_sub', '*', 'disable=0', array('order_by' => 'id_order', 'order_dir' => 'ASC') );
		while ( $submenu = $db->fetch_array( $query ) )
		{
			$submenu['link']=str_replace('$mybburl', $mybb->settings['bburl'], $submenu['link']);
			if (!empty($custommenu[ $submenu[ 'cm_id' ]])) // the main menu is not disabled
			{
				$custommenu[ $submenu[ 'cm_id' ] ]['submenus'][$submenu[ 'id_order' ]] = $submenu;
			}
		}
		$db->free_result( $query );
		
		$cache->update( 'custommenu', $custommenu );
	}
}

// replace the placeholders by their content/values
function custommenu_fill_placeholders( $parseme, $placeholders = array() )
{
	if ( !empty( $parseme ) )
	{
		foreach ( $placeholders as $key => $value )
		{
			$parseme = str_replace( '{' . $key . '}', $value, $parseme );
		}
	}
	
	return $parseme;
}

function custommenu_reordermenuitems()
{
	global $db;
	
	$query = $db->simple_select('custommenu', '*', '', array(
		'order_by' => 'id_order',
		'order_dir' => 'ASC'
	));
	
	$count = 1;
	while ($row = $db->fetch_array($query))
	{
		$updated_record=array(
			'id_order' => intval($count)
		);
		$db->update_query(
			'custommenu',
			$updated_record,
			'id='.intval($row['id'])
		);
		$count++;
	}
	$db->free_result($query);
	
	custommenu_cache_menu();
}

function custommenu_reordersubmenuitems($cm_id)
{
	global $db;
	
	$query = $db->simple_select('custommenu_sub', '*', 'cm_id='.intval($cm_id), array(
		'order_by' => 'id_order',
		'order_dir' => 'ASC'
	));
	
	$count = 1;
	while ($row = $db->fetch_array($query))
	{
		$updated_record=array(
			'id_order' => intval($count)
		);
		$db->update_query(
			'custommenu_sub',
			$updated_record,
			'id='.intval($row['id'])
		);
		$count++;
	}
	$db->free_result($query);
	
	custommenu_cache_menu();
}

function custommenu_user_is_member_of($usergroups=array(), $testgroups=array())
{
	$result=false;
	foreach($testgroups as $testgroup)
	{
		if(in_array($testgroup, $usergroups))
		{
			$result=true;
		}
	}
	return $result;
}

function custommenu_get_usergroups_selection()
{
	global $db,$lang;
	
	$lang->load('custommenu');
	
	$usergroups = array();
	$query      = $db->simple_select( "usergroups", "gid, title", "", array(
		 'order_by' => 'title',
		'order_dir' => 'ASC' 
	) );
	$usergroups[ '' ]    = $lang->custommenu_no_groups;
	//$usergroups[ '1' ]   = $lang->custommenu_unregistered_user;
	while ( $usergroup = $db->fetch_array( $query ) )
	{
		$usergroups[ (int) $usergroup[ 'gid' ] ] = $usergroup[ 'title' ];
	}
	$db->free_result( $query );

	return $usergroups;
}

// templates are a big mess so I put it to the end of the file
function custommenu_setup_stylessheet()
{
	global $PL;
	
	$styles=array(
		'#header ul.menu li a.menu_submenu' => array(
			'background' => 'transparent url(images/optc/arrow.png) no-repeat right center',
			'padding-right' => '15px'
		),
		'#header ul.menu li a.menu_submenu:hover' => array(
			'background' => 'transparent url(images/optc/arrow_hv.png) no-repeat right center'
		),
		'#header .submenu_popup' => array(
			'background-color' => 'black',
			'text-align' => 'left',
			'border-width' => '1px',
			'border-style' => 'solid',
			'border-color' => 'rgb(124, 124, 124)',
			'font-size' => 'smaller'
		),
		'#header .submenu_popup .submenu_item' => array(
			'border-width' => '1px',
			'border-style' => 'solid',
			'border-color' => 'white',
			'padding' => '5px'
		),
		'#header ul.menu li a' => array(
			'padding-left' => 'initial',
			'background-image' => 'none',
			'background-repeat' => 'initial'
		),
		'#logo ul.top_links a.search' => array(
			'background-position' => 'initial'
		),
		'#logo ul.top_links a.memberlist' => array(
			'background-position' => 'initial'
		),
		'#logo ul.top_links a.calendar' => array(
			'background-position' => 'initial'
		),
		'#logo ul.top_links a.help' => array(
			'background-position' => 'initial'
		),
		'#logo ul.top_links a.portal' => array(
			'background-position' => 'initial'
		)
	);
	$PL->stylesheet(
		'custommenu',
		$styles
	);
}

/* Exported by Hooks plugin Wed, 28 Aug 2013 10:37:27 GMT */
?>
