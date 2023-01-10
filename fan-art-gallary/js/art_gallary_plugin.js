jQuery(document).ready(function() {
	jQuery(".art_list a").fancybox({		
		openEffect	: 'fade',
		closeEffect	: 'fade',
		padding : 0,
		margin      : [20, 60, 20, 60],
		helpers	: {
			thumbs	: {
				width	: 50,
				height	: 50
			}
		}		
	});
});
