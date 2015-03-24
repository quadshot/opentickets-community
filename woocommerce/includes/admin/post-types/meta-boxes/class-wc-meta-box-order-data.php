<?php

if (QSOT::is_wc_latest())
	_deprecated_file(__FILE__, 'OTv1.5', dirname(dirname(dirname(__FILE__))).'/meta-boxes/class-wc-meta-box-order-data.php', 'OpenTickets WC override file location has moved.');

require_once dirname(dirname(dirname(__FILE__))).'/meta-boxes/class-wc-meta-box-order-data.php';
