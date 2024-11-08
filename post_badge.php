<?php
// Add these functions at the beginning of your file, after the require statements
function get_badge_image_file($badge) {
    global $DB;
    
    // Get the file storage instance
    $fs = get_file_storage();
    
    // Get context ID from badge's courseid or use system context
    if (!empty($badge->courseid)) {
        $context = context_course::instance($badge->courseid);
    } else {
        $context = context_system::instance();
    }
    
    // Log the attempt
    \local_linkedinbadge\logger::log('Attempting to retrieve badge image', [
        'badge_id' => $badge->id,
        'context_id' => $context->id,
        'badge_name' => $badge->name
    ]);

    // Try both possible filenames
    $filenames = ['f1', 'f1.png'];
    
    foreach ($filenames as $filename) {
        $file = $fs->get_file(
            $context->id,
            'badges',
            'badgeimage',
            $badge->id,
            '/',
            $filename
        );
        
        if ($file) {
            // Create temporary file
            $temp_path = tempnam(sys_get_temp_dir(), 'badge_');
            if ($temp_path === false) {
                throw new moodle_exception('Failed to create temporary file');
            }

            // Copy file content to temporary file
            if (!$file->copy_content_to($temp_path)) {
                unlink($temp_path);
                throw new moodle_exception('Failed to copy badge image to temporary file');
            }

            \local_linkedinbadge\logger::log('Badge image found and copied', [
                'temp_path' => $temp_path,
                'original_filename' => $file->get_filename(),
                'filesize' => $file->get_filesize(),
                'mimetype' => $file->get_mimetype()
            ]);

            return [
                'path' => $temp_path,
                'type' => 'temp',
                'mime' => $file->get_mimetype()
            ];
        }
    }

    // If we get here, we couldn't find the image
    throw new moodle_exception('Badge image not found. Please ensure the badge has an image attached.');
}

function validate_and_prepare_image($image_path) {
    // Log validation start
    \local_linkedinbadge\logger::log('Validating image', [
        'path' => $image_path,
        'exists' => file_exists($image_path),
        'size' => filesize($image_path)
    ]);

    // Verify file exists and is readable
    if (!file_exists($image_path)) {
        throw new moodle_exception('Image file not found: ' . $image_path);
    }
    
    if (!is_readable($image_path)) {
        throw new moodle_exception('Image file not readable: ' . $image_path);
    }

    // Get image information
    $image_info = getimagesize($image_path);
    if ($image_info === false) {
        throw new moodle_exception('Invalid image file format');
    }

    // Log image details
    \local_linkedinbadge\logger::log('Image details', [
        'mime' => $image_info['mime'],
        'width' => $image_info[0],
        'height' => $image_info[1]
    ]);

    // Convert to JPEG if needed
    if ($image_info['mime'] !== 'image/jpeg') {
        $temp_path = tempnam(sys_get_temp_dir(), 'badge_');
        
        // Create source image based on type
        switch ($image_info['mime']) {
            case 'image/png':
                $source = imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($image_path);
                break;
            default:
                throw new moodle_exception('Unsupported image type: ' . $image_info['mime']);
        }

        if (!$source) {
            throw new moodle_exception('Failed to load source image');
        }

        // Create new image
        $width = imagesx($source);
        $height = imagesy($source);
        $new_image = imagecreatetruecolor($width, $height);
        
        // Preserve transparency
        imagealphablending($new_image, true);
        imagesavealpha($new_image, true);
        
        // Fill with white background
        $white = imagecolorallocate($new_image, 255, 255, 255);
        imagefilledrectangle($new_image, 0, 0, $width, $height, $white);
        
        // Copy the original image
        imagecopy($new_image, $source, 0, 0, 0, 0, $width, $height);
        
        // Save as JPEG
        if (!imagejpeg($new_image, $temp_path, 90)) {
            imagedestroy($source);
            imagedestroy($new_image);
            unlink($temp_path);
            throw new moodle_exception('Failed to convert image to JPEG');
        }

        imagedestroy($source);
        imagedestroy($new_image);

        // Clean up original temp file if it was temporary
        if (strpos($image_path, sys_get_temp_dir()) === 0) {
            unlink($image_path);
        }

        return [
            'path' => $temp_path,
            'mime' => 'image/jpeg',
            'is_temp' => true
        ];
    }

    // If already JPEG, return original
    return [
        'path' => $image_path,
        'mime' => 'image/jpeg',
        'is_temp' => strpos($image_path, sys_get_temp_dir()) === 0
    ];
}

require_once('../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/linkedinbadge/post_badge.php'));
$PAGE->set_title(get_string('share_linkedin', 'local_linkedinbadge'));

// Add detailed debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
debugging('', DEBUG_DEVELOPER);

echo $OUTPUT->header();

function upload_image_to_linkedin($token, $image_path, $linkedin_person_id) {
    try {
        // Step 1: Register the image upload
        $register_url = 'https://api.linkedin.com/v2/assets?action=registerUpload';
        $post_data = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => 'urn:li:person:' . $linkedin_person_id,
                'serviceRelationships' => [
                    [
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent'
                    ]
                ]
            ]
        ];

        $ch = curl_init($register_url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($post_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        \local_linkedinbadge\logger::log('Register upload response', [
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => $curl_error
        ]);

        curl_close($ch);

        if ($http_code !== 200) {
            throw new moodle_exception('Failed to register upload: ' . $response);
        }

        $response_data = json_decode($response, true);
        $upload_url = $response_data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $asset = $response_data['value']['asset'];

        // Step 2: Upload the image
        $image_content = file_get_contents($image_path);
        if ($image_content === false) {
            throw new moodle_exception('Failed to read image content');
        }

        $ch = curl_init($upload_url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $image_content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: image/jpeg',
                'Content-Length: ' . strlen($image_content)
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        \local_linkedinbadge\logger::log('Image upload response', [
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => curl_error($ch)
        ]);

        curl_close($ch);

        if ($http_code !== 201) {
            throw new moodle_exception('Failed to upload image');
        }

        return $asset;

    } catch (Exception $e) {
        \local_linkedinbadge\logger::log('Upload error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}
try {
    require_sesskey();
    
    $badgeid = required_param('badge', PARAM_INT);
    $message = required_param('message', PARAM_TEXT);

    // Get badge details
    $badge = $DB->get_record('badge', ['id' => $badgeid], '*', MUST_EXIST);
    
    // Verify badge is issued to user
    $issued = $DB->get_record('badge_issued',
        ['badgeid' => $badgeid, 'userid' => $USER->id],
        '*',
        MUST_EXIST
    );

    // Get badge image using the new method
    $image_file = get_badge_image_file($badge);
    
    // Validate and prepare the image for LinkedIn
    $processed_image = validate_and_prepare_image($image_file['path']);

    // Get LinkedIn tokens
    $token = $DB->get_record('user_preferences',
        array('userid' => $USER->id, 'name' => 'local_linkedinbadge_linkedin_token'),
        'value',
        MUST_EXIST
    );
    
    $id_token = $DB->get_record('user_preferences',
        array('userid' => $USER->id, 'name' => 'local_linkedinbadge_linkedin_id_token'),
        'value',
        MUST_EXIST
    );

    // Decode the ID token to get the LinkedIn person ID
    $id_token_parts = explode('.', $id_token->value);
    if (count($id_token_parts) !== 3) {
        throw new moodle_exception('Invalid ID token format');
    }

    $id_token_payload = json_decode(base64_decode(strtr($id_token_parts[1], '-_', '+/')), true);
    if (!isset($id_token_payload['sub'])) {
        throw new moodle_exception('Invalid ID token payload');
    }

    $linkedin_person_id = $id_token_payload['sub'];

    // Upload the image to LinkedIn
    $image_urn = upload_image_to_linkedin($token->value, $processed_image['path'], $linkedin_person_id);

    // Create the post with the image
    $post_data = [
        'author' => 'urn:li:person:' . $linkedin_person_id,
        'lifecycleState' => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary' => [
                    'text' => $message
                ],
                'shareMediaCategory' => 'IMAGE',
                'media' => [
                    [
                        'status' => 'READY',
                        'description' => [
                            'text' => 'Badge Image'
                        ],
                        'media' => $image_urn,
                        'title' => [
                            'text' => $badge->name
                        ]
                    ]
                ]
            ]
        ],
        'visibility' => [
            'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
        ]
    ];

    // Initialize cURL session for post creation
    $ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($post_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token->value,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo '<div class="container">';

    if ($http_code === 201) {
        echo $OUTPUT->notification(
            get_string('success:badge_shared', 'local_linkedinbadge'),
            'success'
        );

        echo '<div class="mt-3">';
        echo '<a href="https://www.linkedin.com/feed/" target="_blank" class="btn btn-primary">';
        echo '<i class="fa fa-linkedin"></i> ';
        echo get_string('view_on_linkedin', 'local_linkedinbadge');
        echo '</a> ';

        echo '<a href="' . new moodle_url('/badges/mybadges.php') . '" class="btn btn-secondary">';
        echo get_string('return_to_badges', 'local_linkedinbadge');
        echo '</a>';
        echo '</div>';
    } else {
        $error_data = json_decode($response, true);
        $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';

        echo $OUTPUT->notification(
            get_string('error:share_failed', 'local_linkedinbadge', $error_message),
            'error'
        );

        echo '<div class="mt-3">';
        echo '<a href="' . new moodle_url('/local/linkedinbadge/share_badge.php', ['badge' => $badgeid]) . 
             '" class="btn btn-primary">';
        echo get_string('try_again', 'local_linkedinbadge');
        echo '</a> ';

        echo '<a href="' . new moodle_url('/badges/mybadges.php') . '" class="btn btn-secondary">';
        echo get_string('return_to_badges', 'local_linkedinbadge');
        echo '</a>';
        echo '</div>';
    }

    echo '</div>';

} catch (moodle_exception $e) {
    \local_linkedinbadge\logger::log('Error processing badge', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo '<div class="container">';
    echo $OUTPUT->notification($e->getMessage(), 'error');
    
    echo '<div class="mt-3">';
    echo '<a href="' . new moodle_url('/badges/mybadges.php') . '" class="btn btn-secondary">';
    echo get_string('return_to_badges', 'local_linkedinbadge');
    echo '</a>';
    echo '</div>';
    echo '</div>';

} finally {
    // Clean up temporary files
    if (isset($image_file) && $image_file['type'] === 'temp') {
        @unlink($image_file['path']);
    }
    if (isset($processed_image) && $processed_image['is_temp']) {
        @unlink($processed_image['path']);
    }
}

echo $OUTPUT->footer();
