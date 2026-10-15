<?php

/**
 * Markdown extension for phpBB.
 * @author Alfredo Ramos <alfredo.ramos@proton.me>
 * @copyright 2019 Alfredo Ramos
 * @license GPL-2.0-only
 */

namespace alfredoramos\markdown\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\user;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\routing\helper as routing_helper;
use phpbb\language\language;
use alfredoramos\markdown\includes\helper;
use s9e\TextFormatter\Configurator\Helpers\AVTHelper;

class listener implements EventSubscriberInterface
{
	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var user */
	protected $user;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var routing_helper */
	protected $routing_helper;

	/** @var language */
	protected $language;

	/** @var helper */
	protected $helper;

	/** @var array */
	protected $tables = [];

	/** @var bool */
	private $markdown_enabled;

	/**
	 * Listener constructor.
	 *
	 * @param auth				$auth
	 * @param config			$config
	 * @param user				$user
	 * @param request			$request
	 * @param template			$template
	 * @param routing_helper	$routing_helper
	 * @param language			$language
	 * @param helper			$helper
	 * @param string			$posts_table
	 *
	 * @return void
	 */
	public function __construct(auth $auth, config $config, user $user, request $request, template $template, routing_helper $routing_helper, language $language, helper $helper, string $posts_table)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->user = $user;
		$this->request = $request;
		$this->template = $template;
		$this->routing_helper = $routing_helper;
		$this->language = $language;
		$this->helper = $helper;
		$this->markdown_enabled = !empty($this->config['allow_markdown']) &&
			!empty($this->user->data['user_allow_markdown']);

		// Assign tables
		if (empty($this->tables))
		{
			$this->tables = [
				'posts' => $posts_table
			];
		}
	}

	/**
	 * Assign functions defined in this class to event listeners in the core.
	 *
	 * @return array
	 */
	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.acp_board_config_edit_add' => 'acp_markdown_configuration',
			'core.permissions' => 'acp_markdown_permissions',
			'core.text_formatter_s9e_configure_after' => 'configure_markdown',
			'core.text_formatter_s9e_parser_setup' => 'enable_markdown',
			'core.text_formatter_s9e_render_before' => 'plain_block_spacing',
			'core.ucp_display_module_before' => 'ucp_markdown_status',
			'core.ucp_prefs_post_data' => 'ucp_markdown_configuration',
			'core.ucp_prefs_post_update_data' => 'ucp_markdown_configuration_data',
			'core.posting_modify_default_variables' => 'default_post_data',
			'core.posting_modify_message_text' => 'check_forum_permissions',
			'core.posting_modify_submit_post_before' => 'add_post_data',
			'core.submit_post_modify_sql_data' => 'save_post_data',
			'core.posting_modify_template_vars' => 'posting_template_variables',
			'core.ucp_pm_compose_template' => 'pm_template_variables',
			'core.ucp_pm_compose_modify_parse_before' => 'check_pm_permissions',
			'core.message_parser_check_message' => 'check_signature_permissions'
		];
	}

	/**
	 * Load language files.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name'	=> 'alfredoramos/markdown',
			'lang_set'	=> 'posting'
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Add markdown configuration in ACP.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function acp_markdown_configuration($event)
	{
		// Allowed modes
		$modes = [
			'features',
			'post',
			'message',
			'signature'
		];

		if (!in_array($event['mode'], $modes, true))
		{
			return;
		}

		$event['display_vars'] = $this->helper->acp_configuration(
			$event['display_vars'],
			$event['mode']
		);
	}

	/**
	 * Add Markdown permissions.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function acp_markdown_permissions($event)
	{
		$event->update_subarray(
			'permissions',
			'f_markdown',
			[
				'lang' => 'ACL_F_MARKDOWN',
				'cat' => 'content'
			]
		);

		$event->update_subarray(
			'permissions',
			'u_post_markdown',
			[
				'lang' => 'ACL_U_POST_MARKDOWN',
				'cat' => 'post'
			]
		);

		$event->update_subarray(
			'permissions',
			'u_pm_markdown',
			[
				'lang' => 'ACL_U_PM_MARKDOWN',
				'cat' => 'pm'
			]
		);

		$event->update_subarray(
			'permissions',
			'u_sig_markdown',
			[
				'lang' => 'ACL_U_SIG_MARKDOWN',
				'cat' => 'profile'
			]
		);
	}

	/**
	 * Configure markdown.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function configure_markdown($event)
	{
		$configurator = $event['configurator'];

		// Preserve literal newlines when Markdown is off, even if Litedown added paragraph rules.
		$line_break = $configurator->tags->add('MDPLAINBR');
		$line_break->template = '<br/><xsl:if test="@mdplainlast"><span aria-hidden="true">&#8203;</span></xsl:if>';
		$line_break->rules->breakParagraph(false);

		// Remove automatic space above a post's first block, and avoid adding a
		// bottom margin to explicit blank lines after a plain BBCode block.
		$plain_blocks = [
			'QUOTE' => '//blockquote',
			'CODE' => '//pre | //div[contains(concat(" ", @class, " "), " codebox ")] | //dl[contains(concat(" ", @class, " "), " codebox ")]',
			'LIST' => '//ul | //ol',
			'LI' => '//li'
		];
		foreach ($plain_blocks as $tag => $expression)
		{
			if (!isset($configurator->tags[$tag]))
			{
				continue;
			}
			$dom = $configurator->tags[$tag]->template->asDom();
			$xpath = new \DOMXPath($dom);
			foreach ($xpath->query($expression) as $node)
			{
				$condition = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:if');
				$condition->setAttribute('test', '@mdplainafter or @mdplaintop or @mdgap');
				$style = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:attribute');
				$style->setAttribute('name', 'style');
				$existing_style = $node->getAttribute('style');
				if ($existing_style !== '')
				{
					// Keep dynamic list types and other existing inline styles intact.
					$condition->setAttribute('test', 'true()');
					$node->removeAttribute('style');
					foreach (AVTHelper::parse($existing_style) as $token)
					{
						if ($token[0] === 'expression')
						{
							$value = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:value-of');
							$value->setAttribute('select', $token[1]);
							$style->appendChild($value);
						}
						else
						{
							$style->appendChild($dom->createTextNode($token[1]));
						}
					}
					$style->appendChild($dom->createTextNode(';'));
				}
				foreach (['mdplaintop' => 'margin-top:0;', 'mdplainafter' => 'margin-bottom:0;'] as $attribute => $css)
				{
					$margin = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:if');
					$margin->setAttribute('test', '@' . $attribute);
					$margin->appendChild($dom->createTextNode($css));
					$style->appendChild($margin);
				}
				if ($tag === 'LI')
				{
					$gap = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:if');
					$gap->setAttribute('test', '@mdgap');
					$gap->appendChild($dom->createTextNode('margin-top:'));
					$count = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:value-of');
					$count->setAttribute('select', '@mdgap');
					$gap->appendChild($count);
					$gap->appendChild($dom->createTextNode('em;'));
					$style->appendChild($gap);
				}
				$condition->appendChild($style);
				$node->insertBefore($condition, $node->firstChild);
			}
			$dom->saveChanges();
		}

		// Check if plugins should be disabled
		if (empty($this->config['allow_markdown']))
		{
			unset(
				$configurator->Escaper,
				$configurator->Litedown,
				$configurator->PipeTables,
				$configurator->TaskLists
			);
			return;
		}

		// A custom HR BBCode may require attributes that Markdown rules do not have.
		// Keep its template and validation, and route Markdown rules to a separate tag.
		if (isset($configurator->tags['HR']))
		{
			$configurator->tags->add('MDHR')->template = '<hr/>';
			$configurator->tags['HR']->filterChain
				->prepend(__CLASS__ . '::markdown_horizontal_rule($tag, $parser)')
				->setJS('function(tag) {
					if (tag.isSelfClosingTag() && Object.keys(tag.getAttributes()).length === 0) {
						addSelfClosingTag("MDHR", tag.getPos(), tag.getLen());
						return false;
					}
					return true;
				}');
		}

		// Enable plugins
		$configurator->Escaper;
		$configurator->Litedown->addHeadersId();
		$configurator->PipeTables;
		$configurator->TaskLists;

		// List of tag that will get a CSS class
		$tags = [
			// Litedown
			'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
			'LIST', 'SPOILER', 'ISPOILER',

			// PipeTables
			'TABLE'
		];

		// Add CSS class
		foreach ($tags as $tag)
		{
			$tag = trim($tag);

			// Tag must exist
			if (!isset($configurator->tags[$tag]))
			{
				continue;
			}

			// Setup DOM
			$object = $configurator->tags[$tag];
			$dom = $object->template->asDom();
			$xpath = new \DOMXPath($dom);

			// XPath expression
			switch ($tag)
			{
				case 'LIST':
					$exp = '//ul | //ol';
					break;

				case 'SPOILER':
					$exp = '//details';
					break;

				case 'ISPOILER':
					$exp = '//span[contains(@class, "spoiler")]';
					break;

				default:
					$exp = '//' . strtolower($tag);
					break;
			}

			foreach ($xpath->query($exp) as $node)
			{
				$node->setAttribute('class', trim(sprintf(
					'%s markdown',
					trim($node->getattribute('class'))
				)));
				if ($tag === 'LIST')
				{
					// Restore paragraph spacing only for lists marked as loose Markdown.
					$class = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:attribute');
					$class->setAttribute('name', 'class');
					$class->appendChild($dom->createTextNode($node->getAttribute('class')));
					$loose = $dom->createElementNS('http://www.w3.org/1999/XSL/Transform', 'xsl:if');
					$loose->setAttribute('test', '@mdloose');
					$loose->appendChild($dom->createTextNode(' markdown-loose'));
					$class->appendChild($loose);
					$node->removeAttribute('class');
					$node->insertBefore($class, $node->firstChild);
				}
			}

			$dom->saveChanges();
		}
	}

	/**
	 * Separate Markdown rules from an existing custom HR BBCode.
	 */
	public static function markdown_horizontal_rule($tag, $parser)
	{
		if ($tag->isSelfClosingTag() && !$tag->getAttributes())
		{
			$parser->addSelfClosingTag('MDHR', $tag->getPos(), $tag->getLen());
			return false;
		}

		return true;
	}

	/**
	 * Enable markdown.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function enable_markdown($event)
	{
		$parser = $event['parser']->get_parser();
		if ($this->markdown_enabled)
		{
			$parser->disablePlugin('MarkdownPlainLineBreaks');
			return;
		}

		$parser->registerParser('MarkdownPlainLineBreaks', function ($text, $matches) use ($parser)
		{
			foreach ($matches as $match)
			{
				$parser->addSelfClosingTag('MDPLAINBR', $match[0][1], 1);
			}
		}, '/\n/');
		$parser->enablePlugin('MarkdownPlainLineBreaks');
		$parser->disablePlugin('Escaper');
		$parser->disablePlugin('Litedown');
		$parser->disablePlugin('PipeTables');
		$parser->disablePlugin('TaskLists');
	}

	/**
	 * Leave one structural newline at each BBCode block boundary to block spacing.
	 * Preserve additional newlines as intentional blank lines.
	 * Work on parsed tags so escaped or invalid BBCodes remain literal text.
	 */
	public function plain_block_spacing($event)
	{
		$plain_breaks = strpos($event['xml'], '<MDPLAINBR') !== false;
		if (!$plain_breaks && strpos($event['xml'], '<QUOTE') === false &&
			strpos($event['xml'], '<CODE') === false && strpos($event['xml'], '<LIST') === false)
		{
			return;
		}

		$dom = new \DOMDocument();
		if (!$dom->loadXML($event['xml'], LIBXML_NONET))
		{
			return;
		}

		$xpath = new \DOMXPath($dom);
		// Litedown retains paragraphs for loose lists; BBCode lists have an opening
		// <s> marker. Inspect it before the renderer discards source markup nodes.
		foreach ($xpath->query('//LIST[not(s)]') as $list)
		{
			if ($xpath->evaluate('boolean(LI/p)', $list))
			{
				$list->setAttribute('mdloose', '1');
			}
			foreach ($xpath->query('LI', $list) as $item)
			{
				// A loose list wraps every item in paragraphs, even if only one
				// pair of items has a blank line. Use the actual source separators.
				$separator = '';
				$previous = $item->previousSibling;
				while ($previous && $previous->nodeType === XML_TEXT_NODE &&
					trim($previous->textContent, " \t\r\n") === '')
				{
					$separator = $previous->textContent . $separator;
					$previous = $previous->previousSibling;
				}
				$blank_lines = substr_count($separator, "\n") - 1;
				if ($previous && $previous->nodeName === 'LI' && $blank_lines > 0)
				{
					$item->setAttribute('mdgap', (string) $blank_lines);
				}
			}
		}
		foreach ($xpath->query('//QUOTE | //CODE | //LIST') as $block)
		{
			$has_break = false;
			$leading = $block->parentNode === $dom->documentElement &&
				$this->is_plain_break_run($dom->documentElement->firstChild, $has_break, $block);
			if ($leading)
			{
				$block->setAttribute('mdplaintop', '1');
				if ($plain_breaks)
				{
					$this->unwrap_plain_break_paragraphs($dom->documentElement->firstChild, $block);
				}
			}
			else if ($plain_breaks)
			{
				$this->trim_plain_block_breaks($block->previousSibling, false);
			}
			if (!$plain_breaks)
			{
				continue;
			}
			$has_break = false;
			$trailing = $block->parentNode === $dom->documentElement &&
				$this->is_plain_break_run($block->nextSibling, $has_break) && $has_break;
			if ($trailing)
			{
				// At the end of a post every Enter is intentional, including the first.
				// Remove paragraph wrappers so their margins do not add extra space.
				$this->unwrap_plain_break_paragraphs($block->nextSibling);
				$blank_after = false;
				if ($block->nodeName === 'LIST')
				{
					// Keep the extension's last-list margin even when breaks follow it.
					$block->setAttribute('mdplainafter', '1');
				}
			}
			else
			{
				$blank_after = $this->trim_plain_block_breaks($block->nextSibling, true);
			}
			if ($blank_after && ($block->nodeName === 'QUOTE' || $block->nodeName === 'CODE'))
			{
				$block->setAttribute('mdplainafter', '1');
			}
			if ($block->nodeName === 'QUOTE' || $block->nodeName === 'LIST')
			{
				$this->trim_plain_block_breaks($block->firstChild, true);
				$this->trim_plain_block_breaks($block->lastChild, false);
			}
		}

		// A terminal <br> after text does not create a final line box in browsers.
		// Mark it for an invisible rendering anchor, without adding text to the post.
		$last = $dom->documentElement->lastChild;
		while ($last && ($last->nodeName === 'p' ||
			($last->nodeType === XML_TEXT_NODE && trim($last->textContent, " \t\r\n") === '')))
		{
			$last = ($last->nodeName === 'p') ? $last->lastChild : $last->previousSibling;
		}
		if ($last && $last->nodeName === 'MDPLAINBR')
		{
			$previous = $last->previousSibling;
			while ($previous && ($previous->nodeName === 'MDPLAINBR' ||
				($previous->nodeType === XML_TEXT_NODE && trim($previous->textContent, " \t\r\n") === '')))
			{
				$previous = $previous->previousSibling;
			}
			// A paragraph containing only breaks already renders its empty lines.
			if ($previous && !in_array($previous->nodeName, ['QUOTE', 'CODE', 'LIST', 'p', 's', 'e'], true))
			{
				$last->setAttribute('mdplainlast', '1');
			}
		}

		$event['xml'] = $dom->saveXML($dom->documentElement);
	}

	/**
	 * Check whether the rest of the post consists only of plain newlines/whitespace.
	 */
	private function is_plain_break_run($node, &$has_break, $stop = null)
	{
		while ($node && $node !== $stop)
		{
			if ($node->nodeName === 'MDPLAINBR')
			{
				$has_break = true;
			}
			else if ($node->nodeName === 'p')
			{
				if (!$this->is_plain_break_run($node->firstChild, $has_break))
				{
					return false;
				}
			}
			else if ($node->nodeType !== XML_TEXT_NODE || trim($node->textContent, " \t\r\n") !== '')
			{
				return false;
			}
			$node = $node->nextSibling;
		}
		return true;
	}

	/**
	 * Flatten a confirmed trailing whitespace run without removing any newlines.
	 */
	private function unwrap_plain_break_paragraphs($node, $stop = null)
	{
		while ($node && $node !== $stop)
		{
			$next = $node->nextSibling;
			if ($node->nodeName === 'p')
			{
				$this->unwrap_plain_break_paragraphs($node->firstChild);
				while ($node->firstChild)
				{
					$node->parentNode->insertBefore($node->firstChild, $node);
				}
				$node->parentNode->removeChild($node);
			}
			$node = $next;
		}
	}

	/**
	 * Trim only the nearest structural break, retaining its text for editing/unparsing.
	 * Return whether an explicit blank line remains at this boundary.
	 */
	private function trim_plain_block_breaks($node, $forward)
	{
		$remaining = 1;
		$blank_line = false;
		$this->trim_plain_block_break_run($node, $forward, $remaining, $blank_line);
		return $blank_line;
	}

	/**
	 * Share the break budget across paragraph wrappers at the same boundary.
	 * Return true when the entire run contains only structural whitespace.
	 */
	private function trim_plain_block_break_run($node, $forward, &$remaining, &$blank_line)
	{
		while ($node)
		{
			$next = $forward ? $node->nextSibling : $node->previousSibling;
			if ($node->nodeName === 'MDPLAINBR')
			{
				if ($remaining === 0)
				{
					$blank_line = true;
					return false;
				}
				--$remaining;
				$node->parentNode->replaceChild($node->ownerDocument->createTextNode($node->textContent), $node);
			}
			else if ($node->nodeName === 'p')
			{
				if (!$this->trim_plain_block_break_run($forward ? $node->firstChild : $node->lastChild, $forward, $remaining, $blank_line))
				{
					return false;
				}
				// Unwrap empty paragraphs so they do not retain a margin of their own.
				while ($node->firstChild)
				{
					$node->parentNode->insertBefore($node->firstChild, $node);
				}
				$node->parentNode->removeChild($node);
			}
			else if ($node->nodeName !== 's' && $node->nodeName !== 'e' &&
				($node->nodeType !== XML_TEXT_NODE || trim($node->textContent, " \t\r\n") !== ''))
			{
				return false;
			}
			$node = $next;
		}

		return true;
	}

	/**
	 * Check Markdown status in the UCP.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function ucp_markdown_status($event)
	{
		if (($event['id'] !== 'ucp_prefs' && $event['mode'] !== 'post') &&
			($event['id'] !== 'pm' && $event['mode'] !== 'compose') &&
			($event['id'] !== 'ucp_profile' && $event['mode'] !== 'signature')
		)
		{
			return;
		}

		// Globally enabled
		$enabled = !empty($this->config['allow_markdown']);

		// Post, private messages or signature
		$enabled = $enabled &&
			(
				!empty($this->config['allow_post_markdown']) ||
				!empty($this->config['allow_pm_markdown']) ||
				!empty($this->config['allow_sig_markdown'])
			);

		// User permissions for post, private messages or signature
		$enabled = $enabled &&
			(
				!empty($this->auth->acl_get('u_post_markdown')) ||
				!empty($this->auth->acl_get('u_pm_markdown')) ||
				!empty($this->auth->acl_get('u_sig_markdown'))
			);

		$allowed = !empty($this->config['allow_markdown']) &&
			!empty($this->user->data['user_allow_markdown']);

		if ($event['id'] === 'pm' && $event['mode'] === 'compose')
		{
			$allowed = !empty($this->config['allow_markdown']) &&
				!empty($this->config['allow_pm_markdown']) &&
				!empty($this->auth->acl_get('u_pm_markdown'));
			$checked = $this->request->is_set_post('message')
				? $this->request->variable('enable_markdown', false)
				: !empty($this->user->data['user_allow_markdown']);

			$this->template->assign_vars([
				'S_MARKDOWN_OPT_IN' => true,
				'S_MARKDOWN_CHECKED' => ($allowed && $checked) ? ' checked="checked"' : ''
			]);
		}
		else if ($event['id'] === 'ucp_profile' && $event['mode'] === 'signature')
		{
			$allowed = $allowed &&
				!empty($this->config['allow_sig_markdown']) &&
				!empty($this->auth->acl_get('u_sig_markdown'));
		}

		$this->template->assign_vars([
			'S_MARKDOWN_ENABLED' => $enabled,
			'S_MARKDOWN_ALLOWED' => $allowed,
			'L_MARKDOWN_STATUS' => $this->language->lang(
				'MARKDOWN_STATUS_FORMAT',
				$this->routing_helper->route('alfredoramos_markdown_help'),
				($allowed && (!isset($checked) || $checked)) ? $this->language->lang('MARKDOWN_IS_ON') : $this->language->lang('MARKDOWN_IS_OFF')
			)
		]);
	}

	/**
	 * Markdown configuration.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function ucp_markdown_configuration($event)
	{
		$this->language->add_lang('ucp/markdown', 'alfredoramos/markdown');

		$event['data'] = array_merge($event['data'], [
			'markdown' => $this->request->variable(
				'markdown',
				(bool) $this->user->data['user_allow_markdown']
			)
		]);

		$this->template->assign_var('S_MARKDOWN', $event['data']['markdown']);
	}

	/**
	 * Markdown configuration data.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function ucp_markdown_configuration_data($event)
	{
		$event['sql_ary'] = array_merge($event['sql_ary'], [
			'user_allow_markdown' => !empty($event['data']['markdown'])
		]);
	}

	/**
	 * Add default post data.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function default_post_data($event)
	{
		if (isset($event['post_data']['enable_markdown']))
		{
			return;
		}

		// New posts use the account default; edits retain the saved post setting.
		$event['post_data'] = array_merge($event['post_data'], [
			'enable_markdown' => !empty($this->user->data['user_allow_markdown']) &&
				!empty($this->config['allow_post_markdown']) &&
				!empty($this->auth->acl_get('f_markdown', $event['post_data']['forum_id'])) &&
				!empty($this->auth->acl_get('u_post_markdown'))
		]);
	}

	/**
	 * Check Markdown forum permissions.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function check_forum_permissions($event)
	{
		$event['post_data'] = array_merge($event['post_data'], [
			'enable_markdown' => $this->request->variable('enable_markdown', false)
		]);

		$this->markdown_enabled = !empty($this->config['allow_markdown']) &&
			!empty($this->config['allow_post_markdown']) &&
			!empty($this->auth->acl_get('f_markdown', $event['forum_id'])) &&
			!empty($this->auth->acl_get('u_post_markdown')) &&
			!empty($event['post_data']['enable_markdown']);

		if (!$this->markdown_enabled && $this->request->is_set_post('message'))
		{
			// phpBB's request->variable() trims the post before parsing or checksumming.
			// Restore only missing boundary newlines, preserving other listeners' edits.
			$submitted = $this->request->untrimmed_variable('message', '', true, request::POST);
			$message_parser = $event['message_parser'];
			foreach (['/\A[ \t\n]*/', '/[ \t\n]*\z/'] as $index => $pattern)
			{
				preg_match($pattern, $submitted, $original);
				preg_match($pattern, $message_parser->message, $current);
				$missing = substr_count($original[0], "\n") - substr_count($current[0], "\n");
				if ($missing > 0)
				{
					$newlines = str_repeat("\n", $missing);
					$message_parser->message = ($index === 0)
						? $newlines . $message_parser->message
						: $message_parser->message . $newlines;
				}
			}
		}
	}

	/**
	 * Add Markdown status to post data.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function add_post_data($event)
	{
		$event['data'] = array_merge($event['data'], [
			'enable_markdown' => (bool) $event['post_data']['enable_markdown']
		]);
	}

	/**
	 * Add Markdown status to SQL query of posts table.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function save_post_data($event)
	{
		$sql_data = $event['sql_data'];
		$sql_data[$this->tables['posts']]['sql'] = array_merge($sql_data[$this->tables['posts']]['sql'], [
			'enable_markdown' => (bool) $event['data']['enable_markdown']
		]);
		$event['sql_data'] = $sql_data;
	}

	/**
	 * Set template variables in posting editor.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function posting_template_variables($event)
	{
		$allowed = !empty($this->config['allow_markdown']) &&
			!empty($this->config['allow_post_markdown']) &&
			!empty($this->auth->acl_get('f_markdown', $event['forum_id'])) &&
			!empty($this->auth->acl_get('u_post_markdown'));

		$event['page_data'] = array_merge($event['page_data'], [
			'S_MARKDOWN_ALLOWED' => $allowed,
			'S_MARKDOWN_OPT_IN' => true,
			'L_MARKDOWN_STATUS' => $this->language->lang(
				'MARKDOWN_STATUS_FORMAT',
				$this->routing_helper->route('alfredoramos_markdown_help'),
				($allowed && !empty($event['post_data']['enable_markdown'])) ? $this->language->lang('MARKDOWN_IS_ON') : $this->language->lang('MARKDOWN_IS_OFF')
			),
			'S_MARKDOWN_CHECKED' => (!empty($event['post_data']['enable_markdown']) ? ' checked="checked"' : '')
		]);
	}

	/**
	 * Assign Markdown options whenever the private message composer is rendered.
	 * The UCP module ID may be numeric, so do not rely on it being "pm".
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function pm_template_variables($event)
	{
		$this->ucp_markdown_status(['id' => 'pm', 'mode' => 'compose']);
	}

	/**
	 * Check Markdown private messages permissions.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function check_pm_permissions($event)
	{
		$event['enable_markdown'] = $this->request->variable('enable_markdown', false);

		$this->markdown_enabled = !empty($this->config['allow_markdown']) &&
			!empty($this->config['allow_pm_markdown']) &&
			!empty($this->auth->acl_get('u_pm_markdown')) &&
			!empty($event['enable_markdown']);

		$this->template->assign_var(
			'S_MARKDOWN_CHECKED',
			$this->markdown_enabled ? ' checked="checked"' : ''
		);
	}

	/**
	 * Check Markdown signature permissions.
	 *
	 * @param object $event
	 *
	 * @return void
	 */
	public function check_signature_permissions($event)
	{
		if (!in_array($event['mode'], ['sig', 'text_reparser.user_signature'], true))
		{
			return;
		}

		$event['allow_markdown'] = empty($this->request->variable('disable_markdown', false));

		$this->markdown_enabled = $this->markdown_enabled &&
			!empty($this->config['allow_sig_markdown']) &&
			!empty($this->auth->acl_get('u_sig_markdown')) &&
			!empty($event['allow_markdown']);

		$this->template->assign_var(
			'S_MARKDOWN_CHECKED',
			empty($event['allow_markdown']) ? ' checked="checked"' : ''
		);
	}
}
