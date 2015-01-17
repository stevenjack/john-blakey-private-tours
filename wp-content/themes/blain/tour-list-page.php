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

	<div id="tour" class="content-area">
        <main id="main" class="site-main tour-main expanded" role="main">
			<?php while ( have_posts() ) : the_post(); ?>
                <?php $children = get_children(array('order' => 'ASC', 'post_parent' => get_the_ID())); ?>
                <?php $post = get_post(); ?>
                <header class="entry-header">
                    <h1 class="entry-title"><?= $post->post_title ?></h1>
                </header>
                <div class="row row-padding">
                    <div class="col-md-12 tour-image">
                        <?= types_render_field('tour-list-image', array("output" => "image")) ?>
                        <?= apply_filters('the_content', $post->post_content) ?>
                    </div>
                </div>
                <?php foreach ($children as $child_id => $child) { ?>
                    <div class="special-interest-cat">
                        <h3><?php echo $child->post_title; ?></h3>
                        <?php $tour_children = get_children(array('order' => 'ASC', 'post_parent' => $child_id)); ?>
                        <?php if (!empty($child->post_content)) { ?>
                            <div class="blurb">
                                <?= apply_filters('the_content', $child->post_content); ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($_GET["expanded"]): ?>
                        <?php foreach ($tour_children as $tour_child_id => $tour_child) { ?>
                            <div class="row tour-item" id="post-<?= $tour_child->ID ?>">
                                <div class="col-md-6">
                                    <?= types_render_field('tour-image', array("post_id" => $tour_child->ID, "output" => "image")) ?>
                                </div>
                                <div class="col-md-6">
                                    <div class="title">
                                        <div class="row">
                                            <div class="col-md-9">
                                                <h4 class="inner-title"><?= $tour_child->post_title ?></h4>
                                            </div>
                                            <div class="col-md-3">
                                                <h4 class="price">&pound;<?= types_render_field('tour-price', array('output' => "raw", "post_id" => $tour_child->ID))  ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="clear: both;"></div>
                                    <div class="blurb"><?= apply_filters('the_content', $tour_child->post_content) ?></div>
                                </div>
                            </div>
                            <?= ($tour_child != end($tour_children)) ? '<hr>' : '' ?>
                        <?php } ?>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($tour_children as $tour_child_id => $tour_child) { ?>
                            <li>
                                <a href="/?page_id=<?= get_the_ID() ?>&expanded=true#post-<?= $tour_child->ID ?>"><?= $tour_child->post_title ?></a>
                            </li>
                            <?php } ?>
                        </ul>
                     <?php endif; ?>
                <?php } ?>
			<?php endwhile; // end of the loop. ?>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
