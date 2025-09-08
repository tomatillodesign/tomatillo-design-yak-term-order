(function($){
	$(function(){
		var $list = $('#yto-sortable');
		if (!$list.length) return;

		// Live region for announcements
		var $live = $('#yto-live');
		if (!$live.length) {
			$live = $('<div id="yto-live" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>');
			$list.before($live);
		}

		// Make list sortable
		$list.sortable({
			axis: 'y',
			handle: '.yto-handle',
			placeholder: 'yto-placeholder',
			forcePlaceholderSize: true,
			start: function(e, ui){ ui.item.addClass('yto-dragging'); },
			stop:  function(e, ui){ ui.item.removeClass('yto-dragging').focus(); },
			update: announceOrder
		});

		// Keyboard reordering for a11y
		$list.on('keydown', '.yto-item', function(e){
			var $item = $(this);
			if (e.key === 'ArrowUp' || (e.altKey && e.key === 'PageUp')) {
				e.preventDefault();
				var $prev = $item.prev();
				if ($prev.length) $item.insertBefore($prev);
				announceOrder($item);
			}
			if (e.key === 'ArrowDown' || (e.altKey && e.key === 'PageDown')) {
				e.preventDefault();
				var $next = $item.next();
				if ($next.length) $item.insertAfter($next);
				announceOrder($item);
			}
		});

		// Clickable up/down buttons
		$list.on('click', '.yto-up', function(e){
			e.preventDefault();
			var $item = $(this).closest('.yto-item');
			var $prev = $item.prev();
			if ($prev.length) $item.insertBefore($prev);
			announceOrder($item);
		});
		$list.on('click', '.yto-down', function(e){
			e.preventDefault();
			var $item = $(this).closest('.yto-item');
			var $next = $item.next();
			if ($next.length) $item.insertAfter($next);
			announceOrder($item);
		});

		function announceOrder($focusItem){
			var items = $list.children('.yto-item');
			items.each(function(idx){
				var $it = $(this);
				$it.attr('aria-posinset', idx + 1).attr('aria-setsize', items.length);
			});
			if ($focusItem && $focusItem.length) {
				var idx = $focusItem.index() + 1;
				$live.text($focusItem.find('.yto-name').text() + ' moved to position ' + idx + ' of ' + items.length + '.');
			}
		}
		announceOrder();

		// —— AJAX SAVE ——
		$(document).on('click', '#yto-save', function(e){
			e.preventDefault();

			var $root = $('#yto-order-root');
			var taxonomy = $root.data('taxonomy');
			var parentId = $root.data('parent');
			var nonce = $root.data('nonce');
			var endpoint = $root.data('ajax') || (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '/wp-admin/admin-ajax.php');

			var order = [];
			$('#yto-sortable .yto-item').each(function(){
				order.push($(this).data('id'));
			});

			var $btn = $(this).prop('disabled', true);
			var $notice = $('<div class="notice is-dismissible"><p></p></div>');

			$.post(endpoint, {
				action: 'yto_inline_save_order',
				nonce: nonce,
				taxonomy: taxonomy,
				parent: parentId,
				order: order
			}).done(function(resp, status, xhr){
				if (resp && resp.success) {
					$notice.addClass('notice-success').find('p').text('Order saved.');
				} else {
					$notice.addClass('notice-error').find('p').text('Save failed. ' + (resp && resp.data && resp.data.message ? resp.data.message : 'Please try again.'));
					console.error('Yak Term Order: save failed', resp);
				}
			}).fail(function(xhr){
				$notice.addClass('notice-error').find('p').text('Network error. Please try again.');
				console.error('Yak Term Order: AJAX error', xhr.status, xhr.responseText);
			}).always(function(){
				$btn.prop('disabled', false);
				$('#yto-inline-panel').before($notice);
			});
		});
	});
})(jQuery);
