<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') ); ?>
<?php get_header(); ?>
<div id="primary" class="content-area">
	<div id="content" class="site-content" role="main">
		<?php wc_get_template( 'shop/form-login.php' ); ?>
	</div>
</div>
<?php get_footer();
