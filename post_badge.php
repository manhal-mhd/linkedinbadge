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

    // Get all files in the badgeimage filearea for this badge and pick the largest (by pixels then filesize)
    $files = $fs->get_area_files($context->id, 'badges', 'badgeimage', $badge->id, 'id', false);
    $best = null;
    $best_score = 0;

    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }
        // Copy to temp to inspect dimensions
        $temp_path = tempnam(sys_get_temp_dir(), 'badge_');
        if ($temp_path === false) {
            continue;
        }
        if (!$file->copy_content_to($temp_path)) {
            @unlink($temp_path);
            continue;
        }

        $image_info = @getimagesize($temp_path);
        $width = $image_info ? $image_info[0] : 0;
        $height = $image_info ? $image_info[1] : 0;
        $filesize = $file->get_filesize();

        // Score by area first, then filesize
        $score = ($width * $height) + intval($filesize / 1024);

        \local_linkedinbadge\logger::log('Inspecting badge file', [
            'filename' => $file->get_filename(),
            'mime' => $file->get_mimetype(),
            'width' => $width,
            'height' => $height,
            'filesize' => $filesize,
            'score' => $score
        ]);

        if ($score > $best_score) {
            // remove previous best temp file
            if ($best && !empty($best['temp'])) {
                @unlink($best['temp']);
            }
            $best_score = $score;
            $best = [
                'file' => $file,
                'temp' => $temp_path,
                'width' => $width,
                'height' => $height,
                'filesize' => $filesize,
                'mime' => $file->get_mimetype()
            ];
        } else {
            @unlink($temp_path);
        }
    }

    if ($best) {
        \local_linkedinbadge\logger::log('Selected badge image', [
            'filename' => $best['file']->get_filename(),
            'mime' => $best['mime'],
            'width' => $best['width'],
            'height' => $best['height'],
            'filesize' => $best['filesize']
        ]);

        return [
            'path' => $best['temp'],
            'type' => 'temp',
            'mime' => $best['mime']
        ];
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

    // Start with the original file and mime; we'll upscale/convert later if needed.
    $result = [
        'path' => $image_path,
        'mime' => $image_info['mime'],
        'is_temp' => strpos($image_path, sys_get_temp_dir()) === 0
    ];

    // Optionally upscale small images to improve social preview quality.
    // Use ImageMagick `convert` when available for high-quality Lanczos resampling
    // followed by an unsharp mask. Fall back to GD upscaling otherwise.
    $min_side = 600; // minimum target dimension (px)
    $info = @getimagesize($result['path']);
    if ($info && ($info[0] < $min_side || $info[1] < $min_side)) {
        // check for ImageMagick
        @exec('command -v convert', $out, $rc);
        \local_linkedinbadge\logger::log('convert availability', ['out' => $out, 'rc' => $rc]);
        if ($rc === 0 && !empty($out[0])) {
            $convert = $out[0];
            // create temp path with proper extension based on desired output format
            $ext = ($result['mime'] === 'image/png' || $result['mime'] === 'image/gif') ? '.png' : '.jpg';
            $tmp2 = tempnam(sys_get_temp_dir(), 'badge_up_') . $ext;
            // target social card size
            $target_w = 1200;
            $target_h = 630;
            $srcarg = escapeshellarg($result['path']);
            $dstarg = escapeshellarg($tmp2);
            // Use resize^ + extent to fill and center, then unsharp. Prefer PNG output for graphics.
            if ($ext === '.png') {
                $cmd = "$convert $srcarg -filter Lanczos -resize {$target_w}x{$target_h}^ -gravity center -background white -extent {$target_w}x{$target_h} -strip -unsharp 0x1+0.75+0.02 $dstarg";
            } else {
                $cmd = "$convert $srcarg -filter Lanczos -resize {$target_w}x{$target_h}^ -gravity center -background white -extent {$target_w}x{$target_h} -strip -unsharp 0x1+0.75+0.02 -quality 95 $dstarg";
            }
            @exec($cmd, $o, $r);
            if ($r === 0 && file_exists($tmp2) && filesize($tmp2) > 0) {
                // replace
                if ($result['is_temp'] && file_exists($result['path'])) {
                    @unlink($result['path']);
                }
                $result['path'] = $tmp2;
                $result['is_temp'] = true;
                $result['mime'] = ($ext === '.png') ? 'image/png' : 'image/jpeg';
            } else {
                @unlink($tmp2);
            }
        } else {
            // GD fallback: upscale preserving aspect ratio
            $srcinfo = @getimagesize($result['path']);
                if ($srcinfo) {
                $sw = $srcinfo[0];
                $sh = $srcinfo[1];
                // Fit inside target canvas while preserving aspect, then center on 1200x630 canvas
                $target_w = 1200;
                $target_h = 630;
                $scale = min($target_w / $sw, $target_h / $sh);
                $dw = (int)round($sw * $scale);
                $dh = (int)round($sh * $scale);
                // create source image depending on mime
                switch ($result['mime']) {
                    case 'image/png':
                        $srcimg = imagecreatefrompng($result['path']);
                        break;
                    case 'image/gif':
                        $srcimg = imagecreatefromgif($result['path']);
                        break;
                    default:
                        $srcimg = imagecreatefromjpeg($result['path']);
                        break;
                }
                if ($srcimg) {
                    // create final canvas and center the resized image
                    $dst = imagecreatetruecolor($target_w, $target_h);
                    $white = imagecolorallocate($dst, 255,255,255);
                    imagefilledrectangle($dst,0,0,$target_w,$target_h,$white);
                    $dst_resized = imagecreatetruecolor($dw, $dh);
                    imagecopyresampled($dst_resized, $srcimg, 0,0,0,0, $dw, $dh, $sw, $sh);
                    $dst_x = (int)(($target_w - $dw) / 2);
                    $dst_y = (int)(($target_h - $dh) / 2);
                    imagecopy($dst, $dst_resized, $dst_x, $dst_y, 0, 0, $dw, $dh);
                    imagedestroy($dst_resized);
                    // choose output format to match source for best quality on graphics
                    $ext = ($result['mime'] === 'image/png' || $result['mime'] === 'image/gif') ? '.png' : '.jpg';
                    $tmp2 = tempnam(sys_get_temp_dir(), 'badge_up_') . $ext;
                    if ($ext === '.png') {
                        imagepng($dst, $tmp2, 6);
                    } else {
                        imagejpeg($dst, $tmp2, 95);
                    }
                    imagedestroy($dst);
                    imagedestroy($srcimg);
                    if (file_exists($tmp2) && filesize($tmp2) > 0) {
                        if ($result['is_temp'] && file_exists($result['path'])) {
                            @unlink($result['path']);
                        }
                        $result['path'] = $tmp2;
                        $result['is_temp'] = true;
                        $result['mime'] = ($ext === '.png') ? 'image/png' : 'image/jpeg';
                    } else {
                        @unlink($tmp2);
                    }
                }
            }
        }
    }

    return $result;
}

require_once('../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_login();

// Bootstrap diagnostic: unique run id and environment to help correlate log entries
$__linkedin_runid = uniqid('linkpost_', true);
\local_linkedinbadge\logger::log('script_start', [
    'runid' => $__linkedin_runid,
    'file' => __FILE__,
    'file_mtime' => @filemtime(__FILE__),
    'php_sapi' => php_sapi_name(),
    'pid' => getmypid(),
    'time' => time()
]);

global $DB, $USER, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/linkedinbadge/post_badge.php'));
$PAGE->set_title(get_string('share_on_linkedin', 'local_linkedinbadge'));

// Add detailed debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
debugging('', DEBUG_DEVELOPER);

echo $OUTPUT->header();

function upload_image_to_linkedin($token, $image_path, $linkedin_person_id, $mime = 'image/jpeg') {
    try {
        // Immediate diagnostic: log the image path we received, its size and hash to confirm
        $real = @realpath($image_path);
        $exists = file_exists($image_path);
        $size = $exists ? @filesize($image_path) : 0;
        $md5 = $exists ? @md5_file($image_path) : null;
        \local_linkedinbadge\logger::log('upload_called', [
            'image_path' => $image_path,
            'realpath' => $real,
            'exists' => $exists,
            'size' => $size,
            'md5' => $md5,
            'mime_hint' => $mime
        ]);

        // Step 1: Register the image upload with LinkedIn
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
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        \local_linkedinbadge\logger::log('register_upload_response', [
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => $curl_error
        ]);

        if ($http_code < 200 || $http_code >= 300) {
            $response_data = json_decode($response, true);
            $expired = false;
            if ($http_code === 401) {
                $expired = true;
            }
            if (is_array($response_data)) {
                if (!empty($response_data['serviceErrorCode']) && $response_data['serviceErrorCode'] == 65602) {
                    $expired = true;
                }
                if (!empty($response_data['code']) && $response_data['code'] === 'EXPIRED_ACCESS_TOKEN') {
                    $expired = true;
                }
            }
            if ($expired) {
                throw new \Exception('EXPIRED_ACCESS_TOKEN');
            }
            throw new moodle_exception('failed_register', 'local_linkedinbadge', $response);
        }

        $response_data = json_decode($response, true);
        if (empty($response_data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl']) || empty($response_data['value']['asset'])) {
            throw new moodle_exception('failed_register', 'local_linkedinbadge', $response);
        }

        $upload_url = $response_data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $asset = $response_data['value']['asset'];

        // Step 2: Upload the image bytes to the provided upload URL.
        // NOTE: The uploadUrl is typically a pre-signed URL and must NOT include the Authorization header.
        $image_content = @file_get_contents($image_path);
        if ($image_content === false) {
            throw new moodle_exception('Failed to read image content');
        }
        // Log actual upload byte length for debugging (what we will PUT)
        \local_linkedinbadge\logger::log('Image upload preparing', [
            'path' => $image_path,
            'content_length' => strlen($image_content),
            'mime' => $mime
        ]);

        $ch = curl_init($upload_url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $image_content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: ' . $mime,
                'Content-Length: ' . strlen($image_content)
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        \local_linkedinbadge\logger::log('image_upload_response', [
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => $curl_error
        ]);

        if ($http_code < 200 || $http_code >= 300) {
            $response_data = json_decode($response, true);
            $expired = false;
            if ($http_code === 401) {
                $expired = true;
            }
            if (is_array($response_data)) {
                if (!empty($response_data['serviceErrorCode']) && $response_data['serviceErrorCode'] == 65602) {
                    $expired = true;
                }
                if (!empty($response_data['code']) && $response_data['code'] === 'EXPIRED_ACCESS_TOKEN') {
                    $expired = true;
                }
            }
            if ($expired) {
                throw new \Exception('EXPIRED_ACCESS_TOKEN');
            }
            throw new moodle_exception('failed_upload', 'local_linkedinbadge', $response ?: $curl_error);
        }

        // Successful upload — return the asset URN to be used in the post payload.
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
    // Accept the raw trimmed message so placeholders and user formatting survive until we sanitize for the API
    $message = required_param('message', PARAM_RAW_TRIMMED);

    // Log incoming request for debugging
    \local_linkedinbadge\logger::log('post_badge request', [
        'userid' => $USER->id,
        'badgeid' => $badgeid,
        'post_keys' => array_keys($_POST)
    ]);

    // Get badge details (do not use MUST_EXIST so we can give clearer errors)
    $badge = $DB->get_record('badge', ['id' => $badgeid], '*', IGNORE_MISSING);
    if (!$badge) {
        \local_linkedinbadge\logger::log('Badge not found', ['badgeid' => $badgeid]);
        echo '<div class="container">';
        echo $OUTPUT->notification('Badge not found in database.', 'error');
        echo '<div class="mt-3">';
        echo '<a href="' . new moodle_url('/badges/mybadges.php') . '" class="btn btn-secondary">' . get_string('return_to_badges', 'local_linkedinbadge') . '</a>';
        echo '</div></div>';
        echo $OUTPUT->footer();
        exit;
    }

    // Verify badge is issued to user
    $issued = $DB->get_record('badge_issued', ['badgeid' => $badgeid, 'userid' => $USER->id], '*', IGNORE_MISSING);
    if (!$issued) {
        \local_linkedinbadge\logger::log('Badge not issued to user', ['badgeid' => $badgeid, 'userid' => $USER->id]);
        echo '<div class="container">';
        echo $OUTPUT->notification('You have not been issued this badge.', 'error');
        echo '<div class="mt-3">';
        echo '<a href="' . new moodle_url('/badges/mybadges.php') . '" class="btn btn-secondary">' . get_string('return_to_badges', 'local_linkedinbadge') . '</a>';
        echo '</div></div>';
        echo $OUTPUT->footer();
        exit;
    }

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

    // Prepare share URLs once so message and card use the exact same link
    $v_share = time();
    if (!empty($issued) && !empty($issued->uniquehash)) {
        $credential_url = $CFG->wwwroot . '/local/linkedinbadge/credential.php?hash=' . $issued->uniquehash . '&v=' . $v_share;
    } else {
        $credential_url = $CFG->wwwroot . '/badges/mybadges.php';
    }

    // Prepare the share message: decode entities and replace any {$a->...} placeholders
    $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5);
    if (strpos($message, '{$a->') !== false) {
        global $SITE, $CFG;
        $a = new stdClass();
        $a->badge = format_string($badge->name);
        $a->site = $SITE->fullname;
        $a->url = $credential_url; // Use the cache-busted URL
        $message = get_string('default_share_message', 'local_linkedinbadge', $a);
    }

    // Upload the prepared image to LinkedIn and create an IMAGE post
    \local_linkedinbadge\logger::log('image_post_start', [
        'message_length' => strlen($message),
        'processed_image' => isset($processed_image) ? $processed_image : null,
    ]);

    // Upload image bytes to LinkedIn to obtain an asset URN
    $asset = upload_image_to_linkedin($token->value, $processed_image['path'], $linkedin_person_id, $processed_image['mime']);

    \local_linkedinbadge\logger::log('image_upload_asset', ['asset' => $asset]);

    // Prepare IMAGE post payload using the returned asset URN
    $post_data = [
        "author" => "urn:li:person:" . $linkedin_person_id,
        "lifecycleState" => "PUBLISHED",
        "specificContent" => [
            "com.linkedin.ugc.ShareContent" => [
                "shareCommentary" => [
                    "text" => $message
                ],
                "shareMediaCategory" => "IMAGE",
                "media" => [
                    [
                        "status" => "READY",
                        "media" => $asset
                    ]
                ]
            ]
        ],
        "visibility" => [
            "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"
        ]
    ];

    // Log the final payload before sending
    \local_linkedinbadge\logger::log('linkedin_image_post_payload', $post_data);

    // Make the API call to post the image share
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
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Log the UGC post response for debugging
    \local_linkedinbadge\logger::log('ugc_image_post_response', [
        'http_code' => $http_code,
        'response' => $response,
        'curl_error' => $curl_error,
        'post_payload' => substr(json_encode($post_data), 0, 2000)
    ]);

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
