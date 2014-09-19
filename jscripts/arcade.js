var Arcade = {
	init: function()
	{
		$(document).ready(function(){
		});
	},

	editScore: function(sid)
	{
		MyBB.popupWindow("/arcade.php?action=edit&sid="+sid);
	},
	
	submitScoreEdit: function(sid)
	{
		// Get form, serialize it and send it
		var datastring = $(".score_"+sid).serialize();
		$.ajax({
			type: "POST",
			url: "arcade.php",
			data: datastring,
			dataType: "html",
			success: function(data) {
				// Replace modal HTML
				$('.modal_'+sid).fadeOut('slow', function() {
					$('.modal_'+sid).html(data);
					$('.modal_'+sid).fadeIn('slow');
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