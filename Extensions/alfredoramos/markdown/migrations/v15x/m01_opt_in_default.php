<?php

namespace alfredoramos\markdown\migrations\v15x;

use phpbb\db\migration\migration;

class m01_opt_in_default extends migration
{
	static public function depends_on()
	{
		return [
			'\\alfredoramos\\markdown\\migrations\\v10x\\m02_user_configuration',
			'\\alfredoramos\\markdown\\migrations\\v13x\\m00_post_configuration'
		];
	}

	public function update_schema()
	{
		// Change the default for new accounts without overwriting saved preferences.
		return ['change_columns' => [USERS_TABLE => [
			'user_allow_markdown' => ['BOOL', 0]
		]]];
	}

	public function revert_schema()
	{
		return ['change_columns' => [USERS_TABLE => [
			'user_allow_markdown' => ['BOOL', 1]
		]]];
	}
}
