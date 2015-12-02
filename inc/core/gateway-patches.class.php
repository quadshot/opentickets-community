<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
return;

class qsot_gateway_patches {
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_gateway_patches::pre_init();
}
