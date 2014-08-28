<?php
/*
Checkin Page: Previously Checked In
*/
//get_header();

$owner = $order->billing_first_name.' '.$order->billing_last_name.' ('.$order->billing_email.')';
$msg = 'Ticket has PREVIOUSLY checked in!';
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

					<div class="checked-in event-checkin previously-checked-in">
						<h1 class="page-title"><?php echo $msg ?></h1>
						<ul class="ticket-info">
							<li class="owner"><strong>Owner:</strong> <?php echo $owner ?></li>
							<li class="event"><strong>Event:</strong> <?php echo $event->post_title ?></li>
							<li class="zone"><strong>Seat:</strong> <?php echo $zone->fullname ?></li>
						</ul>
					</div>

				</article>
			</div>	
		</div>
	</div>
</div>
<?php
//get_footer();
