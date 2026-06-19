function confirmSubmit(string) { var agree = confirm(string); if (agree) { return true; } else { return false; } }
$(function(){
	$('.zebra tr:even').addClass('even');
	$('.zebra tr:odd').addClass('odd');
	$('.zebra tr').on('mouseover', function() { $(this).addClass('hover'); });
	$('.zebra tr').on('mouseout', function() { $(this).removeClass('hover'); });

	// Collapsible box handle — drives the bottom-bar hamburger and any .box-handle.
	// (jQuery removed the two-callback .toggle() in 1.9, so toggle off the current class.)
	$('.box-handle').on('click', function(){
		var $h = $(this);
		if ($h.hasClass('collapsed')) {
			$h.removeClass('collapsed').addClass('expanded').next('.box-content').show('slow');
		} else {
			$h.removeClass('expanded').addClass('collapsed').next('.box-content').hide('slow');
		}
	});

	// Tree view: tag items, then wire the expand/collapse carets (delegated).
	$('ul.tv').find('li:last-child').addClass('tvil').end()
		.find('li[ul]').addClass('tvic').removeClass('tvil').addClass('tvilc').append('<div class="tvca">');
	$('ul.tv').on('click', 'div.tvca', function(){
		var $li = $(this).parent('li');
		$li.removeClass('tvic').addClass('tvie').removeClass('tvilc').addClass('tvile');
		var $ul = $li.find('>ul');
		if ($ul.is(':visible')) { $ul.slideUp('normal'); } else { $ul.slideDown('normal'); }
	});
	// (WYSIWYG editor removed in the minimal build — content textareas are plain)
});
