function update_bigdata($post_id) {
	error_log('START: update_bigdata');
	do_action('qm/start', 'update_bigdata');
	
	//$api_host = 'http://localhost:9000';
	$api_host = 'https://bigdata.brianbischoff.com';
	$public_prefix = 'https://f3rva.org';

    // Check if this is an autosave or revision or it's not a post
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || get_post_type($post_id) !== 'post') {
        return;
    }
    
	// Get the post object
    $post = get_post($post_id);
	
	// if we haven't published yet, ignore
	if ($post->post_status !== 'publish') {
		return;
	}
    
	// get the body so we can pass it to the API
	$raw_post_body = $post->post_content;
	$post_body = apply_filters('the_content', $raw_post_body);
	$post_title = $post->post_title;
	$post_slug = $post->post_name;
	$post_author_id = $post->post_author;
	$post_author_name = get_the_author_meta('display_name', $post_author_id);

	// get the tags
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
	
	// get our ACF data
	$post_workout_date = get_field('workout_date_new', $post_id);
	$post_qic = get_field('qic', $post_id);
	$post_pax = get_field('the_pax', $post_id);
	$big_data_id = get_field('big_data_id', $post_id);
	
	// construct the public URL
	$dt = DateTime::createFromFormat('Ymd', $post_workout_date);
	$post_url = $public_prefix . '/' . $dt->format('Y/m/d') . '/' . $post_slug . '/';
	
	error_log('DEBUG: post_url: ' . $post_url);
	error_log('DEBUG: post_name: ' . $post_slug);
	error_log('DEBUG: post_title: ' . $post_title);
	error_log('DEBUG: post_author_name: ' . $post_author_name);
	error_log('DEBUG: post_tags: ' . print_r(array_column(array: $tag_data, column_key: 'name'), true));
	error_log('DEBUG: workout_date_new: ' . $post_workout_date);
	error_log('DEBUG: qic: ' . $post_qic);
	error_log('DEBUG: the_pax: ' . $post_pax);
	error_log('DEBUG: big_data_id: ' . $big_data_id);
	
	// if big_data_id is null, add the workout
	if (empty($big_data_id)) {
		// add the workout
		$api_url = $api_host . '/api/v2/addWorkout.php';
		$request_data = array(
		    'method' => 'POST',
		    'headers' => array(
		        'Content-Type' => 'application/json'
		    ),
			'body' => wp_json_encode(array(
				'title' => $post_title,
				'url' => $post_url,
				'author' => $post_author_name,
				'slug' => $post_slug,
				'body' => $post_body,
				'workoutDate' => $post_workout_date,
				'qic' => $post_qic,
				'pax' => $post_pax,
				'aos' => $tag_data
		    ))
		);

		error_log('DEBUG: add workout body: ' . print_r($request_data['body'], true));

		$response = wp_remote_request($api_url, $request_data);
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		error_log('response code: ' . $response_code);
		error_log('response body: ' . $response_body);
		$big_data_id = json_decode($response_body, true)['id'];
		error_log('after add, id: ' . $big_data_id);
		
		update_field('big_data_id', $big_data_id, $post_id);
	}
	else {
		// otherwise refresh the workout
		$api_url = $api_host . '/api/v2/refreshWorkout.php';
		$request_data = array(
		    'method' => 'PUT',
		    'headers' => array(
		        'Content-Type' => 'application/json'
		    ),
			'body' => wp_json_encode(array(
		        'workoutId' => intval($big_data_id),
				'title' => $post_title,
				'url' => $post_url,
				'author' => $post_author_name,
				'slug' => $post_slug,
				'body' => $post_body,
				'workoutDate' => $post_workout_date,
				'qic' => $post_qic,
				'pax' => $post_pax,
				'aos' => $tag_data
		    ))
		);
		
		error_log('DEBUG: refresh workout body: ' . print_r($request_data['body'], true));

		$response = wp_remote_request($api_url, $request_data);
		
		if ( is_wp_error( $response ) ) {
			// Error handling
			$error_message = $response->get_error_message();
			error_log('Error: ' . $error_message);
		} else {
			// Successful request
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			error_log('response code: ' . $response_code);
			error_log('response body: ' . $response_body);
		}
	}
	    
	do_action('qm/stop', 'update_bigdata');
	error_log('END: update_bigdata');
}
add_action('save_post', 'update_bigdata');
