<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package Blain
 */
?>

	</div><!-- #content -->

	<footer id="colophon" class="site-footer container row" role="contentinfo">
	<?php if ( of_get_option('credit1', true) == 0 ) { ?>
		<div class="site-info pull-left">
			<?php do_action( 'blain_credits' ); ?>
			<?php printf( __( 'Blain Theme by %1$s.', 'blain' ), '<a href="http://inkhive.com/" rel="designer">InkHive</a>' ); ?>
		</div><!-- .site-info -->
	<?php } ?>
		<div id="footertext">
        	<?php
			if ( (function_exists( 'of_get_option' ) && (of_get_option('footertext2', true) != 1) ) ) {
			 	echo of_get_option('footertext2', true); } ?>
        	</div>
		<div class="item">
			Email: <a href="mailto:john@johnblakeyprivatetours.com">john@johnblakeyprivatetours.com</a>
		</div>
		<div class="item">
			Tel: <a href="tel:+447768616746">+44 (0)7768616746</a>
		</div>
		<div class="item">
			Skype: jb7traveller
		</div>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php
	if ( (function_exists( 'of_get_option' ) && (of_get_option('footercode1', true) != 1) ) ) {
			 	echo of_get_option('footercode1', true); } ?>
<?php wp_footer(); ?>
</body>
</html>
