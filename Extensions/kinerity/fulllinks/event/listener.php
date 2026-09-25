<?php
/**
 *
 * Full Links extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, kinerity, https://www.layer-3.org
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace kinerity\fulllinks\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Full Links Event listener.
 */
class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.text_formatter_s9e_configure_after'	=> 'onConfigure',
		);
	}

	/**
	 * Modifies the links on display
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function onConfigure($event)
	{
		unset($event['configurator']->tags['LINK_TEXT']->template);
	}
}
