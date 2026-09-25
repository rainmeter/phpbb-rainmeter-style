<?php

/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\markpostunread\event;

use kasimi\markpostunread\includes\core;
use phpbb\controller\helper;
use phpbb\event\data;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/* @var user */
	protected $user;

	/* @var helper */
	protected $helper;

	/* @var template */
	protected $template;

	/* @var core */
	protected $core;

	/* @var bool */
	protected $exist_unread = null;

	/** @var bool */
	protected $can_inject = false;

	/**
 	 * Constructor
	 *
	 * @param user		$user
	 * @param helper	$helper
	 * @param template	$template
	 * @param core		$core
	 */
	public function __construct(
		user $user,
		helper $helper,
		template $template,
		core $core
	)
	{
		$this->user		= $user;
		$this->helper	= $helper;
		$this->template	= $template;
		$this->core		= $core;
	}

	/**
	 * Register events
	 */
	static public function getSubscribedEvents()
	{
		return array(
			// Mark post unread button
			'core.viewtopic_modify_post_data'			=> 'viewtopic_lang_setup',
			'core.viewtopic_modify_post_row'			=> 'inject_mark_unread_button',

			// Permission
			'core.permissions'							=> 'add_permission',

			// Unread posts search link
			'core.get_unread_topics_modify_sql'			=> 'adjust_get_unread_topics_sql',
			'core.display_forums_modify_sql'			=> 'refuse_exist_unread',
			'core.display_forums_modify_template_vars'	=> 'accept_exist_unread',
			'core.page_footer'							=> 'update_search_unread_text',
		);
	}

	/**
	 * Event: core.viewtopic_modify_page_title
	 */
	public function viewtopic_lang_setup()
	{
		if ($this->core->can_use())
		{
			$this->can_inject = true;
			$this->user->add_lang_ext('kasimi/markpostunread', 'common');
			$this->template->assign_var('S_MARKPOSTUNREAD_31X', phpbb_version_compare(PHPBB_VERSION, '3.2.0-dev', '<'));
		}
	}

	/**
	 * Event: core.viewtopic_modify_post_row
	 *
	 * @param data $event
	 */
	public function inject_mark_unread_button($event)
	{
		if ($this->can_inject)
		{
			if ($this->core->is_valid_post_time($event['row']['post_time']))
			{
				$route_params = array(
					'return_forum_id'	=> (int) $event['row']['forum_id'],
					'unread_post_id'	=> (int) $event['row']['post_id'],
				);

				$event['post_row'] = array_merge($event['post_row'], array(
					'S_MARKPOSTUNREAD_ALLOWED'	=> true,
					'U_MARKPOSTUNREAD' 			=> $this->helper->route('kasimi_markpostunread_markpostunread_controller', $route_params),
				));
			}
		}
	}

	/**
	 * Event: core.permissions
	 *
	 * @param data $event
	 */
	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_markpostunread_use'] = array(
			'lang'	=> 'ACL_U_MARKPOSTUNREAD_USE',
			'cat'	=> 'misc',
		);
		$event['permissions'] = $permissions;
	}

	/**
	 * Event: core.get_unread_topics_modify_sql
	 *
	 * @param data $event
	 */
	public function adjust_get_unread_topics_sql($event)
	{
		if ($this->core->cfg('unread_posts_link'))
		{
			$sql_array = $event['sql_array'];
			$last_mark = (int) $event['last_mark'];
			$sql_extra = $event['sql_extra'];
			$sql_sort = $event['sql_sort'];
			$sql_array['WHERE'] = "
				(
					(tt.mark_time IS NOT NULL AND t.topic_last_post_time > tt.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NOT NULL AND t.topic_last_post_time > ft.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NULL AND t.topic_last_post_time > $last_mark)
				)
				$sql_extra
				$sql_sort";
			$event['sql_array'] = $sql_array;
		}
	}

	/**
	 * Event: core.display_forums_modify_sql
	 *
	 * @param data $event
	 */
	public function refuse_exist_unread($event)
	{
		// initialize the flag to signal that we have already checked unreads in functions_display()
		// so that when check_unread_posts() is called in the future it can skip the sql query and give the
		// answer that there are none (this gets reset to true later on in functions_display() if there are unreads),
		// but do NOT initialize if the user is on viewforum since the test for unreads may give false negatives in that context
		if (empty($event['sql_ary']['WHERE']))
		{
			$this->exist_unread = false;
		}
	}

	/**
	 * Event: core.display_forums_modify_template_vars
	 *
	 * @param data $event
	 */
	public function accept_exist_unread($event)
	{
		// if there are any unread topics, set $exist_unread flag to true so that
		// when check_unreads_flag() is called in the future it can skip the sql query
		// and give the answer that there are unread posts
		if ($event['forum_row']['S_UNREAD_FORUM'])
		{
			$this->exist_unread = true;
		}
	}

	/**
	 * Event: core.page_footer
	 */
	public function update_search_unread_text()
	{
		$this->template->assign_vars(array(
			'L_SEARCH_UNREAD'								=> $this->core->get_search_unread_text($this->exist_unread),
			'U_MARKPOSTUNREAD_UPDATE_SEARCH_UNREAD_ACTION'	=> $this->core->cfg('unread_posts_link') ? $this->helper->route('kasimi_markpostunread_searchunread_controller', array(), false) : '',
		));
	}
}
