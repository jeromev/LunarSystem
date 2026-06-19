function confirmSubmit(string) { var agree = confirm(string); if (agree) { return true ; } else { return false ; } }
$(function(){
	$('.zebra tr:even').addClass('even');
	$('.zebra tr:odd').addClass('odd');
	$('.zebra tr').mouseover(function() { $(this).addClass('hover'); });
	$('.zebra tr').mouseout(function() { $(this).removeClass('hover'); });
	$('.box-handle.collapsed').toggle(
		function(){ $(this).removeClass("collapsed").addClass("expanded"); $(this).next('.box-content').show("slow"); },
		function(){ $(this).removeClass("expanded").addClass("collapsed"); $(this).next('.box-content').hide("slow"); }
	);
	$('.box-handle.expanded').toggle(
		function(){ $(this).removeClass("expanded").addClass("collapsed"); $(this).next('.box-content').hide("slow"); },
		function(){ $(this).removeClass("collapsed").addClass("expanded"); $(this).next('.box-content').show("slow"); }
	);
	$("ul.tv").find("li:last-child").addClass("tvil").end().find("li[ul]").addClass("tvic").removeClass("tvil").addClass("tvilc").append('<div class="tvca">').find("div.tvca").toggle(
		function(){ $(this).parent("li").removeClass("tvic").addClass("tvie").removeClass("tvilc").addClass("tvile").find(">ul").slideUp("normal"); },
		function(){ $(this).parent("li").removeClass("tvic").addClass("tvie").removeClass("tvilc").addClass("tvile").find(">ul").slideDown("normal"); }
	);
	// (WYSIWYG editor removed in the minimal build — content textareas are plain)
});