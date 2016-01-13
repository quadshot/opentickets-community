<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
echo QSOT_Templates::maybe_include_template( 'qsot-calendar/views/basic-day.php', $args );
