add_action('enqueue_block_editor_assets', 'enqueue_my_acf_validation');

function enqueue_my_acf_validation() {
    wp_enqueue_script(
        'my-acf-validation',
        get_template_directory_uri() . '/js/validate-acf.js', // Adjust path
        array('wp-data', 'wp-editor', 'acf-input', 'jquery'),
        '1.0',
        true
    );
}
