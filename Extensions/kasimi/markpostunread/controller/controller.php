<?php

/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\markpostunread\controller;

use kasimi\markpostunread\includes\core;
use phpbb\controller\helper;
use phpbb\exception\http_exception;
use phpbb\user;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class controller
{
	/* @var user */
	protected $user;

	/* @var helper */
	protected $helper;

	/* @var core */
	protected $core;

	/**
 	 * Constructor
	 *
	 * @param user		$user
	 * @param helper	$helper
	 * @param core		$core
	 */
	public function __construct(
		user $user,
		helper $helper,
		core $core
	)
	{
		$this->user		= $user;
		$this->helper	= $helper;
		$this->core		= $core;
	}

	/**
	 * Action for route /markpostunread/{return_forum_id}/{unread_post_id}
	 *
	 * Marks a post unread and displays a redirect message
	 *
	 * @param int $return_forum_id
	 * @param int $unread_post_id
	 * @return Response
	 */
	public function markpostunread($return_forum_id, $unread_post_id)
	{
		$this->user->add_lang_ext('kasimi/markpostunread', 'common');
		$this->core->mark_unread_post($unread_post_id);

		$return_index = append_sid($this->core->root_path . 'index.' . $this->core->php_ext);
		$return_forum = append_sid($this->core->root_path . 'viewforum.' . $this->core->php_ext, 'f=' . $return_forum_id);

		meta_refresh(3, $return_forum);

		return $this->helper->message('MARKPOSTUNREAD_REDIRECT_FORMAT', array(
			$this->user->lang('MARKPOSTUNREAD_MARKED_UNREAD'),
			$this->user->lang('RETURN_FORUM', '<a href="' . $return_forum . '">', '</a>'),
			$this->user->lang('RETURN_INDEX', '<a href="' . $return_index . '">', '</a>'),
		));
	}

	/**
	 * Action for route /markpostunread/searchunread
	 *
	 * Returns the text for the 'Unread posts' search link in JSON
	 *
	 * @return JsonResponse
	 */
	public function searchunread()
	{
		// Don't allow usage if default behaviour is selected
		if (!$this->core->cfg('unread_posts_link'))
		{
			throw new http_exception(403, 'NOT_AUTHORISED');
		}

		$response = array(
			'search_unread'	=> $this->core->get_search_unread_text(),
		);

		$headers = array(
			'Cache-Control'	=> 'no-cache, no-store, must-revalidate',
			'Pragma'		=> 'no-cache',
			'Expires'		=> '0',
		);

		return JsonResponse::create($response, 200, $headers);
	}
}
