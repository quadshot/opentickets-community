<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
echo QSOT_Templates::maybe_include_template( 'qsot-calendar/views/agenda-day.php', $args );
