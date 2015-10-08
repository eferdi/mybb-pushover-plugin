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
	
$plugins->add_hook('datahandler_post_insert_thread', 'send_new_thread_notification');
$plugins->add_hook('datahandler_post_insert_post', 'send_new_reply_notification');

function pushover_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * compatibility: A CSV list of MyBB versions supported. Ex, '121,123', '12*'. Wildcards supported.
	 * codename: An unique code name to be used by updated from the official MyBB Mods community.
	 */
	return array(
		'name'			=> 'Pushover Notifications',
		'description'	=> 'Sends informations about new posts to users with the help of Pushover.net',
		'website'		=> 'http://it-marx.de',
		'author'		=> 'eferdi',
		'authorsite'	=> 'http://it-marx.de',
		'version'		=> '0.10',
		'compatibility'	=> '18*',
		'codename'		=> 'pushover'
	);
}

function send_new_thread_notification()
{
	send_notifications(true);
	//sendDebug('newThreadHook');
}

function send_new_reply_notification()
{
	send_notifications(false);
	//sendDebug('newPostHook');
}

function sendDebug($message)
{
	send_pushover_notification_to_user_by_key('ujkGtmTupCGqWgfQFZXPtKDcmxKeA3', 'Plugin DEBUG', $message, '');
}

function send_notifications($newThread)
{
	global $mybb, $db, $post, $cache, $maintimer;
	$forumName = $cache->cache['forums'][$post['fid']]['name'];
	$parentForumIDs = $cache->cache['forums'][$post['fid']]['parentlist'];
	
	$date = new DateTime();
	$postTime = $date->getTimestamp();
	
	if(!empty($parentForumIDs))
	{
		$parentForumID = explode(',', $parentForumIDs);
		$forumName = $cache->cache['forums'][$parentForumID[0]]['name'] . '->' . $forumName;
	}
	
	$pushoverTitel = $post['subject'];
	if($newThread == true)
	{
		$pushoverMessage = $mybb->user['username'] . ' hat ein neues Thema in <i>' . $forumName . '</i> erstellt.';
	}
	else
	{
		$pushoverMessage = $mybb->user['username'] . ' hat auf ein Thema in <i>' . $forumName . '</i> geantwortet.';
	}
	
	$pushoverUrl = $mybb->settings['bburl']  . '/showthread.php?tid=' . $post['tid'] . '&action=newpost'; 
	
	//get plugin settings
	
	//if special forum then get only usersers of the special group
	//else
	//get all users
	$queryUserKeys = $db->simple_select("userfields", "ufid, fid6", "fid6 is not null", array(
		"order_by" => 'ufid',
		"order_dir" => 'DESC'
	));
	 
	while($userKeyTemp = $db->fetch_array($queryUserKeys))
	{
		$userKeys[] = $userKeyTemp;
	}
	
	$queryBannedUsers = $db->simple_select("banned", "uid", "uid is not null", array(
		"order_by" => 'uid',
		"order_dir" => 'DESC'
	));
	
	while($bannedUserTemp = $db->fetch_array($queryBannedUsers))
	{
		$bannedUsers[] = $bannedUserTemp;
	}
	
	if(!empty($userKeys))
	{
		foreach($userKeys as $userKey)
		{
			if($userKey['ufid'] == $mybb->user['uid'])
			{
				continue;
			}
			
			if(!empty($bannedUsers))
			{
				foreach($bannedUsers as $bannedUser)
				{
				
					if($bannedUser['uid'] == $userKey['ufid'])
					{
						continue;
					}
				}
			}
			
			send_pushover_notification_to_user_by_key($userKey['fid6'], $pushoverTitel, $pushoverMessage, $pushoverUrl, $postTime);
		}	
	}
	//get all banned users
	
    if($mybb->usergroup['cancp'] == 1)
    {
        // Admin only
    }
    else
    {
        // Everyone else
    }
}

function send_pushover_notification_to_user_by_key($userKey, $pushoverTitel, $pushoverMessage, $pushoverUrl, $postTime)
{
	ob_start();
	curl_setopt_array($ch = curl_init(), array(
	  CURLOPT_HEADER => false,
	  CURLOPT_URL => "https://api.pushover.net/1/messages.json",
	  CURLOPT_SSL_VERIFYPEER => false,
	  CURLOPT_VERBOSE => false,
	  CURLOPT_RETURNTRANSFER => false,
	  CURLOPT_POSTFIELDS => array(
		"token" => "",
		"user" => $userKey,
		"title" => $pushoverTitel,
		"message" => $pushoverMessage,
		"url_title" => "Beitrag lesen",
		"url" => $pushoverUrl,
		"html" => 1,
		"timestamp" => $postTime
	  ),
	  CURLOPT_SAFE_UPLOAD => true,
	));
	curl_exec($ch);
	curl_close($ch);
	//*/
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
	global $db, $mybb;

	$setting_group = array(
		'name' => 'pushover_settings',
		'title' => 'Pushover settings',
		'description' => 'Configure Pushover Notifications',
		'disporder' => 5, // The order your setting group will display
		'isdefault' => 0
	);

	$gid = $db->insert_query("settinggroups", $setting_group);
	
	
	$setting_array = array(
    'pushover_special_forums' => array(
        'title' => 'Pushover Special Forums',
        'description' => 'Define a list of Special Forums. Pushover notifications about new posts in these Forums will only send to the below selected group.',
        'optionscode' => 'forumselect',
        'disporder' => 1
    ),
    'pushover_special_group' => array(
        'title' => 'Pushover Special Group',
        'description' => 'Only these Groups will receive Pushover notifications for the above Forums.',
        'optionscode' => "groupselect",
        'disporder' => 2
    ),
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
	return true;		
}

/*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
*/
function pushover_uninstall()
{
	global $db;

	$db->delete_query('settings', "name IN ('pushover_special_forums','pushover_special_group')");
	$db->delete_query('settinggroups', "name = 'pushover_settings'");

	// Don't forget this
	rebuild_settings();
}
