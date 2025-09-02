<?php 

if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

/** Handle the user uploads */
function fpp_process_upload(WP_REST_Request $request)
{
/*
   // $request_body = $request->get_body_params();
   // if(update_post_meta( $request_body['post_id'], 'post_data', $request_body ))
   // {
   //    $response = new WP_REST_Response(array('message'=>'Successful'));
   //    $response->set_status(200);
   //    return $response;
   // }
   // else{
   //      return new WP_Error('invalid_request', 'Something went wrong', array('status'=>403));
   // }


    //return new WP_REST_Response($request->get_header('Referer'));
*/

    $uploaded_file = $_FILES['user_photo'];
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        return new WP_REST_Response('Upload error: ' . $uploaded_file['error']);
    }

    $overrides = array(
        'test_form' => false,
        'test_type' => true,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'bmp' => 'image/bmp',
            'tif|tiff' => 'image/tiff',
            'ico' => 'image/x-icon',
            'heic' => 'image/heic',
        ),
    );

    $upload_result = wp_handle_upload($uploaded_file, $overrides);

    if ($upload_result && !isset($upload_result['error'])) {
        // Success: File is moved to uploads dir
        return new WP_REST_Response(array(
            'message' => 'File uploaded successfully!',
            'path' => $upload_result['file'],
            'url' => $upload_result['url'],
            'type' => $upload_result['type'],
        ), 200);
    } else {
        // Failure for whatever reason
        return new WP_REST_Response(array(
            'error' => $upload_result['error'],
        ), 400);
    }
    
/*

    // You can access parameters via direct array access on the object:
    $param = $request['user_photo'];

  // Or via the helper method:
  $param = $request->get_param( 'some_param' );

  // You can get the combined, merged set of parameters:
  $parameters = $request->get_params();

  // The individual sets of parameters are also available, if needed:
  $parameters = $request->get_url_params();
  $parameters = $request->get_query_params();
  $parameters = $request->get_body_params();
  $parameters = $request->get_json_params();
  $parameters = $request->get_default_params();

  // Uploads aren't merged in, but can be accessed separately:
  $parameters = $request->get_file_params();
  # file is temporarially stored on server
  # need to move to a new location on save
  # new file name needs to be generated and unique
  #   look into uuid for filenames and maybe organize them by station directories
  # see. https://www.php.net/manual/en/features.file-upload.post-method.php
*/
}

/** Function to register the upload route */
function fpp_register_routes(){
   register_rest_route('fpp/v1', '/photo_upload/(?P<id>\d+)', array(
        'methods'=>'POST',
        'callback'=>'fpp_process_upload',
        'permission_callback'=> '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                return is_numeric( $param );
                }
            ),
        ),
   )); 
}

add_action('rest_api_init', 'fpp_register_routes');
?>