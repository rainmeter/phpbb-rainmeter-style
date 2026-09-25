/**
 *
 * @package phpBB Extension - Mark Post Unread
 * @copyright (c) 2015 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

jQuery(function($) {
	$.each(['mark_forums_read', 'mark_topics_read'], function(i, cbName) {
		var cbPhpbb = phpbb.ajaxCallbacks[cbName];
		phpbb.addAjaxCallback(cbName, function(res) {
			cbPhpbb(res);
			$.ajax({
				url		: markpostunread.updateSearchUnreadAction,
				type	: 'GET',
				cache	: false
			}).success(function(ajaxData) {
				if (ajaxData.search_unread) {
					var $item = $('li a[href$="search_id=unreadposts"]');
					var $span = $item.find('span');
					($span.length ? $span : $item).text(ajaxData.search_unread);
				}
			});
		});
	});
});
