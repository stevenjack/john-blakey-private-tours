jQuery(document).ready(function() {
		//Set up the Slider 
	jQuery("time.entry-date").timeago();

	jQuery(document).ready(function() {
		jQuery('.main-navigation .menu ul').superfish({
			delay:       1000,                            // 1 second avoids dropdown from suddenly disappearing
			animation:   {opacity:'show'},  			  // fade-in and slide-down animation
			speed:       'fast',                          // faster animation speed
			autoArrows:  false                            // disable generation of arrow mark-up
		});
	});
	
	/*	
	
	var $footer = jQuery('.home #secondary');
		// initialize
		$footer.imagesLoaded( function() {	
			$footer.masonry({
			  itemSelector: '.widget'
			});
		});	
		
	var $footer = jQuery('.page-template-page-full-width-php #secondary');
		// initialize
		$footer.imagesLoaded( function() {	
			$footer.masonry({
			  itemSelector: '.widget'
			});
		});	
*/
		
	jQuery(window).bind('scroll', function(e) {
		hefct();
	});		
	
});
 
 jQuery(window).load( function() {
 	jQuery('#slider').nivoSlider({effect:'boxRandom', pauseTime: 5500,});
 });   	
    	
function hefct() {
	var scrollPosition = jQuery(window).scrollTop();
	jQuery('#header-image').css('top', (0 - (scrollPosition * .2)) + 'px');
}	