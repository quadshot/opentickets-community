<?php
/*
Template Name: OpenTickets Calendar
*/

get_header(); ?>

	<div id="content" class="row-fluid clearfix">
		<div class="span12">
        	<div id="page-entry">
            	<div class="fluid-row">
					<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<div class="calendar event-calendar">
	<div class="remove-if-js non-js-calendar-page-wrapper"><?php if (is_active_sidebar('qsot-calendar')): ?>
		<div class="calendar-widget-area"><?php dynamic_sidebar('qsot-calendar'); ?></div>
	<?php endif; ?></div>
</div>
<script> if (typeof jQuery == 'function') (function($) { $('.remove-if-js').remove(); })(jQuery);</script>
					</article>
	  			</div>	
			</div>
		</div>
	</div>
<?php get_footer(); ?>
