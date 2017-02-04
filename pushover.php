<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Make sure we can't access this file directly from the browser.
if(!defined('IN_MYBB'))
{
	die('This file cannot be accessed directly.');
}

$plugins->add_hook('newthread_do_newthread_end', 'pushover_send_new_thread_notification');
$plugins->add_hook('datahandler_post_insert_post_end', 'pushover_send_new_reply_notification');

function pushover_info()
{
	return array(
		'name' => 'Pushover Notifications',
		'description' => 'Sends informations about new posts to users with the help of Puschover',
		'website' => 'https://github.com/fvjuzmu/mybb-pushover-plugin',
		'author' => 'soulflyman',
		'authorsite' => 'https://github.com/soulflyman',
		'version' => '1.0',
		'compatibility' => '18*',
		'codename' => 'pushover'
	);
}

function pushover_send_new_thread_notification()
{
	try
	{
		if(pushover_is_forum_blacklisted())
		{
			return;
		}

		$userName = $GLOBALS['new_thread']['username'];
		$parentID = $GLOBALS['forum']['pid'];
		$parentName = $GLOBALS['forum_cache'][$parentID]['name'];
		$threadID = $GLOBALS['thread_info']['tid'];
		$postSubject = $GLOBALS['new_thread']['subject'];

		$msgUrl = $GLOBALS['settings']['bburl'] . '/showthread.php?tid=' .$threadID . '&action=newpost';
		$msg = $userName . " hat dieses neue Thema in <i>" . $parentName . "</i> erstellt.";

		pushover_send_notifications($postSubject, $msg, $msgUrl);
	}
	catch(Exception $e)
	{
	}
}

function pushover_send_new_reply_notification()
{
	try
	{
		if($GLOBALS['post']['savedraft'] == 1 || pushover_is_forum_blacklisted())
		{
			return;
		}

		$userName = $GLOBALS['post']['username'];
		$forumName = $GLOBALS['forum']['name'];
		$parentID = $GLOBALS['forum']['pid'];
		$parentName = $GLOBALS['forum_cache'][$parentID]['name'];
		$threadID = $GLOBALS['post']['tid'];
		$postSubject = $GLOBALS['post']['subject'];

		$msgUrl = $GLOBALS['settings']['bburl'] . '/showthread.php?tid=' .$threadID . '&action=newpost';
		$msg = $userName . " hat auf ein Thema in <i>" . $parentName . "->" . $forumName . "</i> geantwortet.";

		pushover_send_notifications($postSubject, $msg, $msgUrl);
	}
	catch(Exception $e)
	{
	}
}

function pushover_is_forum_blacklisted()
{
	$blacklist = explode(',', $GLOBALS['settings']['pushover_blacklist']);

	if(!in_array($GLOBALS['forum']['pid'], $blacklist) && !in_array($GLOBALS['forum']['fid'], $blacklist))
	{
		return false;
	}

	return true;
}

function pushover_send_notifications($msgTitle, $msg, $msgUrl)
{
	global $db;

	$fid = "fid" . $GLOBALS['settings']['pushover_fid'];

	$date = new DateTime();
	$postTime = $date->getTimestamp();

	// GET pushover IDs of alle users that are not banned
	$queryUserKeys = $db->simple_select("userfields", "ufid, " . $fid, $fid . " is not NULL and " . $fid . " != '' and ufid not in (select uid from " . $db->table_prefix . "banned where lifted = 0)", array(
		"order_by" => 'ufid',
		"order_dir" => 'DESC'
	));

	//send notification to every user except the user which posted
	while($userKey = $db->fetch_array($queryUserKeys))
	{
		if($userKey['ufid'] == $GLOBALS['uid'])
		{
			continue;
		}

		send_pushover_notification_to_user_by_key($userKey[$fid], $msgTitle, $msg, $msgUrl, $postTime);
	}
}

function send_pushover_notification_to_user_by_key($userKey, $pushoverTitle, $pushoverMessage, $pushoverUrl, $postTime)
{
    ob_start();
	curl_setopt_array($ch = curl_init(), array(
		CURLOPT_HEADER => false,
		CURLOPT_URL => "https://api.pushover.net/1/messages.json",
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_VERBOSE => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => array(
			"token" => $GLOBALS['settings']['pushover_token'],
			"user" => $userKey,
			"title" => $pushoverTitle,
			"message" => $pushoverMessage,
			"url_title" => "Beitrag lesen",
			"url" => $pushoverUrl,
			"html" => 1,
			"timestamp" => $postTime
		),
		CURLOPT_SAFE_UPLOAD => true

		/*/CURLOPT_POST => true,
		CURLOPT_USERAGENT => 'api',
		CURLOPT_TIMEOUT => 1,
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_CONNECTTIMEOUT => 1,
		CURLOPT_DNS_CACHE_TIMEOUT => 10,
		CURLOPT_FRESH_CONNECT => true//*/
	));

	curl_exec($ch);
	curl_close($ch);
    ob_end_clean();
}

/*
 * _install():
 *   Called whenever a plugin is installed by clicking the 'Install' button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
*/
function pushover_install()
{
	global $db;

	$setting_group = array(
		'name' => 'pushover_settings',
		'title' => 'Pushover settings',
		'description' => 'Configure Pushover Notifications',
		'disporder' => 5, // The order your setting group will display
		'isdefault' => 0
	);

	$gid = $db->insert_query("settinggroups", $setting_group);

	$setting_array = array(
		'pushover_token' => array(
			'title' => 'Pushover API Token',
			'description' => 'Your Pushover API Token',
			'optionscode' => 'text',
			'disporder' => 1
		),
		'pushover_fid' => array(
			'title' => 'Custom Profile Field ID',
			'description' => 'The ID of the "Custom Profile Field" in which the users can enter there Pushover-ID',
			'optionscode' => 'text',
			'disporder' => 3
		),
		'pushover_blacklist' => array(
			'title' => 'Forum Blacklist',
			'description' => 'A Blacklist of Forum-IDs seperated by comma. All listet forums do not send a notification.',
			'optionscode' => 'text',
			'disporder' => 4
		)
	);

	foreach($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid'] = $gid;
		$db->insert_query('settings', $setting);
	}

	// Don't forget this!
	rebuild_settings();
}

/*
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
*/
function pushover_is_installed()
{
	global $mybb;

	if( array_key_exists('pushover_token', $mybb->settings) &&
		array_key_exists('pushover_blacklist', $mybb->settings) &&
		array_key_exists('pushover_fid', $mybb->settings) )
	{
		return true;
	}
	else
	{
		return false;
	}
}

/*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
*/
function pushover_uninstall()
{
	global $db;

	$db->delete_query('settings', "name IN ('pushover_token','pushover_blacklist','pushover_fid')");
	$db->delete_query('settinggroups', "name = 'pushover_settings'");

	// Don't forget this
	rebuild_settings();
}
