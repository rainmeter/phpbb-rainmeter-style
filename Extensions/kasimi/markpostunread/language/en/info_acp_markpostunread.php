<?php

/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'MARKPOSTUNREAD_TITLE'						=> 'Mark Post Unread',
	'MARKPOSTUNREAD_CONFIG'						=> 'Configuration',
	'MARKPOSTUNREAD_CONFIG_UPDATED'				=> '<strong>Mark Post Unread </strong>Extension<br />» Configuration updated',

	'MARKPOSTUNREAD_GROUP_MARKPOSTUNREAD'		=> '«Mark post unread» button in viewtopic',
	'MARKPOSTUNREAD_ENABLED'					=> '«Mark post unread» button',
	'MARKPOSTUNREAD_ENABLED_EXP'				=> 'Important: <strong>Enable server-side topic marking</strong> is required to be set to <strong>Yes</strong> in the Load settings.',
	'MARKPOSTUNREAD_ENABLE_FAILED'				=> 'Enabling the «Mark post unread» button failed because <strong>Enable server-side topic marking</strong> is disabled.',
	'MARKPOSTUNREAD_MAX_DAYS'					=> 'Maximum age of posts, in days',
	'MARKPOSTUNREAD_MAX_DAYS_EXP'				=> 'When a user marks a post unread, a row in the topics_track table is inserted for each already read topic in the relevant forum with a last_post_time after the post_time of the post being marked unread. On a big board with a huge number of posts, it is conceivable that a lot of db storage could be used for this feature (e.g. if a lot of your users mark really old posts unread), as well as slow down big boards with lots of topics. With this option, you can limit the feature to posts that are no more than a specified number of days old. Enter 0 to allow your users to mark <strong>all</strong> posts unread.',

	'MARKPOSTUNREAD_GROUP_UNREADSEARCHLINK'		=> '«Unread posts» search link in the navbar',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK'			=> 'Behaviour of the «Unread posts» search link in the navbar',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK_EXP'		=> 'Options 2 and 3 only affect logged in users. Users who are not logged in always see «Unread posts».',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK_OPT1'		=> '1) Always display «Unread posts» (phpBB default)',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK_OPT2'		=> '2) Display «Unread posts» / «No unread posts»',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK_OPT2_EXP'	=> 'May slightly affect performance.',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK_OPT3'		=> '3) Display «Unread posts in X topics» / «No unread posts»',
	'MARKPOSTUNREAD_UNREAD_POSTS_LINK_OPT3_EXP'	=> 'May perceptibly affect performance.',
));
