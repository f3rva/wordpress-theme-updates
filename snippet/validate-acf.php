add_action('enqueue_block_editor_assets', 'enqueue_my_acf_validation');

function enqueue_my_acf_validation() {
    $script_path = get_stylesheet_directory() . '/js/validate-acf.js';
    $version     = file_exists($script_path) ? filemtime($script_path) : '1.1';

    wp_enqueue_script(
        'my-acf-validation',
        get_stylesheet_directory_uri() . '/js/validate-acf.js',
        array('wp-data', 'wp-editor', 'acf-input', 'jquery'),
        $version,
        true
    );
}
