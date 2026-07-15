<?php

/**
 * Helpers for the Planity notification Kanban embedded in the Monday module.
 *
 * The notification data is owned by the thirdpartynotify module; this file only
 * keeps Monday-specific rendering and access decisions out of main.php.
 */

function planity_kanban_render_left_menu()
{
	return 
		  '<ul id="planity-kanban-list" style="list-style:none;padding:0;">'
		. '<li id="planity-kanban-item" class="workspace-item planity-kanban-item">Kanban planity</li>'
		. '</ul>';
}

function planity_kanban_user_is_admin($user)
{
	return !empty($user->admin);
}

function planity_kanban_load_service()
{
	$classFile = DOL_DOCUMENT_ROOT.'/custom/thirdpartynotify/class/thirdpartynotify.class.php';
	if (!file_exists($classFile)) {
		return false;
	}
	require_once $classFile;
	return class_exists('ThirdpartyNotify');
}
