<?php
/**
 * Template Name: Tour expanded page
 *
 * @package Blain
 */

get_header(); ?>

	<div id="tours" class="content-area">
        <main id="main" class="site-main" role="main">
			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'content', 'page' ); ?>

				<?php
					// If comments are open or we have at least one comment, load up the comment template
					if ( comments_open() || '0' != get_comments_number() )
						comments_template();
				?>

			<?php endwhile; // end of the loop. ?>


			<div class="row">
  				<div class="col-md-4"><?= get_image('tour_image') ?></div>
  				<div class="col-md-8"></p><?= get('blurb'); ?></p></div>
			</div>

			<h3>Classic tours of London - By Private Car</h3>

			<?php $tours = get_group('classic_tours');
				$index = 0;
			      foreach($tours as $tour) {
			?>
				<div class="row">
  					<div class="col-md-6"><img src="<?= $tour['classic_tours_image'][1]['original'] ?>"></div>
  					<div class="col-md-6">
						<div class="title">
							<h4><?= $tour['classic_tours_title'][1] ?></h4>
							<span><?= $tour['classic_tours_duration'][1] ?></span>
							</span>&pound;<?= $tour['classic_tours_price'][1] ?></span>
						</div>
						<div class="blurb">
							<?= get('classic_tours_blurb') ?>
						</div>
					</div>
				</div>
			<?php
			$index++;
			 }
			?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
