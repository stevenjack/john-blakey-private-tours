<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package Blain
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php wp_title( '|', true, 'right' ); ?></title>
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>
	<header id="masthead" class="site-header row container" role="banner">
		<div class="site-branding col-md-8">
            <div class="site-title-image">
                <img src="/wp-content/themes/blain/images/blue_badge_small.jpg" />
             </div>
		<?php if((of_get_option('logo', true) != "") && (of_get_option('logo', true) != 1) ) { ?>
			<h1 class="site-title logo-container"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
			<?php
			echo "<img class='main_logo' src='".of_get_option('logo', true)."' title='".esc_attr(get_bloginfo( 'name','display' ) )."'></a></h1>";
			}
		else { ?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
		<?php
		}
		?>
		</div>

		<div id="social-icons" class="col-md-4">
			    <?php if ( of_get_option('facebook', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('facebook', true)); ?>" title="Facebook" ><i class="social-icon icon-facebook-sign"></i></a>
	             <?php } ?>
	            <?php if ( of_get_option('twitter', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url("http://twitter.com/".of_get_option('twitter', true)); ?>" title="Twitter" ><i class="social-icon icon-twitter-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('google', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('google', true)); ?>" title="Google Plus" ><i class="social-icon icon-google-plus-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('feedburner', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('feedburner', true)); ?>" title="RSS Feeds" ><i class="social-icon icon-rss-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('pinterest', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('pinterest', true)); ?>" title="Pinterest" ><i class="social-icon icon-pinterest-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('instagram', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('instagram', true)); ?>" title="Instagram" ><i class="social-icon icon-instagram"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('linkedin', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('linkedin', true)); ?>" title="LinkedIn" ><i class="social-icon icon-linkedin-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('youtube', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('youtube', true)); ?>" title="YouTube" ><i class="social-icon icon-youtube-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('tumblr', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('tumblr', true)); ?>" title="Tumblr" ><i class="social-icon icon-tumblr-sign"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('flickr', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('flickr', true)); ?>" title="Flickr" ><i class="social-icon icon-flickr"></i></a>
	             <?php } ?>
	             <?php if ( of_get_option('dribble', true) != "") { ?>
				 <a target='_blank' href="<?php echo esc_url(of_get_option('dribble', true)); ?>" title="Dribbble" ><i class="social-icon icon-dribbble"></i></a>
	             <?php } ?>
         </div>

	</header><!-- #masthead -->

	<div class="nav-wrapper container">
	<nav id="site-navigation" class="navbar navbar-default main-navigation" role="navigation">

			<div class="navbar-header">
		    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
		      <span class="sr-only">Toggle navigation</span>
		      <span class="icon-bar"></span>
		      <span class="icon-bar"></span>
		      <span class="icon-bar"></span>
		    </button>
		  </div>


			<?php
			    wp_nav_menu( array(
			        'theme_location'    => 'primary',
			        'depth'             => 2,
			        'container'         => 'div',
			        'container_class'   => 'collapse navbar-collapse navbar-ex1-collapse',
			        'menu_class'        => 'nav navbar-nav',
			        'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
			        'walker'            => new wp_bootstrap_navwalker())
			    );
?>
		</nav><!-- #site-navigation -->
	</div>
	<div id="content" class="site-content row container">
	<?php
	if ( (function_exists( 'of_get_option' )) && (of_get_option('slidetitle5',true) !=1) ) {
	if ( ( of_get_option('slider_enabled') != 0 ) && (is_home())  )
		{ ?>
	<div class="slider-wrapper theme-default">
    	<div class="ribbon"></div>
    		<div id="slider" class="nivoSlider">
    			<?php
		  		$slider_flag = false;
		  		for ($i=1;$i<6;$i++) {
		  			$caption = ((of_get_option('slidetitle'.$i, true)=="")?"":"#caption_".$i);
					if ( of_get_option('slide'.$i, true) != "" ) {
						echo "<a href='".of_get_option('slideurl'.$i, true)."'><img src='".of_get_option('slide'.$i, true)."' title='".$caption."'></a>";
						$slider_flag = true;
					}
				}
				?>
    		</div><!--#slider-->
    		<?php for ($i=1;$i<6;$i++) {
    				$caption = ((of_get_option('slidetitle'.$i, true)=="")?"":"#caption_".$i);
    				if ($caption != "")
    				{
	    				echo "<div id='caption_".$i."' class='nivo-html-caption'>";
	    				echo "<a href='".of_get_option('slideurl'.$i, true)."'><div class='slide-title'>".of_get_option('slidetitle'.$i, true)."</div></a>";
	    				echo "<div class='slide-description'>".of_get_option('slidedesc'.$i, true)."</div>";
	    				echo "</div>";
    				}
    			}

			?>
    </div>
	<?php
			}
		}
		?>
