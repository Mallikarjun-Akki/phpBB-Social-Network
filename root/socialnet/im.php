<?php
/**
 *
 * @package phpBB Social Network
 * @version 0.6.3
 * @copyright (c) 2010-2012 Kamahl & Culprit http://phpbbsocialnetwork.com
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

if (!defined('SOCIALNET_INSTALLED'))
{
	/**
	 * @ignore
	 */
	define('IN_PHPBB', true);
	/**
	 * @ignore
	 */
	define('SN_LOADER', 'im');
	define('SN_IM', true);
	$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
	$phpEx = substr(strrchr(__FILE__, '.'), 1);
	include_once($phpbb_root_path . 'common.' . $phpEx);
	include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);

	// Start session management
	$user->session_begin(false);
	$auth->acl($user->data);
	$user->setup('viewforum');

}

if (!class_exists('socialnet_im'))
{

	/**
	 * Socialnet_im trida
	 *
	 * @tutorial instant_messenger.pkg
	 * @package InstantMessenger
	 * @author Culprit
	 */

	class socialnet_im
	{
		var $p_master = null;
		var $config = array();
		var $items = array(
			'onlineUsers'	 => array(),
			'groups'		 => array(),
			'chatBoxes'		 => array(),
			'message'		 => array(),
			'user_online'	 => 0,
			'typing'		 => array(),
			'onlineList'	 => '',
			'onlineCount'	 => 0,
			'recd'			 => true,
		);
		var $closeSequence = array(
			'alt'	 => false,
			'ctrl'	 => false,
			'shift'	 => false,
			'key'	 => 27
		);
		var $sendSequence = array(
			'alt'	 => false,
			'ctrl'	 => false,
			'shift'	 => false,
			'key'	 => 13
		);

		/**
		 * Constructor function for module Instant Messenger
		 */
		function socialnet_im(&$p_master = null)
		{
			global $template, $db, $config, $user, $phpbb_root_path, $phpEx;

			$this->p_master =& $p_master;

			$this->config = array(
				'only_friends'		 => $config['im_only_friends'],
				'allow_sound'		 => $config['im_allow_sound'],
				'colour_username'	 => $config['im_colour_username'],
			);
			$this->items['lastCheckTime'] = time();

			$t_imaset_path = "{$phpbb_root_path}styles/" . $user->theme['imageset_path'] . '/imageset';
			$_phpbb_root_path = str_replace('\\', '/', $phpbb_root_path);
			$_script_path = str_replace('//', '/', str_replace('\\', '/', $config['script_path']) . '/');
			$t_imaset_path = preg_replace('#^' . preg_quote($_phpbb_root_path) . '#si', $_script_path, $t_imaset_path);
			$this->t_imaset_path = $t_imaset_path;

			$template_assign_vars = array();
			$this->config['my_avatar'] = $this->_open_chatbox_avatar($user->data['user_id']);
			if (!defined('SN_LOADER'))
			{
				$time = strtotime(date('d-m-Y ')) - ($config['im_msg_purged_automatic_time'] * 24 * 60 * 60);

				if ($config['im_msg_purged_automatic_time'] != 0 && $time != $config['im_msg_purged_time'])
				{
					$sql = "DELETE FROM " . SN_IM_TABLE . " WHERE recd = 1 AND sent < {$time}";
					$db->sql_query($sql);

					$sql = "UPDATE " . SN_CONFIG_TABLE . " SET config_value = '{$time}' WHERE config_name = 'im_msg_purged_time'";
					$db->sql_query($sql);

				}

				$c_onlinelistName = $config['cookie_name'] . '_sn_im_online';
				$c_onlinelist = request_var($c_onlinelistName, 'true', false, true);
				$template_assign_vars = array_merge($template_assign_vars, array(
					'SN_IM_MODE'			 => 'startIM',
					'SN_IM_ONLINELIST_SHOW'	 => $c_onlinelist == 'true',
				));

				$this->_startIM();
			}

			$closeKey = $this->_keyboardString($this->closeSequence);
			$sendKey = $this->_keyboardString($this->sendSequence);

			$template_assign_vars = array_merge($template_assign_vars, array(
				'SN_IM_ONLINE'			 => $user->data['user_im_online'] == 1 ? true : false,
				'S_SN_USERNAME'			 => addslashes($user->data['username']),
				'S_SN_IM_ONLINE'		 => $user->data['user_im_online'] == 1 ? 'online' : 'offline',
				'SN_IM_USERNAME'		 => $this->p_master->get_username_string($this->config['colour_username'], 'no_profile', $user->data['user_id'], $user->data['username'], $user->data['user_colour']),
				'SN_IM_USER_AVATAR'		 => $this->config['my_avatar'],
				'S_SN_IM_USER_SOUND'	 => $user->data['user_im_sound'],
				'S_SN_IM_USER_SOUNDNAME' => $user->data['user_im_soundname'],
				'SN_IM_MIN_CHECK'		 => $this->p_master->config['im_checkTime_min'],
				'SN_IM_MAX_CHECK'		 => $this->p_master->config['im_checkTime_max'],
				'SN_IM_LAST_CHECKTIME'	 => $this->items['lastCheckTime'],
				'SN_IM_PRESS_TO_CLOSE'	 => sprintf($user->lang['SN_IM_PRESS_TO_CLOSE'], $closeKey),
				'SN_IM_PRESS_TO_SEND'	 => sprintf($user->lang['SN_IM_PRESS_TO_SEND'], $sendKey),
				'SN_IM_CLOSESEQ_ALT'	 => $this->closeSequence['alt'] ? 'true' : 'false',
				'SN_IM_CLOSESEQ_CTRL'	 => $this->closeSequence['ctrl'] ? 'true' : 'false',
				'SN_IM_CLOSESEQ_SHIFT'	 => $this->closeSequence['shift'] ? 'true' : 'false',
				'SN_IM_CLOSESEQ_KEY'	 => $this->closeSequence['key'],
				'SN_IM_SENDSEQ_ALT'		 => $this->sendSequence['alt'] ? 'true' : 'false',
				'SN_IM_SENDSEQ_CTRL'	 => $this->sendSequence['ctrl'] ? 'true' : 'false',
				'SN_IM_SENDSEQ_SHIFT'	 => $this->sendSequence['shift'] ? 'true' : 'false',
				'SN_IM_SENDSEQ_KEY'		 => $this->sendSequence['key'],
			));

			$template->assign_vars($template_assign_vars);

			$this->items['onlineUsers'] = $this->p_master->onlineSelect();
			$this->items['groups'] = $this->p_master->groups;
			$this->items['onlineList'] = $this->onlineList();
			$this->items['onlineCount'] = count($this->items['onlineUsers']);

			$template->assign_var('SN_IM_ONLINELIST', $this->items['onlineList']);
		}

		/**
		 * Zakladni procedura pro nacteni
		 * @access public
		 * @param $mode Jaka cast IM
		 * @return void
		 */
		function load($mode)
		{
			global $template, $user;

			$this->items['user_online'] = $user->data['user_im_online'];

			switch ($mode)
			{
				case 'startIM':
					$this->_startIM();
				case 'snImLogin':
				case 'sn-im-login':
					$this->_snImLogout(1);
				case 'onlineUsers':
					//$this->items['onlineUsers'] = $this->p_master->onlineUsers();
					break;
				case 'onlineUsersCount':
					//$this->_onlineUsersCount('startIM');
					break;
				case 'openChatBox':
					$this->openChatBox();
					break;
				case 'closeChatBox':
					$this->closeChatBox();
					break;
				case 'sendMessage':
					$this->sendMessage();
					break;
				case 'coreIM':
					$this->core();
					break;
				case 'msg_time':
					$this->_msgTime();
					break;
				case 'snImLogout':
				case 'sn-im-logout':
					$this->_snImLogout(0);
					break;
				case 'snImSoundOff':
					$this->_snImSound(0);
					break;
				case 'snImSoundOn':
					$this->_snImSound(1);
					break;
				case 'snImUserGroupHide':
					$this->_snImUserGroup(1);
					break;
				case 'snImUserGroupShow':
					$this->_snImUserGroup(0);
					break;
				case 'snImTyping':
					$this->_snImTyping();
					return;
					break;
			}
			header('Content-type: application/json');
			header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
			header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
			die(json_encode($this->items));
		}

		/**
		 * Ovevri Chat Box
		 * @access private
		 * @return void
		 */
		function openChatBox()
		{
			global $db, $template, $user, $phpbb_root_path, $phpEx;

			$this->items['html'] = '';

			$userTo = (int) request_var('userTo', 0);
			$usernameTo = strip_tags(request_var('usernameTo', '', true));

			if ($userTo == 0 || $usernameTo == '')
			{
				return;
			}

			$this->items['onlineUsers'] = $this->p_master->onlineUsers();
			$usernameToSQL = $db->sql_escape($usernameTo);

			$sql = "INSERT INTO " . SN_IM_CHATBOXES_TABLE . " (uid_from, uid_to, username_to, starttime) VALUES ( {$user->data['user_id']}, {$userTo}, '{$usernameToSQL}', " . $user->data['session_start'] . ")";
			$db->sql_return_on_error(true);
			$return = $db->sql_query($sql);
			if (!$return)
			{
				$sql = "UPDATE " . SN_IM_CHATBOXES_TABLE . " SET starttime = '{$user->data['session_start']}' WHERE uid_from = '{$user->data['user_id']}' AND uid_to = '{$userTo}' AND username_to = '{$usernameToSQL}'";
				$db->sql_query($sql);
			}
			$db->sql_return_on_error(false);

			// DATA DO SABLONY
			$template->set_filenames(array(
				'body'	 => 'socialnet/im.html'
			));

			if (isset($this->items['onlineUsers'][$userTo]['status']))
			{
				$status = $this->items['onlineUsers'][$userTo]['status'];
			}
			else
			{
				$status = 0;
			}

			$userto_avatar = $this->_open_chatbox_avatar($userTo);

			if (!isset($this->p_master->friends['friends'][$userTo]) || !isset($this->p_master->friends['colourNames'][$userTo]['full']))
			{
				$this->p_master->get_friend('full', $userTo, $this->p_master->config['im_colour_username'], false);
			}

			$b_no_avatar_me = stripos($this->config['my_avatar'], 'socialnet/no_avatar') !== false ? true : false;
			$b_no_avatar_sender = stripos($userto_avatar, 'socialnet/no_avatar') !== false ? true : false;
			$template->assign_block_vars('sn_im_chatbox', array(
				'USER_ID'				 => $userTo,
				'U_PROFILE_USER'		 => $this->p_master->friends['colourNames'][$userTo]['full'],
				'USERNAME_TO'			 => $this->p_master->friends['friends'][$userTo],
				//'USERNAME_TO'			 => $this->items['onlineUsers'][$userTo]['userName'],
				'USERNAME_TO_NO_COLOR'	 => html_entity_decode($usernameTo),
				'USERNAME_TO_PROFILE'	 => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $userTo),
				'U_UCP_IM_HISTORY'		 => append_sid("{$phpbb_root_path}ucp.{$phpEx}", "i=socialnet&amp;mode=module_im_history&amp;u=" . $userTo),
				'S_OPEN'				 => true,
				'STATUS'				 => $status,
				'AVATAR'				 => $userto_avatar,
				'UNREAD'				 => 0,
			));

			$sql_from = $db->sql_in_set('uid_from', array(
				$user->data['user_id'],
				$userTo
			));
			$sql_to = $db->sql_in_set('uid_to', array(
				$user->data['user_id'],
				$userTo
			));
			$sql = "SELECT * FROM " . SN_IM_TABLE . "
					WHERE {$sql_from} AND {$sql_to} AND sent > {$user->data['session_last_visit']}
					ORDER BY sent";
			$result = $db->sql_query($sql);

			$p_sender = 0;
			while ($msg = $db->sql_fetchrow($result))
			{
				if ($msg['message'] != '0')
				{
					$message = generate_text_for_display($msg['message'], $msg['bbcode_uid'], $msg['bbcode_bitfield'], $this->p_master->bbCodeFlags);
				}
				else
				{
					$message = $msg['message'];
				}

				$same_sender = ($p_sender == $msg['uid_from']) ? true : false;
				$p_sender = $msg['uid_from'];

				if ($msg['uid_from'] == $user->data['user_id'])
				{
					$b_no_avatar = $b_no_avatar_me;
				}
				else
				{
					$b_no_avatar = $b_no_avatar_sender;
				}

				$template->assign_block_vars('sn_im_chatbox.message', array(
					'S_ME'			 => $user->data['user_id'] == $msg['uid_from'],
					'UID_FROM'		 => $msg['uid_from'],
					'S_UID_SAME'	 => $same_sender,
					'B_NO_AVATAR'	 => $b_no_avatar,
					'MESSAGE'		 => $message,
					'TIME'			 => $msg['sent'],
					'TIME_STRING' => $user->format_date($msg['sent'], "h:i"),
				));
			}

			$this->_markRecievedMessages();

			$this->p_master->page_header();
			$this->items['html'] = $this->p_master->page_footer();
		}

		/**
		 * Zavri ChatBox
		 * @access private
		 * @return void
		 */
		function closeChatBox()
		{
			global $db, $user, $config;

			$uid = request_var('uid', 0);
			$sql = "DELETE FROM " . SN_IM_CHATBOXES_TABLE . " WHERE uid_from = '{$user->data['user_id']}' AND uid_to = '{$uid}'";

			$db->sql_query($sql);
		}

		/**
		 * Posli zpravu
		 * @access private
		 * @return void
		 */
		function sendMessage()
		{
			global $db, $user, $config, $template;

			$uid = request_var('uid', 0);
			$message = utf8_normalize_nfc(request_var('message', '', true));
			if ($uid == 0 || $message == '')
			{
				return;
			}

			$this->_writeMessage($message, $uid);
		}

		/**
		 * Jadro - prijeti zpravy + otevreni neexistujiciho chatboxu
		 * @access private
		 * @return void
		 */
		function core()
		{
			global $db, $user, $template, $phpbb_root_path, $phpEx, $config;

			$lastCheckTime = request_var('lastCheckTime', $this->items['lastCheckTime']);

			$sql = "SELECT im.uid_from AS uid_from, im.uid_to AS uid_to, im.sent, im.recd, im.message AS message, im.bbcode_uid, im.bbcode_bitfield, cb.uid_from AS cb_from, cb.uid_to AS cb_to, u.username, u.user_colour
							FROM " . SN_IM_TABLE . " AS im
								LEFT OUTER JOIN " . SN_IM_CHATBOXES_TABLE . " AS cb ON im.uid_from = cb.uid_to AND im.uid_to = cb.uid_from
								LEFT OUTER JOIN " . USERS_TABLE . " AS u ON u.user_id = im.uid_from
							WHERE im.uid_to = '{$user->data['user_id']}' AND sent > {$lastCheckTime}
							ORDER BY sent ASC";
			$result = $db->sql_query($sql);

			$b_no_avatar_me = stripos($this->config['my_avatar'], 'socialnet/no_avatar') !== false ? true : false;

			$p_sender = 0;

			while ($row = $db->sql_fetchrow($result))
			{
				// Exists unread MSG ?
				if ($this->items['recd'] == true && $row['recd'] == 0)
				{
					$this->items['recd'] = false;
				}

				if ($row['message'] != '0')
				{
					$message = generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $this->p_master->bbCodeFlags);
				}
				else
				{
					$message = $row['message'];
				}

				if (isset($config['im_url_new_window']) && $config['im_url_new_window'])
				{
					$message = preg_replace('/(\<a class="postlink" href="[^"]*"[^>]*?)>/si', '$1 target="_blank">', $message);
				}

				$userto_avatar = $this->_open_chatbox_avatar($row['uid_from']);
				$same_sender = ($p_sender == $row['uid_from']) ? true : false;
				$p_sender = $row['uid_from'];

				$template->destroy();
				$template->set_filenames(array(
					'body'	 => 'socialnet/im.html'
				));

				$template->assign_block_vars('sn_im_chatbox', array(
					'USER_ID'				 => $row['uid_from'],
					'AVATAR'				 => $userto_avatar,
					'U_PROFILE_USER'		 => $this->p_master->get_username_string($this->config['colour_username'], 'full', $row['uid_from'], $row['username'], $row['user_colour']),
					'B_SN_IM_ONLY_MESSAGE'	 => true,
					'UNREAD'				 => 0
				));

				$b_no_avatar = false;
				$b_no_avatar_sender = stripos($userto_avatar, 'socialnet/no_avatar') !== false ? true : false;
				if ($row['uid_from'] == $user->data['user_id'])
				{
					$b_no_avatar = $b_no_avatar_me;
				}
				else
				{
					$b_no_avatar = $b_no_avatar_sender;
				}

				$template->assign_block_vars('sn_im_chatbox.message', array(
					'S_ME'			 => false,
					'UID_FROM'		 => $row['uid_from'],
					'S_UID_SAME'	 => $same_sender,
					'MESSAGE'		 => $message,
					'B_NO_AVATAR'	 => $b_no_avatar,
					'TIME'			 => $row['sent'],
					'TIME_STRING' => $user->format_date($row['sent'], "h:i"),
				));

				$template->assign_var('T_IMAGESET_PATH', $this->t_imaset_path);
				$content = $this->p_master->get_page(); // . htmlentities($userto_avatar) ."'".$b_no_avatar.'"';
				$this->items['message'][] = array(
					'uid'		 => $row['uid_from'],
					'userName'	 => $row['username'],
					'from'		 => $row['uid_from'],
					'message'	 => $content,
					'time'		 => $row['sent'],
					'chatBox'	 => $row['cb_from'] != '' && $row['cb_to'] != '',
					'sameSender' => $same_sender,
				);

			}

			$this->_markRecievedMessages();
			//$this->_onlineSelect();
			}

		/**
		 * Nacti existujici chatboxy
		 * @access private
		 * @param $mode string
		 */
		function _startIM($mode = '')
		{
			global $template, $user, $db, $config, $phpbb_root_path, $phpEx;

			/*$db->sql_query( 'TRUNCATE TABLE ' . SN_IM_CHATBOXES_TABLE);*/
			// Zrus stare chatboxy, otevrene pred prihlasenim
			$sql = "DELETE FROM " . SN_IM_CHATBOXES_TABLE . " WHERE uid_from = '{$user->data['user_id']}' AND starttime < '{$user->data['session_start']}'";
			$db->sql_query($sql);

			$this->items['onlineUsers'] = $this->p_master->onlineSelect();
			$this->items['onlineCount'] = count($this->items['onlineUsers']);

			// Vem otevrene chatboxy
			$sql = "SELECT * FROM " . SN_IM_CHATBOXES_TABLE . " WHERE uid_from = '{$user->data['user_id']}' AND starttime >= '{$user->data['session_start']}' ORDER BY starttime DESC";
			$rs = $db->sql_query($sql);
			$chatBoxRowSet = $db->sql_fetchrowset($rs);
			$db->sql_freeresult($rs);

			$b_no_avatar_me = stripos($this->config['my_avatar'], 'socialnet/no_avatar') !== false ? true : false;

			//while ($row = $db->sql_fetchrow($rs))
			for ($i = 0; isset($chatBoxRowSet[$i]); $i++)
			{
				$row = $chatBoxRowSet[$i];
				$status = isset($this->items['onlineUsers'][$row['uid_to']]['status']) ? $this->items['onlineUsers'][$row['uid_to']]['status'] : 0;
				$userto_avatar = $this->_open_chatbox_avatar($row['uid_to']);
				$c_onlinelistName = $config['cookie_name'] . '_sn_im_chatBox' . ($row['uid_to']);
				$s_open = request_var($c_onlinelistName, 'false', false, true);

				if (!isset($this->p_master->friends['friends'][$row['uid_to']]) || !isset($this->p_master->friends['colourNames'][$row['uid_to']]['full']))
				{
					$this->p_master->get_friend('full', $row['uid_to'], $this->p_master->config['im_colour_username'], false);
				}

				$unread = request_var("{$config['cookie_name']}_sn_im_chatBox{$row['uid_to']}Unread", 0, false, true);

				$template->assign_block_vars('sn_im_chatbox', array(
					'USER_ID'				 => $row['uid_to'],
					'U_PROFILE_USER'		 => $this->p_master->friends['colourNames'][$row['uid_to']]['full'],
					'USERNAME_TO'			 => $this->p_master->friends['friends'][$row['uid_to']],
					'USERNAME_TO_NO_COLOR'	 => html_entity_decode($row['username_to']),
					'USERNAME_TO_PROFILE'	 => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $row['uid_to']),
					'U_UCP_IM_HISTORY'		 => append_sid("{$phpbb_root_path}ucp.{$phpEx}", "i=socialnet&amp;mode=module_im_history&amp;u=" . $row['uid_to']),
					'S_OPEN'				 => $s_open == 'true',
					'USER_AVATAR'			 => '',
					'STATUS'				 => $status,
					'AVATAR'				 => $userto_avatar,
					'UNREAD'				 => $unread,
				));

				$sql_from = $db->sql_in_set('uid_from', array(
					$user->data['user_id'],
					$row['uid_to']
				));
				$sql_to = $db->sql_in_set('uid_to', array(
					$user->data['user_id'],
					$row['uid_to']
				));
				$sql = "SELECT * FROM " . SN_IM_TABLE . "
								WHERE {$sql_from} AND {$sql_to} AND sent > {$user->data['session_last_visit']}
								ORDER BY sent";
				$result = $db->sql_query($sql);
				$msgBoxRowSet = $db->sql_fetchrowset($result);
				$db->sql_freeresult($result);
				$previous_sender = 0;

				$b_no_avatar_sender = stripos($userto_avatar, 'socialnet/no_avatar') !== false ? true : false;

				//while ($msg = $db->sql_fetchrow($result))
				for ($j = 0; isset($msgBoxRowSet[$j]); $j++)
				{
					$msg = $msgBoxRowSet[$j];

					if ($msg['message'] != '0')
					{
						$message = generate_text_for_display($msg['message'], $msg['bbcode_uid'], $msg['bbcode_bitfield'], $this->p_master->bbCodeFlags);
					}
					else
					{
						$message = $msg['message'];
					}
					if (isset($config['im_url_new_window']) && $config['im_url_new_window'])
					{
						$message = preg_replace('/(\<a class="postlink" href="[^"]*"[^>]*?)>/si', '$1 target="_blank">', $message);
					}

					$b_no_avatar = false;
					if ($user->data['user_id'] == $msg['uid_from'])
					{
						$b_no_avatar = $b_no_avatar_me;
					}
					else
					{
						$b_no_avatar = $b_no_avatar_sender;
					}

					$template->assign_block_vars('sn_im_chatbox.message', array(
						'S_ME'			 => $user->data['user_id'] == $msg['uid_from'],
						'UID_FROM'		 => $msg['uid_from'],
						'S_UID_SAME'	 => $previous_sender == $msg['uid_from'],
						'MESSAGE'		 => $message,
						'B_NO_AVATAR'	 => $b_no_avatar,
						'TIME'			 => $msg['sent'],
						'TIME_STRING' => $user->format_date($msg['sent'], "h:i"), 
					));
					$previous_sender = $msg['uid_from'];
				}

				$this->_markRecievedMessages();
			}

		}

		function onlineList()
		{
			global $user, $template;

			$groups = $this->p_master->groups;
			$users = $this->items['onlineUsers'];
			$template->assign_var('SN_IM_ONLINE_COUNT', count($users));

			if (!empty($groups))
			{
				$in_group_added = array();
				$in_group_count = 0;
				foreach ($groups as $gid => $group)
				{
					$in_group = array();

					if (!empty($group['users']))
					{
						$user_group = array_flip($group['users']);

						$in_group = array_intersect_key($users, $user_group);
					}

					$template->assign_block_vars('sn_im_online_ufg', array(
						'GID'	 => $gid,
						'NAME'	 => $group['name'],
						'HIDDEN' => $group['collapse'],
						'COUNT'	 => count($in_group),
						'GROUP'	 => true,
					));

					if (!empty($in_group))
					{
						foreach ($in_group as $user_id => $usr)
						{
							$template->assign_block_vars('sn_im_online_ufg.user', array(
								'USER_ID'	 => $user_id,
								'AVATAR'	 => $usr['avatar'],
								'STATUS'	 => $usr['status'],
								'USERNAME'	 => $usr['userName'],
								'USERCLEAN'	 => addslashes($usr['userClean']),
								'ONLINE'	 => $usr['online'],
							));
							$users[$user_id]['in_group'] = true;
							$in_group_added[$user_id] = 'in_group';
							$in_group_count++;
						}
					}

				}

				$in_group = array_diff_key($users, $in_group_added);
				$template->assign_block_vars('sn_im_online_ufg', array(
					'GID'	 => '0',
					'NAME'	 => $user->lang['IM_GROUP_UNDECIDED'],
					'HIDDEN' => $groups[0]['collapse'],
					'COUNT'	 => count($in_group),
					'GROUP'	 => true
				));

				foreach ($in_group as $user_id => $usr)
				{
					$template->assign_block_vars('sn_im_online_ufg.user', array(
						'USER_ID'	 => $user_id,
						'AVATAR'	 => $usr['avatar'],
						'STATUS'	 => $usr['status'],
						'USERNAME'	 => $usr['userName'],
						'USERCLEAN'	 => addslashes($usr['userClean']),
						'ONLINE'	 => $usr['online'],
					));

				}
			}
			else
			{

				$template->assign_block_vars('sn_im_online_ufg', array(
					'GID'	 => '0',
					'NAME'	 => '',
					'HIDDEN' => false,
					'COUNT'	 => 1,
					'GROUP'	 => false,
				));

				foreach ($users as $user_id => $usr)
				{

					$template->assign_block_vars('sn_im_online_ufg.user', array(
						'USER_ID'	 => $user_id,
						'AVATAR'	 => $usr['avatar'],
						'STATUS'	 => $usr['status'],
						'USERNAME'	 => $usr['userName'],
						'USERCLEAN'	 => addslashes($usr['userClean']),
						'ONLINE'	 => $usr['online'],
					));
					//print_r($usr);print '<hr />';
					}
			}

			$template->set_filenames(array(
				'sn_im_online_list'	 => 'socialnet/im_onlinelist.html'
			));
			$return = $this->p_master->get_page('sn_im_online_list', false);
			//print '<pre>';print htmlentities($return) . '<br>';
			//print_r( $template->_tpldata);

			//die( __FILE__ . ' ' . __LINE__);

			//$template->destroy_block_vars('sn_im_online_ufg');

			return $this->items['onlineList'] = $return;
		}

		/**
		 * Zapis zpravu do DB
		 * @access private
		 * @param $message string Zprava
		 * @param $uid mixed Kdo komu posila, nebo jen komu
		 * @return void
		 */
		function _writeMessage($message, $uid)
		{
			global $user, $db, $template, $config, $starttime;

			if ($message == '')
			{
				return;
			}
			$this->items['msgs'] = $message;

			$pp = request_var('pp', 0);

			$bbuid = $bitfield = $flags = '';

			generate_text_for_storage($message, $bbuid, $bitfield, $flags, $this->p_master->allow_bbcode, $this->p_master->allow_urls, $this->p_master->allow_smilies);
			if (!is_array($uid))
			{
				$sql_arr = array(
					'uid_from'	 => $user->data['user_id'],
					'uid_to'	 => $uid
				);
			}
			else
			{
				$sql_arr = array(
					'uid_from'	 => $uid['from'],
					'uid_to'	 => $uid['to']
				);
			}

			$message_time = $starttime;

			$sql_arr = array_merge($sql_arr, array(
				'message'			 => $message,
				'sent'				 => $message_time,
				'recd'				 => 0,
				'bbcode_bitfield'	 => $bitfield,
				'bbcode_uid'		 => $bbuid
			));

			$sql = "INSERT INTO " . SN_IM_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_arr);
			$db->sql_query($sql);

			if ($message != '0')
			{
				$message = generate_text_for_display($message, $bbuid, $bitfield, $this->p_master->bbCodeFlags);
			}

			if (isset($config['im_url_new_window']) && $config['im_url_new_window'])
			{
				$message = preg_replace('/(\<a class="postlink" href="[^"]*"[^>]*?)>/si', '$1 target="_blank">', $message);
			}

			$template->destroy();
			$template->set_filenames(array(
				'body'	 => 'socialnet/im.html'
			));
			$template->assign_var('SN_IM_MODE', 'sendMessage');
			$template->assign_var('SN_IM_USERNAME', $this->p_master->get_username_string($this->config['colour_username'], 'no_profile', $user->data['user_id'], $user->data['username'], $user->data['user_colour']));
			$template->assign_var('SN_IM_USER_AVATAR', $this->config['my_avatar']);
			$template->assign_var('T_IMAGESET_PATH', $this->t_imaset_path);
			$template->assign_block_vars('sn_im_chatbox', array(
				'USER_ID'				 => $uid['to'],
				'B_SN_IM_ONLY_MESSAGE'	 => true
			));
			$template->assign_block_vars('sn_im_chatbox.message', array(
				'S_ME'			 => true,
				'UID_FROM'		 => $user->data['user_id'],
				'S_UID_SAME'	 => $user->data['user_id'] == $pp,
				'MESSAGE'		 => $message,
				'B_NO_AVATAR'	 => stripos($this->config['my_avatar'], 'socialnet/no_avatar') !== false ? true : false,
				'TIME'			 => $message_time,
				'TIME_STRING'	 => $user->format_date($message_time, "h:i"),
			));

			$this->p_master->page_header();
			$message = $this->p_master->page_footer();

			$this->items['message'] = $message;

		}

		/**
		 * Oznac zpravy jako dorucene
		 * @access private
		 * @return void
		 */
		function _markRecievedMessages()
		{
			global $user, $db;

			$lastCheckTime = request_var('lastCheckTime', $this->items['lastCheckTime']);
			$sql = "UPDATE " . SN_IM_TABLE . " SET recd = 1 WHERE uid_to = {$user->data['user_id']} AND sent <= {$lastCheckTime}";
			$result = $db->sql_query($sql);
		}

		/**
		 * Vrati time ago pre spravu
		 * @return void
		 */
		function _msgTime()
		{
			$unix_time = request_var('msg_time', 0);

			$time_ago = $this->p_master->time_ago($unix_time);

			$this->items['timeAgo'] = $time_ago;
		}

		/**
		 * Odhlaseni uzivatele z chatu
		 * Jedna se o globalni odhlaseni ze zakazanim modulu - stejne jako pres UCP
		 *
		 * @since 0.5.1
		 * @access private
		 * @param integer $enable 0 - zakazat, 1 - povolit
		 * @return void
		 */
		function _snImLogout($enable)
		{
			global $db, $user;

			//$enable = (int) request_var('snImEnable', 0);
			$sql = "UPDATE " . SN_USERS_TABLE . "
				SET user_im_online = '{$enable}'
				WHERE user_id = {$user->data['user_id']}";
			$db->sql_return_on_error(true);
			if (!$db->sql_query($sql))
			{
				$this->items[$enable ? 'login' : 'logout'] = false;
			}
			else
			{
				$this->items[$enable ? 'login' : 'logout'] = true;
				$db->sql_freeresult();
			}
		}

		/**
		 * Zapni / vypni sound
		 * Globalne vypnutie zvuku pre IM - rovnako ako cez UCP
		 *
		 * @since 0.5.1
		 * @access private
		 * @param integer $enable 0 - vypnut, 1 - zapnut
		 * @return void
		 */
		function _snImSound($enable)
		{
			global $db, $user;

			$sql = "UPDATE " . SN_USERS_TABLE . "
				SET user_im_sound = '{$enable}'
				WHERE user_id = {$user->data['user_id']}";
			$db->sql_return_on_error(true);
			if (!$db->sql_query($sql))
			{
				$this->items['sound'] = !$enable;
			}
			else
			{
				$this->items['sound'] = $enable;
				$db->sql_freeresult();
			}
		}

		/**
		* Show / hide user group
		*
		* @since 0.6.3
		* @access private
		* @param integer $enable 0 - show, 1 - hide
		* @return void
		*/
		function _snImUserGroup($hide)
		{
			global $db, $user;
		
			$gid = request_var('gid', 0);
		
			$sql = "UPDATE " . SN_FMS_GROUPS_TABLE . "
						SET fms_collapse = '{$hide}'
						WHERE fms_gid = '{$gid}'
							AND user_id = " . $user->data['user_id'];
			$db->sql_query($sql);
			$this->p_master->reload_groups($user->data['user_id']);
		}
		
		function _snImTyping()
		{
			global $db, $user;

			$typing_to = request_var('tto', 0);

			$sql = "UPDATE " . SN_USERS_TABLE . " SET user_im_typing_to = '{$typing_to}', user_im_typing = " . time() . " WHERE user_id = " . $user->data['user_id'];
			$db->sql_query($sql);
		}

		function _open_chatbox_avatar($uidTo)
		{
			global $db, $phpbb_root_path;
			$sql = "SELECT user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
				FROM " . USERS_TABLE . "
				WHERE user_id = '{$uidTo}'";

			$rs = $db->sql_query($sql);
			$rowAvatar = $db->sql_fetchrow($rs);
			$db->sql_freeresult($rs);

			return $this->p_master->get_user_avatar_resized($rowAvatar['user_avatar'], $rowAvatar['user_avatar_type'], $rowAvatar['user_avatar_width'], $rowAvatar['user_avatar_height'], 30);

		}

		function _keyboardString($sequence = array())
		{
			$closeKey = '';
			foreach ($sequence as $idx => $allow)
			{
				if ($idx == 'key')
				{
					continue;
				}
				if ($allow)
				{
					$closeKey .= (!empty($closeKey) ? '+' : '');
					$closeKey .= strtoupper($idx);
				}
			}
			$closeCode = explode('/', strtr(chr($sequence['key']), $this->p_master->charTranslation));
			$closeKey .= (!empty($closeKey) ? '+' : '') . strtoupper($closeCode[0]);

			return $closeKey;
		}
	}
}

if (isset($socialnet) && defined('SN_IM'))
{
	if ($user->data['user_type'] == USER_IGNORE || $config['board_disable'] == 1)
	{
		$ann_data = array(
			'user_id'		 => 'ANONYMOUS',
			'onlineUsers'	 => array(),
			'chatBoxes'		 => array(),
			'message'		 => array(),
			'user_online'	 => 0,
			'message'		 => array(),
			'onlineCount'	 => 0
		);

		header('Content-type: application/json');
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		die(json_encode($ann_data));
	}

	$mode = request_var('mode', 'startIM');

	$socialnet->modules_obj['im']->load($mode);

}

?>