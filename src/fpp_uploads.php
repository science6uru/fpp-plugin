<?php 
if (!function_exists('wp_read_image_metadata')) {
    require_once ABSPATH . '/wp-admin/includes/image.php';
}
if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

function fpp_get_user_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP from shared internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP passed through a proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        // Default to REMOTE_ADDR
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if ($ip) {
        return $ip;
    }
    return "";
}

/**
 * Generates a unique filename for photos uploaded to FPP stations.
 * @return String
 */
function fpp_generate_unique_filename(String$user_ip, int$photo_id, String$ext) {
    $hashstr = substr(hash("md5", "fpp_photo.{$user_ip}.{$photo_id}"), 0, 10);
    return "{$hashstr}-{$photo_id}{$ext}";
}

/** Handle the user uploads */
function fpp_process_upload(WP_REST_Request $request) {
    global $wpdb, $fpp_stations, $fpp_photos;
    $secret_key = get_option('fpp_recaptcha_secret_key');
    $uploaded_file = $_FILES['user_photo'];

    // better error handling
    if ( ! isset( $uploaded_file ) || ! is_array( $uploaded_file ) ) {
        return new WP_REST_Response( array( 'error' => 'No file uploaded.' ), 400 );
    }

    if ( $uploaded_file['error'] !== UPLOAD_ERR_OK ) {
        $code = $uploaded_file['error'];
        $server_upload = ini_get('upload_max_filesize');
        $server_post   = ini_get('post_max_size');

        if ( $code === UPLOAD_ERR_INI_SIZE) {
            $msg = sprintf(
                'Uploaded file is too large. PHP limit: %s',
                esc_html($server_upload)
            );
            return new WP_REST_Response( array( 'error' => $msg ), 400 );
        }
        
        // Map for any other errors that might happen. 
        $errors = array(
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded... (How did you get here???).',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        );
        $msg = isset( $errors[$code] ) ? $errors[$code] : ( 'Upload error code: ' . intval( $code ) );
        return new WP_REST_Response( array( 'error' => $msg ), 400 );
    }

    // Config maximum
    $configured_mb = floatval( get_option( 'fpp_max_upload_size_mb', '0.0' ) );
    if ( $configured_mb > 0 ) {
        $configured_bytes = (int) round( $configured_mb * 1024 * 1024 );
        if ( isset( $uploaded_file['size'] ) && $uploaded_file['size'] > $configured_bytes ) {
            return new WP_REST_Response( array(
                'error' => sprintf(
                    'File exceeds maximum size of %s MB.',
                    number_format( $configured_mb, 2, '.', '' )
                )
            ), 400 );
        }
    }

    $temp_file_path = $uploaded_file['tmp_name'];
    $metadata = wp_read_image_metadata( $temp_file_path );
    // Only verify if secret key is configured
    if (!empty($secret_key)) {
        $recaptcha_response = $request->get_param('g-recaptcha-response');
        if (empty($recaptcha_response)) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA token is missing.'), 400);
        }

        $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ),
        ));

        if (is_wp_error($verify_response)) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA verification failed: ' . $verify_response->get_error_message()), 400);
        }

        $response_body = json_decode(wp_remote_retrieve_body($verify_response));

        $threshold = floatval( get_option( 'fpp_recaptcha_threshold', '0.0' ) );

        if ( ! $response_body->success || ! isset( $response_body->score ) || $response_body->score < $threshold ) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA verification failed (low score). Please try again.'), 400);
        }

        // if ($response_body->action !== 'upload_photo') { ... }
    }

    // Get station ID from route parameter
    $station_id = $request->get_param('id');
    $station_sql = $wpdb->prepare("SELECT * from $fpp_stations where id = %d", $station_id);
    $station = $wpdb->get_row($station_sql);

    if ($wpdb->last_error or $station == NULL) {
        error_log("Station lookup error: " . $wpdb->last_error);
        return new WP_REST_Response(array(
            'error' => "Invalid station",
        ), 404);
    }
    $user_ip = fpp_get_user_ip();

    $wpdb->query('START TRANSACTION');
    try {
        $created = $wpdb->insert(
            $fpp_photos,
            array(
                'ip' => $user_ip,
                'station_id' => $station->id,
                'file_name' => uniqid(),
            ),
            array(
                '%s', // Format for ip
                '%d', // Format for station_id
                '%s', // Format for file_name
            )
        );

        if ($created == false) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(array(
                'error' => "Error processing request",
            ), 500);
        }
        $photo_id = $wpdb->insert_id;
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
            'unique_filename_callback' => function( $dir, $name, $ext ) use ($user_ip, $photo_id){
                global $wpdb, $fpp_photos;
                // NOTE: $ext contains the ".", ex: $ext = ".jpg"
                $filename = fpp_generate_unique_filename($user_ip, $photo_id, $ext);
                $updated = $wpdb->update($fpp_photos,
                                array('file_name' => $filename),
                                array('id' => $photo_id));
                if ($updated == false) {
                    throw new Exception("Failed to update filename");
                }
                $paths_created = wp_mkdir_p($dir);
                if (! $paths_created) {
                    throw new Exception("Target directory does not exist.");
                }
                return $filename;
            }
        );

        // Apply filter to override upload path
        add_filter('upload_dir', function($dirs) use ($station_id) {
            $custom_base_dir = get_option('fpp_images_base_dir');
            $subdir_prefix = '';
            if (!empty($custom_base_dir)) {
                $dirs['basedir'] = rtrim($custom_base_dir, '/');
                $base_relative = str_replace( ABSPATH, '', $dirs['basedir'] );
                $dirs['baseurl'] = trailingslashit( site_url() ) . ltrim( $base_relative, '/' );
                wp_mkdir_p($dirs['basedir']);
            } else {
                $subdir_prefix = '/fpp-plugin';
            }
            $dirs['subdir'] = $subdir_prefix . '/station-' . $station_id;
            $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url']  = $dirs['baseurl'] . $dirs['subdir'];
            return $dirs;
        });

        $upload_result = wp_handle_upload($uploaded_file, $overrides);
        remove_all_filters('upload_dir');

        if ($upload_result && !isset($upload_result['error'])) {
            // Success: File is moved to uploads dir
            $commit_result = $wpdb->query( 'COMMIT' );
            if ( false === $commit_result ) {
                // If COMMIT fails, explicitly rollback
                $wpdb->query( 'ROLLBACK' );
                error_log( 'wpdb: COMMIT failed, transaction rolled back.' );
                return new WP_REST_Response(array(
                    'error' => "Internal Server Error: Could not persist data",
                ), 500);
            }
            return new WP_REST_Response(array(
                'message' => 'File uploaded successfully!',
                'path' => $upload_result['file'],
                'url' => $upload_result['url'],
                'type' => $upload_result['type'],
                'meta' => $metadata,
            ), 200);
        } else {
            // Failure for whatever reason
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(array(
                'error' => $upload_result['error'],
            ), 400);
        }
    } catch (Exception $e) {
        // If any error occurs, rollback the transaction
        $wpdb->query('ROLLBACK');
        error_log('Transaction failed: ' . $e->getMessage());
        return new WP_REST_Response(array(
            'error' => "Internal Server Error {$e->getMessage()}",
        ), 500);
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

/** Register the upload route */
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