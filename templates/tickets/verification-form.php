<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<?php get_header(); ?>
<div id="primary" class="content-area">
	<div id="content" class="site-content" role="main">
		<div class="form-wrapper">
			<h3><?php _e('Additional Information is Required','opentickets-community-edition') ?></h3>
			<p>
				<?php _e('In order to allow you to view your ticket, we must first verify who you are, and that you should be able to view this ticket. To do that, we need to obtain some information from you, that we will use to verify who you are. If this information matches our record, then your ticket will be available to you.','opentickets-community-edition') ?>
			</p>
			<form action="" method="post">
				<label><?php _e('Email','opentickets-community-edition') ?></label>
				<input type="email" name="email" value="" class="widefat" />
				<div class="helper">
					<?php _e('This should be the email you used during the purchase of your ticket, as your billing email address.','opentickets-community-edition') ?>
				</div>

				<input type="hidden" name="verification_form" value="1" />
				<input type="submit" value="<?php _e('Submit','opentickets-community-edition') ?>" />
			</form>
		</div>
	</div>
</div>
<?php get_footer();
