<?php
/**
 * Template Name: Tour list page
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
                <?php $children = get_children(array('order' => 'ASC', 'post_parent' => get_the_ID())); ?>
                <?php $post = get_post(); ?>

            <h1><?= $post->post_title ?></h1>
			<div class="row row-padding">
                <div class="col-md-12 tour-image">
                    <?= types_render_field('tour-list-image', array("output" => "image")) ?>
                    <?= apply_filters('the_content', $post->post_content) ?>
                </div>
                <!-- <div class="col-md-8">
                    <?= apply_filters('the_content', $post->post_content) ?>
                </div> -->
			</div>

            <?php foreach ($children as $child_id => $child) { ?>
                <div class="special-interest-cat">
                    <h3><?php echo $child->post_title; ?></h3>
                    <?php $tour_children = get_children(array('post_parent' => $child_id)); ?>
                    <?php if (!empty($child->post_content)) { ?>
                        <div class="blurb">
                            <?= apply_filters('the_content', $child->post_content); ?>
                        </div>
                    <?php } ?>
                    <ul>
                    <?php foreach ($tour_children as $tour_child_id => $tour_child) { ?>
                        <li>
                            <a href="<?= $tour_child->guid ?>"><?= $tour_child->post_title ?></a>
                        </li>
                    <?php } ?>
                    </ul>
                </div>
            <?php } ?>

			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
