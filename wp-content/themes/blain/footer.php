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
		<p id="footertext" class="item">
        	<?php
			if ( (function_exists( 'of_get_option' ) && (of_get_option('footertext2', true) != 1) ) ) {
			 	echo of_get_option('footertext2', true); } ?>
        </p>
		<p class="item">
			Email: <a href="mailto:john.blakey7@btinternet.com">john.blakey7@btinternet.com</a>
		</p>
		<p class="item">
			From the USA: <a href="tel:011447939076514">011 44 7939 076 514</a>
		</p>
		<p class="item">
			Tel: <a href="tel:07939076514">07939 076 514</a>
		</p>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php
	if ( (function_exists( 'of_get_option' ) && (of_get_option('footercode1', true) != 1) ) ) {
			 	echo of_get_option('footercode1', true); } ?>
<?php wp_footer(); ?>
</body>
</html>
