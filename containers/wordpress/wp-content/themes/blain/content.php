<?php
/**
 * @package Blain
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class("row archive"); ?>>
	
	<div class="featured-thumb col-md-3 col-xs-12">
	<a href="<?php the_permalink(); ?>">
	<?php if (has_post_thumbnail()) :
		the_post_thumbnail('homepage-thumb');	
		  else: ?>
		<img src="<?php echo get_stylesheet_directory_uri()."/images/dthumb.png"; ?>">  
		<?php
	endif; 
	?>
	</a>
	</div>
	<div class="col-md-9 col-xs-12">
	<header class="entry-header">
		<h1 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h1>

		<?php if ( 'post' == get_post_type() ) : ?>
		<div class="entry-meta">
			<?php blain_posted_on(); ?>
		</div><!-- .entry-meta -->
		<?php endif; ?>
	</header><!-- .entry-header -->

	<?php if ( is_search() ) : // Only display Excerpts for Search ?>
	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div><!-- .entry-summary -->
	<?php else : ?>
	<div class="entry-content">
	<?php if ( of_get_option('excerpt1', true) == 0 ) : ?>
		<?php the_content( __( 'Contine reading <span class="meta-nav">&rarr;</span>', 'blain' ) ); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . __( 'Pages: ', 'blain' ),
				'after'  => '</div>',
			) );
		else :
			the_excerpt();
		endif;		
		?>
	</div><!-- .entry-content -->
	<?php endif; ?>
	</div>
</article><!-- #post-## -->