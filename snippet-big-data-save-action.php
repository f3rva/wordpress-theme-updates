function update_bigdata($post_id) {
	error_log('START: update_bigdata');
	
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
    
	error_log('DEBUG: permalink: ' . get_permalink($post));
	error_log('DEBUG: post_name: ' . $post->post_name);
	error_log('DEBUG: post_title: ' . $post->post_title);
	error_log('DEBUG: workout_date_new: ' . get_field('workout_date_new', $post_id));
	error_log('DEBUG: qic: ' . get_field('qic', $post_id));
	error_log('DEBUG: the_pax: ' . get_field('the_pax', $post_id));
	error_log('DEBUG: big_data_id: ' . get_field('big_data_id', $post_id));
	
	// if big_data_id is null, add the workout
	$bigDataId = get_field('big_data_id', $post_id);
	if (empty($bigDataId)) {
		// add the workout
		$api_url = 'http://bigdata.brianbischoff.com/api/v1/addWorkout.php';
		$request_data = array(
		    'method' => 'POST',
		    'headers' => array(
		        'Content-Type' => 'application/json'
		    ),
			'body' => wp_json_encode(array(
		        'post' => array(
					'title' => $post->post_title,
					'url' => get_permalink($post)
				)
		    ))
		);
		$response = wp_remote_request($api_url, $request_data);
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		error_log('response code: ' . $response_code);
		error_log('response body: ' . $response_body);
		$bigDataId = json_decode($response_body, true)['id'];
		error_log('after add, id: ' . $bigDataId);
		
		// temporarily remove the save_post hook to avoid infinite loop
		remove_action('save_post', 'update_bigdata');
		update_field('big_data_id', $bigDataId, $post_id);
		add_action('save_post', 'update_bigdata');
	}
	else {
		// otherwise refresh the workout
		$api_url = 'http://bigdata.brianbischoff.com/api/v1/refreshWorkout.php';
		$request_data = array(
		    'method' => 'PUT',
		    'headers' => array(
		        'Content-Type' => 'application/json'
		    ),
			'body' => wp_json_encode(array(
		        'workoutId' => intval($bigDataId)
		    ))
		);
		$response = wp_remote_request($api_url, $request_data);
		
		if ( is_wp_error( $response ) ) {
			// Error handling
			$error_message = $response->get_error_message();
			//echo "Error: $error_message";
			error_log('Error: $error_message');
		} else {
			// Successful request
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			error_log('response code: ' . $response_code);
			error_log('response body: ' . $response_body);
		}
	}
	
    // Make the API call
    //$api_url = 'https://xckku9tdn8.execute-api.us-east-1.amazonaws.com/hello';
	//$request_data = array(
    //    'method' => 'POST',
    //    'headers' => array(
    //        'Content-Type' => 'application/json'
    //    ),
	//	'body' => wp_json_encode(array(
    //        'post' => array (
	//			'first_name' => 'Word',
    //        	'last_name' => 'Press: ' . $post->post_name // Replace with the appropriate post data you want to send
	//		)
    //    ))
    //);
	//$response = wp_remote_post($api_url, $request_data);
    
    // Check if the API call was successful
    //if (is_wp_error($response)) {
        // Handle the error
    //    return;
    //}
    
    // Get the API response body
    //$api_data = wp_remote_retrieve_body($response);
    
    
	error_log('END: update_bigdata');
}
add_action('save_post', 'update_bigdata');
