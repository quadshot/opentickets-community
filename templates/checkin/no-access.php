<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/*
Checkin Page: Check-In Failure
*/
//get_header();

$heading = $heading ? $heading : __('No access','opentickets-community-edition');
$msg = $msg ? $msg : __('You do not have sufficient permissions to perform this action!','opentickets-community-edition');
?><html><head><title><?php echo $heading.' - '.get_bloginfo('name') ?></title>
<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport" />
<meta name="viewport" content="width=device-width" />
<link href="<?php echo esc_attr($stylesheet) ?>" id="checkin-styles" rel="stylesheet" type="text/css" media="all" />
</head><body>
<div id="content" class="row-fluid clearfix">
	<div class="span12">
		<div id="page-entry">
			<div class="fluid-row">
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

					<div class="checked-in event-checkin no-access">
						<h1 class="page-title"><?php echo $heading ?></h1>
						<div class="error"><?php echo apply_filters('the_content', $msg) ?></div>
					</div>

				</article>
			</div>	
		</div>
	</div>
</div>
</body>
<?php
//get_footer();
