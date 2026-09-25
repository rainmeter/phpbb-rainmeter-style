<?php

/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\markpostunread\includes;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\content_visibility;
use phpbb\db\driver\driver_interface as db_interface;
use phpbb\exception\http_exception;
use phpbb\exception\runtime_exception;
use phpbb\user;

class core
{
	/**
	 * This value is used to limit the number of topics if the ACP option "Unread posts in X topics" is selected.
	 * If the actual number of unread topics of a user exceeds this limit, "Unread posts in over $UNREAD_TOPICS_LIMIT topics" is displayed.
	 */
	const UNREAD_TOPICS_LIMIT = 100;

	/* @var string */
	public $root_path;

	/* @var string */
	public $php_ext;

	/* @var user */
	protected $user;

	/* @var config */
	protected $config;

	/* @var auth */
	protected $auth;

	/* @var db_interface */
	protected $db;

	/* @var content_visibility */
	protected $content_visibility;

	/**
	 * Constructor
	 *
	 * @param string				$root_path
	 * @param string				$php_ext
	 * @param user					$user
	 * @param config				$config
	 * @param auth					$auth
	 * @param db_interface			$db
	 * @param content_visibility	$content_visibility
	 */
	public function __construct(
		$root_path,
		$php_ext,
		user $user,
		config $config,
		auth $auth,
		db_interface $db,
		content_visibility $content_visibility
	)
	{
		$this->root_path			= $root_path;
		$this->php_ext				= $php_ext;
		$this->user					= $user;
		$this->config				= $config;
		$this->auth					= $auth;
		$this->db					= $db;
		$this->content_visibility	= $content_visibility;
	}

	/**
	 * Quick access to this extension's config values
	 *
	 * @param string $key
	 * @return string
	 */
	public function cfg($key)
	{
		return $this->config['kasimi.markpostunread.' . $key];
	}

	/**
	 * Returns true if the user is allowed to mark posts unread, false otherwise.
	 * Does not check post time validity.
	 *
	 * @return bool
	 */
	public function can_use()
	{
		// The board is set up to use cookies rather than the database to store read topic info, the user is not registered or the user is a bot
		return $this->cfg('enabled') && $this->auth->acl_get('u_markpostunread_use') && $this->config['load_db_lastread'] && $this->user->data['is_registered'] && !$this->user->data['is_bot'];
	}

	/**
	 * Returns false if the post_time is too old according to the ACP setting, otherwise true
	 *
	 * @param int $post_time
	 * @return bool
	 */
	public function is_valid_post_time($post_time)
	{
		$max_days = (int) $this->cfg('max_days');
		return $max_days == 0 || time() - (60 * 60 * 24 * $max_days) <= (int) $post_time;
	}

	/**
	 * Marks a post unread when the user clicks the mark post as unread link in viewtopic
	 *
	 * @param int $unread_post_id
	 */
	public function mark_unread_post($unread_post_id)
	{
		if (!$this->can_use())
		{
			throw new http_exception(403, 'NOT_AUTHORISED');
		}

		// Fetch the post_time, topic_id and forum_id of the post being marked as unread
		$sql = 'SELECT post_time, topic_id, forum_id, post_visibility
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . (int) $unread_post_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		// The post does not exist
		if (empty($row['topic_id']))
		{
			throw new runtime_exception('NO_TOPIC');
		}

		$user_id = $this->user->data['user_id'];
		$post_time = $row['post_time'];
		$mark_time = $post_time - 1;
		$topic_id = $row['topic_id'];
		$forum_id = $row['forum_id'];
		$is_post_visible = $this->auth->acl_get('m_approve', $forum_id) || $row['post_visibility'] == ITEM_APPROVED;

		// The user is not allowed to read it or the post is too old
		if (!$is_post_visible || !$this->auth->acl_get('f_read', $forum_id) || !$this->is_valid_post_time($post_time))
		{
			throw new runtime_exception('NO_TOPIC');
		}

		// set mark_time for the user and the relevant topic in the topics_track table
		// to the post_time of the post minus 1 (so that phpbb3 will think the post is unread)

		// The following update & insert queries are copied from the markread() function, case $mode == 'topic' to fix needless AND in SQL query
		$sql = 'UPDATE ' . TOPICS_TRACK_TABLE . '
			SET mark_time = ' . (int) $mark_time . '
			WHERE user_id = ' . (int) $user_id .
				// removed condition
				//AND mark_time < (int) $post_time
				' AND topic_id = ' . (int) $topic_id;
		$this->db->sql_query($sql);

		// insert row
		if (!$this->db->sql_affectedrows())
		{
			$sql_ary = array(
				'user_id'	=> $user_id,
				'topic_id'	=> $topic_id,
				'forum_id'	=> $forum_id,
				'mark_time'	=> $mark_time,
			);

			$this->db->sql_return_on_error(true);
			$this->db->sql_query('INSERT INTO ' . TOPICS_TRACK_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
			$this->db->sql_return_on_error(false);
		}

		// now, tinker with the forums_track and topics_track tables in accordance with these rules:
		//
		//	-	set $forum_tracking_info to be the mark_time entry for the user and relevant forum in the forum_tracks table;
		//		if there is no such entry, set $forum_tracking_info to be the user_lastmark entry for the user in the users table
		//
		//	-	if the post_time of the post is smaller (earlier) than $forum_tracking_info, then:
		//
		//		-	set mark_time for the user and the relevant forum in the forums_track table
		//			to the post_time for the post minus 1 (so that phpbb3 will think the forum is unread)
		//
		//		-	but before doing that, add a new topics_track entry
		//			(with mark_time = forum_tracking_info before the new mark_time entry is added to the forums_track table)
		//			for each other topic in the forum that meets all of the following tests
		//
		//			-	does not already have a topics_track entry for the user and forum
		//
		//			-	has a topic_last_post_time less than or equal to the then current forum_tracking_info
		//				(which shows that it has already been read)
		//
		//			-	has a last post time greater than the new mark_time that will be used for the forums_track table
		//
		//			The purpose of adding these new topics_track entries is to make sure that phpbb3
		//			will continue to treat already read topics as already read rather than incorrectly
		//			thinking they are unread because their topic_last_post_time is after the new
		//			mark_time for the relevant forum

		// so the first step: calculate the forum_tracking_info
		$sql = 'SELECT mark_time
			FROM ' . FORUMS_TRACK_TABLE . '
			WHERE forum_id = ' . (int) $forum_id . '
				AND user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$forum_tracking_info = !empty($row['mark_time']) ? $row['mark_time'] : $this->user->data['user_lastmark'];

		// next, check to see if the post being marked unread has a post_time at or before $forum_tracking_info
		if ($post_time <= $forum_tracking_info)
		{
			// ok, post being marked unread has post time at or before $forum_tracking_info, so we will
			// need to create special topics_track entries for all topics that
			// meet the three tests described in the comment that appears before the $sql definition above
			// (since these are the topics that are currently considered 'read' and would otherwise
			// no longer be considered read when we change the forums_track entry to an earlier mark_time
			// later in the script)

			// so, fetch the topic_ids and related info for the topics in this forum that meet the three tests
			$sql = 'SELECT t.topic_id, t.topic_last_post_time, tt.mark_time
				FROM ' . TOPICS_TABLE . ' t
				LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (t.topic_id = tt.topic_id AND tt.user_id = ' . (int) $user_id . ')
				WHERE tt.mark_time IS NULL
					AND t.forum_id = ' . (int) $forum_id . '
					AND t.topic_last_post_time <= ' . (int) $forum_tracking_info . '
					AND t.topic_last_post_time > ' . (int) $mark_time;
			$result = $this->db->sql_query($sql);
			$sql_insert_ary = array();

			// for each of the topics meeting the three tests, create a topics_track entry
			while ($row = $this->db->sql_fetchrow($result))
			{
				$sql_insert_ary[] = array(
					'user_id'	=> $user_id,
					'topic_id'	=> $row['topic_id'],
					'forum_id'	=> $forum_id,
					'mark_time'	=> $forum_tracking_info,
				);
			}
			$this->db->sql_multi_insert(TOPICS_TRACK_TABLE, $sql_insert_ary);
			$this->db->sql_freeresult($result);

			// finally, move the forums_track time back to $mark_time by inserting or updating the relevant row;
			// to do that, find out if there already is an entry for this user_id and forum_id
			$sql = 'SELECT forum_id
				FROM ' . FORUMS_TRACK_TABLE . '
				WHERE forum_id = ' . (int) $forum_id . '
					AND user_id = ' . (int) $user_id;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (isset($row['forum_id']))
			{
				// in this case there is already an entry for this user and forum_id
				// in the forums_track table, so update the entry for the forum_id
				$sql = 'UPDATE ' . FORUMS_TRACK_TABLE . '
					SET mark_time = ' . (int) $mark_time . '
					WHERE forum_id = ' . (int) $forum_id . '
						AND user_id = ' . (int) $user_id;
				$this->db->sql_query($sql);
			}
			else
			{
				// in this case there is no entry for this user and forum_id
				// in the forums_track table, so insert one
				$sql = 'INSERT INTO ' . FORUMS_TRACK_TABLE . ' ' . $this->db->sql_build_array('INSERT', array(
					'user_id'	=> $user_id,
					'forum_id'	=> $forum_id,
					'mark_time'	=> $mark_time,
				));
				$this->db->sql_query($sql);
			}
		}
	}

	/**
	 * Get number of topics that have unread posts. Returns a negative number to signal error.
	 *
	 * @param int $limit
	 * @param null|bool $exist_unread
	 * @return int
	 */
	protected function get_unread_topics_count($limit, $exist_unread)
	{
		if (!$this->config['load_db_lastread'] || !$this->user->data['is_registered'] || $this->user->data['is_bot'])
		{
			return -1;
		}

		$unread_topics_count = 0;

		if (is_null($exist_unread) || $exist_unread && $limit > 1)
		{
			$forum_ids = array_unique(array_keys($this->auth->acl_getf('f_read')));
			if (sizeof($forum_ids))
			{
				$sql_where = ' AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, $table_alias = 't.');
				$sql_sort = '';
				$unread_topics_count = sizeof(get_unread_topics($this->user->data['user_id'], $sql_where, $sql_sort, $limit));
			}
		}
		else
		{
			$unread_topics_count = $exist_unread ? 1 : 0;
		}

		return $unread_topics_count;
	}

	/**
	 * Returns the display text for the 'Unread posts' search link according to the extension config
	 *
	 * @param null|bool $exist_unread
	 * @return string
	 */
	public function get_search_unread_text($exist_unread = null)
	{
		switch ($this->cfg('unread_posts_link'))
		{
			// Nothing to do here, default behaviour
			case 0:
				break;

			// Display 'No unread posts' if there are none
			case 1:
				if ($this->get_unread_topics_count(1, $exist_unread) === 0)
				{
					$this->user->add_lang_ext('kasimi/markpostunread', 'common');
					return $this->user->lang('MARKPOSTUNREAD_UNREAD_NUM', 0);
				}
				break;

			// Display 'Unread posts in X topics' if there are unread posts, otherwise display 'No unread posts'
			case 2:
				$limit = self::UNREAD_TOPICS_LIMIT;
				$unread_topics_count = $this->get_unread_topics_count($limit + 1, $exist_unread);
				if ($unread_topics_count >= 0)
				{
					$this->user->add_lang_ext('kasimi/markpostunread', 'common');
					$lang_key = $unread_topics_count > $limit ? 'MARKPOSTUNREAD_UNREAD_NUM_MAX' : 'MARKPOSTUNREAD_UNREAD_NUM';
					return $this->user->lang($lang_key, min($unread_topics_count, $limit));
				}
				break;
		}

		return $this->user->lang('SEARCH_UNREAD');
	}
}
