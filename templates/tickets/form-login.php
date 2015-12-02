<?php ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) ? die( header( 'Location: /' ) ) : null; ?>
<?php get_header(); ?>
<div id="primary" class="content-area">
	<div id="content" class="site-content" role="main">
		<?php woocommerce_get_template( 'shop/form-login.php' ); ?>
	</div>
</div>
<?php get_footer();
