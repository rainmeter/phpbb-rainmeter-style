<?php
/**
*
* Hide Bots extension for the phpBB Forum Software package.
*
* @copyright 2016 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/
namespace rmcgirr83\hidebots;

/**
* Extension class for custom enable/disable/purge actions
*/
class ext extends \phpbb\extension\base
{
	/**
	 * Enable extension if phpBB version requirement is met
	 *
	 * @return bool
	 * @access public
	 */
	public function is_enableable()
	{
		$enableable = phpbb_version_compare(PHPBB_VERSION, '3.1.7', '>=');
		if (!$enableable)
		{
			$user = $this->container->get('user');
			$user->add_lang_ext('rmcgirr83/hidebots', 'common');
			trigger_error($user->lang('HIDEBOTS_REQUIRE_3.1.7'), E_USER_WARNING);
		}

		return true;
	}
}
