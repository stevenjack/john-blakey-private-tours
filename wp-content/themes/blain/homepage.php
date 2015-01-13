<?php
/**
 * Template Name: Homepage
 *
 * @package Blain
 */

get_header(); ?>

<?php
    $children = null;
?>
	<div id="tours" class="content-area">
        <main id="main" class="site-main" role="main">
			<?php while ( have_posts() ) : the_post(); ?>
                <div class="quotes homepage-header">
                    <div class="col-md-3">
                        <?= types_render_field('left-hand-image', array("output" => "image")) ?>
                    </div>
                    <div class="col-md-9">
                        <?= types_render_field('slider-images', array("output" => "image")) ?>
                    </div>
                </div>
                <div class="col-md-12">

                    <blockquote>
                        <?= types_render_field('slider-testimonial', array('separator' => '</blockquote><blockquote>')) ?>
                    </blockquote>
                </div>
                <div class="clearfix visible-xs-block"></div>

                <?php $categories = get_categories(array('type' => 'page', 'hide_empty' => false)); ?>
                <?php foreach ($categories as $category) { ?>
                    <div class="col-xs-6 col-sm-4 tour">
                        <div class="caption">
                            <a href="<?= $category->slug ?>">
                                <h3><?= $category->name ?></h3>
                            </a>
                        </div>
                        <div class="region-box">
                            <a href="<?= $category->slug ?>">
                                <img width="100%" src="<?php echo z_taxonomy_image_url($category->term_id); ?>" />
                            </a>
                        </div>
                    </div>
                <?php } ?>
                <div class="clearfix visible-xs-block"></div>
                <div class="col-xs-6 col-sm-4 tour">
                    <h4>Also a proud member of:</h4>
                </div>
                <div class="clearfix visible-xs-block"></div>
                <div class="row">
                    <ul>
                        <li>
                            <?= types_render_field('member-of', array('separator' => '</li><li>')) ?>
                        </li>
                    </ul>
                </div>
            </div>

			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
