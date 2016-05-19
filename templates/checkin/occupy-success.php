<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/*
Checkin Page: Check-In Success
*/
//get_header();

$owner = $ticket->order->billing_first_name . ' ' . $ticket->order->billing_last_name . ' (' . $ticket->order->billing_email . ')';
$index = '[' . $ticket->owns['occupied'] . ' / ' . array_sum( array_values( $ticket->owns ) ) . ']';
$msg = __('Check-In SUCCESSFUL','opentickets-community-edition');
?><html><head><title><?php echo $msg.' - '.get_bloginfo('name') ?></title>
<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport" />
<meta name="viewport" content="width=device-width" />
<link href="<?php echo esc_attr($stylesheet) ?>" id="checkin-styles" rel="stylesheet" type="text/css" media="all" />
</head><body>
<div id="content" class="row-fluid clearfix">
	<div class="span12">
		<div id="page-entry">
			<div class="fluid-row">
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

					<div class="checked-in event-checkin occupy-success">
						<h1 class="page-title"><?php echo $msg ?></h1>
						<ul class="ticket-info">
							<li class="owner"><strong><?php _e('Owner:','opentickets-community-edition') ?></strong> <?php echo $owner ?></li>
							<li class="event"><strong><?php _e('Event:','opentickets-community-edition') ?></strong> <?php echo $ticket->event->post_title ?></li>
							<li class="start-date"><strong><?php _e( 'Starts:', 'opentickets-community-edition' ) ?></strong> <?php
								echo date_i18n( get_option( 'date_format', 'F jS, Y' ) . ' ' . get_option( 'time_format', 'g:ia' ), QSOT_Utils::local_timestamp( $ticket->event->meta->start ) )
							?></li>
							<li class="checked"><strong><?php _e('Checked-In:','opentickets-community-edition') ?></strong> <?php echo $index ?></li>
						</ul>
					</div>

				</article>
			</div>	
		</div>
	</div>
</div>
</body>
<?php
//get_footer();
