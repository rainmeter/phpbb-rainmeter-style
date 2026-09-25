<?php

/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\markpostunread\acp;

class markpostunread_module
{
	public $u_action;

	protected $setting_prefix = 'kasimi.markpostunread.';

	protected $settings = array(
		'enabled'				=> 0,
		'max_days'				=> 0,
		'unread_posts_link'		=> 0,
	);

	function main($id, $mode)
	{
		global $config, $request, $template, $user, $phpbb_log;

		$user->add_lang('acp/common');

		$this->tpl_name = 'acp_markpostunread';
		$this->page_title = $user->lang('MARKPOSTUNREAD_TITLE');

		add_form_key('acp_markpostunread');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('acp_markpostunread'))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action));
			}

			foreach ($this->settings as $setting => $default)
			{
				if (!is_null($default))
				{
					$value = $request->variable($setting, $default, is_string($default));

					if ($setting === 'enabled' && $value && !$config['load_db_lastread'])
					{
						trigger_error($user->lang('MARKPOSTUNREAD_ENABLE_FAILED') . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$config->set($this->setting_prefix . $setting, $value);
				}
			}

			$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'MARKPOSTUNREAD_CONFIG_UPDATED');
			trigger_error($user->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template_vars = array();

		foreach ($this->settings as $setting => $default)
		{
			$setting_full = $this->setting_prefix . $setting;
			$key = strtoupper(str_replace('.', '_', $setting_full));
			$value = isset($config[$setting_full]) ? $config[$setting_full] : $default;
			settype($value, gettype($default));
			$template_vars[$key] = $value;
		}

		$template->assign_vars($template_vars);
	}
}
