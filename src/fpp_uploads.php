<?php 
if (!function_exists('wp_read_image_metadata')) {
    require_once ABSPATH . '/wp-admin/includes/image.php';
}
if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

function fpp_write_log($log) {
    if (true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }
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

function fpp_get_slug_page_link($slug){
  $post = get_page_by_path($slug, OBJECT, 'page');
  return get_permalink($post->ID);
}

function fpp_get_rotation($filename) {
    if (function_exists('exif_read_data')) {
        $exif = exif_read_data($filename);
        if ($exif && isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];
            if ($orientation != 1) {
                // Create an image resource from the filename
                $img = imagecreatefromjpeg($filename); // For JPEGs. Use imagecreatefrompng/gif for other formats.
                $deg = 0;
                switch ($orientation) {
                    case 3:
                        $deg = 180;
                        break;
                    case 6:
                        $deg = 270;
                        break;
                    case 8:
                        $deg = 90;
                        break;
                }
                return $deg;
            }
        }
    }
    return 0;
}


function fpp_create_image_resized($fileName, $maxWidth, $maxHeight, $destination) {
    // Get original image dimensions and type
    list($sourceImageWidth, $sourceImageHeight, $uploadImageType) = getimagesize($fileName);
    if($sourceImageWidth == 0 || $sourceImageHeight == 0) {
        return false;
    }


    // Create image resource from the source file based on file type
    switch ($uploadImageType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($fileName);
            $deg = fpp_get_rotation($fileName);
            if ($deg) {
                // Rotate the image resource
                $sourceImage = imagerotate($sourceImage, $deg, 0);
                imagejpeg($sourceImage, $fileName, 95);
                $sourceImage = imagecreatefromjpeg($fileName);
            }

            $gdWidth = imagesx($sourceImage);
            $gdHeight = imagesy($sourceImage);
            $ratio = min($maxWidth / $gdWidth, $maxHeight / $gdHeight);
            $newWidth = (int)($gdWidth * $ratio);
            $newHeight = (int)($gdHeight * $ratio);
            break;
        default:
            return false; // Unsupported image type
    }

    // Create a new true color image (destination layer) with the new dimensions
    $targetLayer = imagecreatetruecolor($newWidth, $newHeight);

    // Resize the image using imagecopyresampled for better quality
    imagecopyresampled($targetLayer, $sourceImage, 0, 0, 0, 0,
        $newWidth, $newHeight, $sourceImageWidth, $sourceImageHeight);

    // Save the resized image to the destination file
    switch ($uploadImageType) {
        case IMAGETYPE_JPEG:
            imagejpeg($targetLayer, $destination, 85); // Quality 0-100
            break;
    }
    return true;
}

function fpp_generate_thumbnail($photo) {
    global $wpdb, $fpp_photos;
    $filename = $photo->file_name;
    $path = fpp_photos_dir($photo->station_id);
    $filepath = $path . "/" . $filename;

    if (empty($filename)) {
        return;
    }
    if (!file_exists($filepath)) {
        return;
    }
    // Get only the filename without the extension
    $basename = pathinfo($filepath, PATHINFO_FILENAME);
    $thumbname = $basename . "-thumb.jpg";
    if (fpp_create_image_resized($filepath, 300, 200, $path . "/" . $thumbname)) {
        $updated = $wpdb->update($fpp_photos,
                        array('thumb_200' => $thumbname),
                        array('id' => $photo->id));
    }
}
function fpp_generate_display_image($photo) {
    global $wpdb, $fpp_photos;
    $filename = $photo->file_name;
    $path = fpp_photos_dir($photo->station_id);
    $filepath = $path . "/" . $filename;
    // Get only the filename without the extension

    if (empty($filename)) {
        return;
    }
    if (!file_exists($filepath)) {
        return;
    }
    $basename = pathinfo($filepath, PATHINFO_FILENAME);
    $thumbname = $basename . "-image_2000.jpg";
    if (fpp_create_image_resized($filepath, 2000, 2000, $path . "/" . $thumbname)) {
        $updated = $wpdb->update($fpp_photos,
                        array('image_2000' => $thumbname),
                        array('id' => $photo->id));
    }
}
/**
 * Generates a unique filename for photos uploaded to FPP stations.
 * @return String
 */
function fpp_generate_unique_filename(String$user_ip, int$photo_id, String$ext) {
    $hashstr = substr(hash("md5", "fpp_photo.{$user_ip}.{$photo_id}"), 0, 10);
    return "{$hashstr}-{$photo_id}{$ext}";
}

function fpp_verify_recaptcha_v3($token, $remote_ip) {
    $secret_key = get_option('fpp_recaptcha_secret_key');
    if (empty($secret_key)) {
        return array('success' => false, 'error' => 'reCAPTCHA v3 not configured');
    }

    $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
        'timeout' => 10,
        'body' => array(
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $remote_ip,
        ),
    ));

    if (is_wp_error($verify_response)) {
        return array('success' => false, 'error' => 'reCAPTCHA verification failed: ' . $verify_response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($verify_response);
    if ($response_code !== 200) {
        return array('success' => false, 'error' => 'reCAPTCHA service returned error: ' . $response_code);
    }

    $response_body = wp_remote_retrieve_body($verify_response);
    $response_data = json_decode($response_body);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('success' => false, 'error' => 'Invalid response from reCAPTCHA service');
    }
    
    if (!$response_data || !isset($response_data->success)) {
        return array('success' => false, 'error' => 'Invalid response format from reCAPTCHA service');
    }
    
    if (!$response_data->success) {
        $error_codes = isset($response_data->{'error-codes'}) ? implode(', ', $response_data->{'error-codes'}) : 'Unknown error';
        return array('success' => false, 'error' => $error_codes);
    }
    
    return array('success' => true, 'score' => $response_data->score ?? 0, 'response' => $response_data);
}

function fpp_verify_recaptcha_v2($token, $remote_ip) {
    $secret_key = get_option('fpp_recaptcha_v2_secret_key');
    if (empty($secret_key)) {
        return array('success' => false, 'error' => 'reCAPTCHA v2 not configured');
    }

    $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
        'timeout' => 10,
        'body' => array(
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $remote_ip,
        ),
    ));

    if (is_wp_error($verify_response)) {
        return array('success' => false, 'error' => 'reCAPTCHA v2 verification failed: ' . $verify_response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($verify_response);
    if ($response_code !== 200) {
        return array('success' => false, 'error' => 'reCAPTCHA v2 service returned error: ' . $response_code);
    }

    $response_body = wp_remote_retrieve_body($verify_response);
    $response_data = json_decode($response_body);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('success' => false, 'error' => 'Invalid response from reCAPTCHA v2 service');
    }
    
    if (!$response_data || !isset($response_data->success)) {
        return array('success' => false, 'error' => 'Invalid response format from reCAPTCHA v2 service');
    }
    
    if (!$response_data->success) {
        $error_codes = isset($response_data->{'error-codes'}) ? implode(', ', $response_data->{'error-codes'}) : 'Unknown error';
        return array('success' => false, 'error' => $error_codes);
    }
    
    return array('success' => true, 'response' => $response_data);
}

/** Handle the user uploads */
function fpp_process_upload(WP_REST_Request $request) {
    global $wpdb, $fpp_stations, $fpp_photos;
    $station_slug =$request->get_param('station_slug'); 
    // Get station ID and validate it exists first
    $station_sql = $wpdb->prepare("SELECT * from $fpp_stations where slug = %s", $station_slug);
    $station = $wpdb->get_row($station_sql);
    $station_id = $station->id;

    if ($wpdb->last_error or $station == NULL) {
        error_log("Station lookup error: " . $wpdb->last_error);
        return new WP_REST_Response(array(
            'error' => "Invalid station",
        ), 404);
    }

    $target_dir = fpp_photos_dir($station_id);
    
    if (!is_dir($target_dir)) {
        return new WP_REST_Response(array(
            'error' => 'Upload directory does not exist.',
        ), 500);
    }

    if (!is_writable($target_dir)) {
        return new WP_REST_Response(array(
            'error' => 'Upload directory is not writable.',
        ), 500);
    }

    $v3_secret_key = get_option('fpp_recaptcha_secret_key');
    $v2_secret_key = get_option('fpp_recaptcha_v2_secret_key');
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
    $metadata = exif_read_data( $temp_file_path );
    // echo "<html><body>";
    // var_dump($metadata);
    // echo "</body></html>";
    // exit();
    
    $v3_token = $request->get_param('g-recaptcha-response');
    $v2_token = $request->get_param('g-recaptcha-response-v2');
    $remote_ip = $_SERVER['REMOTE_ADDR'];
    $validateV3 = !empty($v3_secret_key);
    $validateV2 = !empty($v2_secret_key);

    if ($validateV2 && $v2_token) {
        $validateV3 = false;
    }
    if ($validateV3 && empty($v2_token)) {
        $validateV2 = false;
    }

    $score = $request->get_param('g-recaptcha-score-v3');
    $captcha_mode = "none";
    // If v3 is configured, use it
    if ($validateV3) {
        if (empty($v3_token)) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA v3 token is missing.'), 400);
        }

        $v3_result = fpp_verify_recaptcha_v3($v3_token, $remote_ip);
        
        if (!$v3_result['success']) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA v3 verification failed: ' . ($v3_result['error'] ?? 'Unknown error')), 400);
        }

        $low_threshold = floatval(get_option('fpp_recaptcha_low_threshold', '0.3'));
        $high_threshold = floatval(get_option('fpp_recaptcha_high_threshold', '0.7'));
        $score = $v3_result['score'];
        $captcha_mode = "v3";

        // Score below low threshold
        if ($score < $low_threshold) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA verification failed (low score). Please try again.'), 400);
        }
        
        // Score between low and high thresholds require v2
        if ($score >= $low_threshold && $score < $high_threshold) {
            if (!$validateV2) {
                return new WP_REST_Response(array(
                    'error' => 'v2_required',
                    'message' => 'Additional security verification required.'
                ), 400);
            }
            
            // ver v2 token
            $v2_result = fpp_verify_recaptcha_v2($v2_token, $remote_ip);
            $validateV2 = false;
            if (!$v2_result['success']) {
                return new WP_REST_Response(array('error' => 'reCAPTCHA v2 verification failed. Please try again.'), 400);
            }
            $captcha_mode = "v2";

        }
        
        // score above high threshold accept without v2
    } 
    // If v3 is not configured but v2 is, use v2
    if ($validateV2) {
        if (empty($v2_token)) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA v2 token is missing.'), 400);
        }

        $v2_result = fpp_verify_recaptcha_v2($v2_token, $remote_ip);
        if (!$v2_result['success']) {
            return new WP_REST_Response(array('error' => 'reCAPTCHA v2 verification failed. Please try again.'), 400);
        }
        $captcha_mode = "v2";

    }
    // If neither isset , skip 

    $user_ip = fpp_get_user_ip();
    $wpdb->query('START TRANSACTION');
    $field_values = array(
                        'ip' => $user_ip,
                        'station_id' => $station->id,
                        'file_name' => uniqid(),
                        'captcha_score' => sprintf("%.2f", $score),
                        'captcha_mode' => $captcha_mode,
                        'metadata' => json_encode($metadata),
                    );
    $field_formats = array(
                        '%s', // Format for ip
                        '%d', // Format for station_id
                        '%s', // Format for file_name
                        '%s', // Format for score (decimal-string)
                        '%s', // Format for captcha_mode
                        '%s', // Format for metadata
                    );  
    try {
        if (isset($metadata['created_timestamp']) && $metadata['created_timestamp'] != "0" && filter_var($metadata['created_timestamp'], FILTER_VALIDATE_INT) !== false) {
            $field_values['taken'] = gmdate('Y-m-d H:i:s', intval($metadata['created_timestamp']));
            $field_formats[] = '%s';
        } else if (isset($metadata['DateTimeOriginal'])) {
            $field_values['taken'] = $metadata['DateTimeOriginal'];
            $field_formats[] = '%s';
        }
    } catch (Exception $e) {
        fpp_write_log($e->getMessage());
    }
    try {
        $created = $wpdb->insert(
            $fpp_photos,
            $field_values,
            $field_formats
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
                // Removed wp_mkdir_p call so it's more under control by admin
                if (!is_dir($dir)) {
                    throw new Exception("Target directory does not exist.");
                }
                return $filename;
            }
        );

        // Apply filter to override upload path
        add_filter('upload_dir', function($dirs) use ($station_id) {
            // Removed wp_mkdir_p call so it's more under control by admin
            $dirs['subdir'] = fpp_photo_subdir($station_id);
            $dirs['path'] = $dirs['basedir'] . "/" . $dirs['subdir'];
            $dirs['url']  = $dirs['baseurl'] . "/" . $dirs['subdir'];
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
            $photo = $wpdb->get_row("select * from $fpp_photos where id = $photo_id");
            fpp_generate_thumbnail($photo);
            if (isset($_POST["return_url"])) {
                $return_url = htmlspecialchars_decode($_POST['return_url']);
                $upload_param = str_contains($return_url, "?") ? "&uploaded=success" : "?uploaded=success";
                header("Location:".$return_url . $upload_param);
                exit();
            }
            if (!empty($station->upload_page_slug)) {
                header("Location:".fpp_get_slug_page_link($station->upload_page_slug). "?uploaded=true");
                exit();
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
   register_rest_route('fpp/v1', '/photo_upload/(?P<station_slug>[a-z0-9-]+)', array(
        'methods'=>'POST',
        'callback'=>'fpp_process_upload',
        'permission_callback'=> '__return_true',
        'args' => array(
            'station_slug' => array(
                'validate_callback' => function($param, $request, $key) {
                return is_string( $param );
                }
            ),
        ),
   )); 
}

add_action('rest_api_init', 'fpp_register_routes');
?>