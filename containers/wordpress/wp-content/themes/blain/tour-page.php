<?php
/**
 * Template Name: Tour page
 *
 * @package Blain
 */

get_header(); ?>

    <div id="tour" class="content-area">
        <main id="main" class="site-main tour-main" role="main">
            <?php while ( have_posts() ) : the_post(); ?>
                <?php $post = get_post(); ?>

                <div class="row">
                    <div class="col-md-6"><?= types_render_field('tour-image', array("output" => "image")) ?></div>
                    <div class="col-md-6">
                        <div class="title">
                            <div class="row">
                                <div class="col-md-9">
                                    <h4 class="inner-title"><?= $post->post_title ?></h4>
                                </div>
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                        <div class="blurb">
                            <?= apply_filters('the_content', $post->post_content) ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; // end of the loop. ?>
        </main><!-- #main -->
    </div><!-- #primary -->

<?php get_footer(); ?>
