<?php
/**
 * Template Name: Content page
 *
 * @package Blain
 */

get_header(); ?>

	<div id="tour" class="content-area">
        <main id="main" class="site-main tour-main content" role="main">
			<?php while ( have_posts() ) : the_post(); ?>
                <?php $post = get_post(); ?>
                <div class="entry-header">
                    <h1 class="entry-title"><?= $post->post_title ?></h1>
                </div>
				<div class="row">
                    <div class="col-md-6">
                        <div class="main-image">
                            <?= types_render_field('default-content-image', array("output" => "image")) ?>
                        </div>
                    </div>
  					<div class="col-md-6">
						<div class="blurb"><?= apply_filters('the_content', $post->post_content) ?></div>
					</div>
				</div>
			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
