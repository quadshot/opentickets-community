<?php

if (QSOT::is_wc_latest())
	_deprecated_file(__FILE__, 'OTv1.5', dirname(dirname(dirname(dirname(__FILE__)))).'/meta-boxes/views/html-order-item.php', __('OpenTickets WC template override has moved location.','opentickets-community-edition'));

include dirname(dirname(dirname(dirname(__FILE__)))).'/meta-boxes/views/html-order-item.php';
