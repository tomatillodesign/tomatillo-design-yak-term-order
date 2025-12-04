(function($) {
	'use strict';

	$(function() {
		console.log('YTO Post Order: Script loaded');
		
		var $panel = $('#yto-post-order-panel');
		if (!$panel.length) {
			console.log('YTO Post Order: No panel found, exiting');
			return;
		}

		var postType = $panel.data('post-type');
		var $table = $('#the-list');
		var $status = $('#yto-save-status');
		var i18n = window.ytoPostOrder ? window.ytoPostOrder.i18n : {};
		var isSaving = false;

		console.log('YTO Post Order: Panel found for post type:', postType);
		console.log('YTO Post Order: Table #the-list found:', $table.length > 0);
		console.log('YTO Post Order: Table rows:', $table.find('tr').length);

		// Create live region for a11y announcements
		var $live = $('<div id="yto-post-live" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>');
		$panel.append($live);

		console.log('YTO Post Order: Initializing sortable...');

		// Make table rows sortable with autosave
		$table.sortable({
			items: 'tr',
			axis: 'y',
			handle: '.yto-drag-handle',
			placeholder: 'yto-row-placeholder',
			forcePlaceholderSize: true,
			helper: function(e, tr) {
				var $originals = tr.children();
				var $helper = tr.clone();
				$helper.children().each(function(index) {
					$(this).width($originals.eq(index).width());
				});
				return $helper;
			},
			start: function(e, ui) {
				ui.item.addClass('yto-dragging');
				ui.placeholder.height(ui.item.height());
			},
			stop: function(e, ui) {
				ui.item.removeClass('yto-dragging');
				ui.item.find('.yto-drag-handle').focus();
			},
			update: function(e, ui) {
				console.log('YTO Post Order: Order changed, autosaving...');
				announcePosition(ui.item);
				autoSave();
			}
		});
		
		console.log('YTO Post Order: Sortable initialized successfully');
		console.log('YTO Post Order: Drag handles found:', $table.find('.yto-drag-handle').length);

		// Keyboard navigation for accessibility
		$table.on('keydown', 'tr', function(e) {
			var $row = $(this);

			// Alt+Up or Alt+Down to move rows
			if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
				e.preventDefault();
				
				if (e.key === 'ArrowUp') {
					var $prev = $row.prev('tr');
					if ($prev.length) {
						$row.insertBefore($prev);
						announcePosition($row);
						autoSave();
					}
				} else {
					var $next = $row.next('tr');
					if ($next.length) {
						$row.insertAfter($next);
						announcePosition($row);
						autoSave();
					}
				}
				
				$row.find('.yto-drag-handle').focus();
			}
		});

		/**
		 * Announce position change for screen readers
		 */
		function announcePosition($row) {
			var title = $row.find('.row-title').text() || $row.find('a.row-title').text() || 'Item';
			var index = $row.index() + 1;
			var total = $table.find('tr').length;
			
			$live.text(title + ' ' + (i18n.movedTo || 'moved to position') + ' ' + index + ' ' + (i18n.of || 'of') + ' ' + total);
		}

		/**
		 * Autosave the current order via AJAX
		 */
		function autoSave() {
			if (isSaving) {
				console.log('YTO Post Order: Already saving, skipping...');
				return;
			}

			var order = [];
			$table.find('tr').each(function() {
				var id = $(this).attr('id');
				if (id && id.indexOf('post-') === 0) {
					order.push(parseInt(id.replace('post-', ''), 10));
				}
			});

			if (!order.length) {
				console.log('YTO Post Order: No posts found to save');
				return;
			}

			isSaving = true;
			$status.text(i18n.saving || 'Saving...').addClass('yto-saving');
			console.log('YTO Post Order: Autosaving order:', order);

			$.ajax({
				url: window.ytoPostOrder.ajaxUrl,
				method: 'POST',
				data: {
					action: 'yto_save_post_order',
					nonce: window.ytoPostOrder.nonce,
					post_type: postType,
					order: order
				},
				success: function(response) {
					console.log('YTO Post Order: Autosave response:', response);
					if (response && response.success) {
						$status.text(i18n.saved || 'Saved').removeClass('yto-saving').addClass('yto-saved');
						
						// Update displayed order values
						var pos = 10;
						$table.find('tr').each(function() {
							$(this).find('.yto-order-value').text(pos);
							pos += 10;
						});
						
						// Fade out status after delay
						setTimeout(function() {
							$status.removeClass('yto-saved').text('');
						}, 1500);
					} else {
						$status.text(i18n.error || 'Error').removeClass('yto-saving').addClass('yto-error');
						console.error('YTO autosave error:', response);
						setTimeout(function() {
							$status.removeClass('yto-error').text('');
						}, 3000);
					}
				},
				error: function(xhr, status, error) {
					$status.text(i18n.error || 'Error').removeClass('yto-saving').addClass('yto-error');
					console.error('YTO AJAX error:', status, error, xhr.responseText);
					setTimeout(function() {
						$status.removeClass('yto-error').text('');
					}, 3000);
				},
				complete: function() {
					isSaving = false;
				}
			});
		}
	});
})(jQuery);

