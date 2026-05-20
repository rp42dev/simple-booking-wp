<?php
require_once 'wp-load.php'; // This might not work if I don't know where wp-load is.
// Actually, I'll just check if it's defined in the current context.

if ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) ) {
    echo "SIMPLE_BOOKING_FORCE_PRO is defined as: ";
    var_dump( SIMPLE_BOOKING_FORCE_PRO );
} else {
    echo "SIMPLE_BOOKING_FORCE_PRO is NOT defined.";
}
