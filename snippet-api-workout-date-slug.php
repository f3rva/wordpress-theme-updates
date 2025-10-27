function register_custom_f3_data_workout_slug_date_endpoint() {
    register_rest_route( 'f3-data/v1', '/workout-slug-date/(?P<slug>[a-zA-Z0-9-]+)/(?P<date>\d{4}-\d{2}-\d{2})', array(
        'methods' => 'GET',
        'callback' => 'get_custom_post_data_by_slug_and_acf', // New callback name
        'permission_callback' => '__return_true' 
    ) );
}
add_action( 'rest_api_init', 'register_custom_f3_data_workout_slug_date_endpoint' );

function get_custom_post_data_by_slug_and_acf( $request ) {
    $slug = sanitize_text_field( $request['slug'] );
    $workout_date = sanitize_text_field( $request['date'] ); // The workout_date_new value
    
    // Define the ACF Field Key you are searching against
    $acf_key_for_search = 'workout_date_new'; 

    // Use WP_Query with both slug and Meta Query for uniqueness
    $args = array(
        'name'           => $slug, // Filter by post slug
        'post_type'      => 'any', // Search across all post types
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => $acf_key_for_search,
                'value'   => $workout_date,
                'compare' => '=',
                'type'    => 'DATE' // Important for date fields
            ),
        ),
    );
    
    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return new WP_Error( 'post_not_found', 'No published post found matching the slug and date.', array( 'status' => 404 ) );
    }

    // Get the single resulting post object
    $post = $query->posts[0]; 

    // --- Data Retrieval (Same as original custom function) ---
    
    // 1. Get Author Data
    $author_id = $post->post_author;
    $author_data = get_userdata( $author_id );

    // 2. Construct the Custom Response Array
    $data = array(
        'author_name'     => $author_data->display_name,
        'title'           => $post->post_title,
        'slug'            => $post->post_name,
        'html_content'    => apply_filters( 'the_content', $post->post_content ), 
        // Get ACF Fields
        'workout_date_new' => get_field( 'workout_date_new', $post->ID ),
        'big_data_id'     => get_field( 'big_data_id', $post->ID ),
    );

    return new WP_REST_Response( $data, 200 );
}