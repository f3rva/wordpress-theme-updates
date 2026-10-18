// 1. Redirect frontend traffic for everyone EXCEPT admins
add_action( 'template_redirect', function() {
    // 'manage_options' is a capability only Administrators have
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_redirect( 'https://f3rva.org/backblast/new', 301 );
        exit;
    }
} );

// 2. Redirect backend (wp-admin) traffic for non-admins who try to log in
add_action( 'admin_init', function() {
    // Allow AJAX requests to pass through (used by plugins/themes)
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_redirect( 'https://f3rva.org/backblast/new', 301 );
        exit;
    }
} );
