// 1. Expose big_data_id to Gutenberg REST API so the editor UI receives the ID on save
add_action('init', 'register_f3_big_data_meta');
function register_f3_big_data_meta() {
    register_post_meta('post', 'big_data_id', [
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'integer',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);
}

// 2. Prevent ACF from erasing an existing big_data_id during un-refreshed editor saves (type '0' to explicitly clear)
add_filter('acf/update_value/name=big_data_id', 'preserve_f3_big_data_id', 10, 4);
function preserve_f3_big_data_id($value, $post_id, $field, $original) {
    if ($value === 0 || $value === '0') {
        return '';
    }
    if (empty($value) && is_numeric($post_id)) {
        $existing = get_post_meta($post_id, 'big_data_id', true);
        if (!empty($existing)) {
            return $existing;
        }
    }
    return $value;
}

function update_bigdata($post_id) {
    // 1. Ensure $post_id is a valid post ID and post type is 'post'
    if (!is_numeric($post_id) || get_post_type($post_id) !== 'post') {
        return;
    }

    // 2. Ignore autosaves or revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // 3. Ignore non-published posts
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return;
    }

    error_log('START: update_bigdata for post ' . $post_id);
    do_action('qm/start', 'update_bigdata');

    //define("F3_API_HOST", "http://localhost:8000");
    $api_host = defined('F3_API_HOST') ? F3_API_HOST : 'https://api.f3rva.org';
    $public_prefix = 'https://f3rva.org';

    // 4. Read ACF field data (guaranteed to be committed to DB by acf/save_post)
    $big_data_id = get_field('big_data_id', $post_id);
    if (empty($big_data_id)) {
        $big_data_id = get_post_meta($post_id, 'big_data_id', true);
    }

    // Get the post body
    $raw_post_body = $post->post_content;
    $post_body = apply_filters('the_content', $raw_post_body);
    $post_title = $post->post_title;
    $post_slug = $post->post_name;
    $post_author_id = $post->post_author;
    $post_author_name = get_the_author_meta('display_name', $post_author_id);

    // Get the tags / AOs
    $post_tags = get_the_terms($post_id, 'post_tag');
    $tag_data = [];

    if ($post_tags && !is_wp_error($post_tags)) {
        foreach ($post_tags as $tag) {
            $tag_data[] = [
                'name' => $tag->name,
                'slug' => $tag->slug
            ];
        }
    }

    // Get ACF data
    $post_workout_date = get_field('workout_date_new', $post_id);
    $post_qic = get_field('qic', $post_id);
    $post_pax = get_field('the_pax', $post_id);

    // Construct the public URL
    $dt = DateTime::createFromFormat('Ymd', $post_workout_date);
    $post_url = $public_prefix . '/' . ($dt ? $dt->format('Y/m/d') : date('Y/m/d')) . '/' . $post_slug . '/';

    error_log('DEBUG: post_url: ' . $post_url);
    error_log('DEBUG: post_name: ' . $post_slug);
    error_log('DEBUG: post_title: ' . $post_title);
    error_log('DEBUG: post_author_name: ' . $post_author_name);
    error_log('DEBUG: post_tags: ' . print_r(array_column(array: $tag_data, column_key: 'name'), true));
    error_log('DEBUG: workout_date_new: ' . $post_workout_date);
    error_log('DEBUG: qic: ' . $post_qic);
    error_log('DEBUG: the_pax: ' . $post_pax);
    error_log('DEBUG: big_data_id: ' . $big_data_id);

    $payload = [
        'title'       => $post_title,
        'url'         => $post_url,
        'author'      => $post_author_name,
        'slug'        => $post_slug,
        'body'        => $post_body,
        'workoutDate' => $post_workout_date,
        'qic'         => $post_qic,
        'pax'         => $post_pax,
        'aos'         => $tag_data
    ];

    // If big_data_id is empty, add the workout
    if (empty($big_data_id)) {
        $api_url = $api_host . '/v2/workouts';
        $request_data = [
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ],
            'body'    => wp_json_encode($payload)
        ];

        error_log('DEBUG: add workout body: ' . print_r($request_data['body'], true));

        $response = wp_remote_request($api_url, $request_data);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Error adding workout: ' . $error_message);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log('Workout creation response code: ' . $response_code);
            error_log('Workout creation response body: ' . $response_body);

            if ($response_code === 201 || $response_code === 200) {
                $data = json_decode($response_body, true);
                if (!empty($data['id'])) {
                    $big_data_id = $data['id'];
                    error_log('after add, id: ' . $big_data_id);
                    update_field('big_data_id', $big_data_id, $post_id);
                    update_post_meta($post_id, 'big_data_id', $big_data_id);
                }
            } elseif ($response_code === 409) {
                // Self-healing: if workout already exists in API, retrieve ID and link it
                error_log('Workout already exists in API (409), attempting self-healing lookup.');
                if ($dt) {
                    $lookup_url = $api_host . '/v2/workouts/by-date-slug?year=' . $dt->format('Y') . '&month=' . $dt->format('n') . '&day=' . $dt->format('j') . '&slug=' . $post_slug;
                    $lookup_res = wp_remote_get($lookup_url);
                    if (!is_wp_error($lookup_res) && wp_remote_retrieve_response_code($lookup_res) === 200) {
                        $existing = json_decode(wp_remote_retrieve_body($lookup_res), true);
                        if (!empty($existing['workoutId'])) {
                            $big_data_id = $existing['workoutId'];
                            update_field('big_data_id', $big_data_id, $post_id);
                            update_post_meta($post_id, 'big_data_id', $big_data_id);
                            error_log('Self-healing succeeded: recovered big_data_id ' . $big_data_id);
                        }
                    }
                }
            } else {
                error_log('Failed to create workout in f3rva-api, code: ' . $response_code . ', error: ' . $response_body);
            }
        }
    } else {
        // Otherwise refresh the workout
        $api_url = $api_host . '/v2/workouts/' . intval($big_data_id);
        $request_data = [
            'method'  => 'PUT',
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ],
            'body'    => wp_json_encode($payload)
        ];

        error_log('DEBUG: refresh workout body: ' . print_r($request_data['body'], true));

        $response = wp_remote_request($api_url, $request_data);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Error refreshing workout: ' . $error_message);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log('Workout update response code: ' . $response_code);
            error_log('Workout update response body: ' . $response_body);

            if ($response_code !== 200) {
                error_log('Failed to refresh workout in f3rva-api, code: ' . $response_code . ', error: ' . $response_body);
            }
        }
    }

    do_action('qm/stop', 'update_bigdata');
    error_log('END: update_bigdata');
}
// Hook into acf/save_post at priority 20 so our update runs AFTER ACF saves the form in full editor
add_action('acf/save_post', 'update_bigdata', 20, 1);

// Hook into save_post for Quick Edit (AJAX inline-save) and Bulk Edit where ACF hooks do not fire
add_action('save_post', 'update_bigdata_quick_edit', 20, 1);
function update_bigdata_quick_edit($post_id) {
    $is_quick_edit = (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'inline-save');
    $is_bulk_edit  = isset($_REQUEST['bulk_edit']);

    if ($is_quick_edit || $is_bulk_edit) {
        update_bigdata($post_id);
    }
}
