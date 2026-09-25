<?php

/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\markpostunread\acp;

class markpostunread_info
{
	function module()
	{
		return array(
			'filename'	=> '\kasimi\markpostunread\acp\markpostunread_module',
			'title'		=> 'MARKPOSTUNREAD_TITLE',
			'modes'		=> array(
				'settings' => array(
					'title'	=> 'MARKPOSTUNREAD_CONFIG',
					'auth'	=> 'ext_kasimi/markpostunread && acl_a_board',
					'cat'	=> array('MARKPOSTUNREAD_TITLE'),
				),
			),
		);
	}
}
