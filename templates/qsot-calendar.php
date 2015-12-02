<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/*
Template Name: OpenTickets Calendar
*/

get_header();
?>

<?php do_action( 'qsot-before-calendar-content' ); ?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="calendar event-calendar calendar-content-wrap">
		<div class="remove-if-js non-js-calendar-page-wrapper"><?php if (is_active_sidebar('qsot-calendar')): ?>
			<div class="calendar-widget-area"><?php dynamic_sidebar('qsot-calendar'); ?></div>
		<?php endif; ?></div>
	</div>
	<script> if (typeof jQuery == 'function') (function($) { $('.remove-if-js').remove(); })(jQuery);</script>
</article>

<?php do_action( 'qsot-after-calendar-content' ); ?>

<?php
get_footer();
