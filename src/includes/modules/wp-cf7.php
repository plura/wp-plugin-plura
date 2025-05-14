<?php

/* FIX: CF7 Breaking Spaces */
add_filter( 'wpcf7_autop_or_not', '__return_false' );