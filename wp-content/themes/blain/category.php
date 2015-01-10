<?php
/**
 * Template Name: Tour tagged page
 *
 * @package Blain
 */

get_header();
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);

query_posts(
    array (
        'category_name' => $category
    )
);
?>

    <div id="tours" class="content-area">
        <main id="main" class="site-main" role="main">
			<?php while ( have_posts() ) : the_post(); ?>
                <?php $post = get_post(); ?>

				<div class="row">
                    <div class="col-md-6"><?= types_render_field('tour-image', array("output" => "image")) ?></div>
  					<div class="col-md-6">
						<div class="title">
                            <h4><?= $post->post_title ?></h4>
							<span></span>
                            </span>&pound;<?= types_render_field('tour-price', array('output' => 'raw'))  ?></span>
						</div>
						<div class="blurb">
							<?= $post->post_content ?>
						</div>
					</div>
				</div>
			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
