<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
echo ( $multiple && isset( $ticket->qr_codes[ $index ] ) ) ? $ticket->qr_codes[ $index ] : $ticket->qr_code;
