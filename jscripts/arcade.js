/**
 * My Arcade
 * Copyright 2015 Starpaul20
 */

var Arcade = {
	init: function()
	{
		$(document).ready(function(){
		});
	},

	editScore: function(sid)
	{
		MyBB.popupWindow("/arcade.php?modal=1&action=edit&sid="+sid);
	},

	cancelTournament: function(tid)
	{
		MyBB.popupWindow("/tournaments.php?modal=1&action=cancel&tid="+tid);
	},

	submitScoreEdit: function(sid)
	{
		// Get form, serialize it and send it
		var datastring = $(".score_"+sid).serialize();
		$.ajax({
			type: "POST",
			url: "arcade.php?modal=1",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML
				$('.modal_'+sid).fadeOut('slow', function() {
					$('.modal_'+sid).html(data);
					$('.modal_'+sid).fadeIn('slow');
					$(".modal").fadeIn('slow');
				});
			},
			error: function(){
				  alert(lang.unknown_error);
			}
		});

		return false;
	},

	submitCancelTournament: function(tid)
	{
		// Get form, serialize it and send it
		var datastring = $(".tournament_"+tid).serialize();
		$.ajax({
			type: "POST",
			url: "tournaments.php?modal=1",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML
				$('.modal_'+tid).fadeOut('slow', function() {
					$('.modal_'+tid).html(data);
					$('.modal_'+tid).fadeIn('slow');
					$(".modal").fadeIn('slow');
				});
			},
			error: function(){
				  alert(lang.unknown_error);
			}
		});

		return false;
	}
};

Arcade.init();