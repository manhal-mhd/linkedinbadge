<?php
require_once('../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_login();

$badgeid = required_param('badge', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/linkedinbadge/share_badge.php', array('badge' => $badgeid)));

// Use a more generic title if the string isn't found
$title = get_string('share_on_linkedin', 'local_linkedinbadge', '', true) ?: 'Share Badge on LinkedIn';
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

try {
    global $DB, $USER, $CFG, $SITE;
    
    // Get badge record
    $badge = $DB->get_record('badge', array('id' => $badgeid), '*', MUST_EXIST);
    
    // Check if badge is issued to user
    $issued = $DB->get_record('badge_issued', 
        array('badgeid' => $badgeid, 'userid' => $USER->id), 
        '*', 
        MUST_EXIST
    );

    if (!$issued) {
        throw new moodle_exception('error:badgenotearned', 'local_linkedinbadge');
    }

    // Get LinkedIn token
    $token = $DB->get_field('user_preferences', 'value', 
        array('userid' => $USER->id, 'name' => 'local_linkedinbadge_linkedin_token')
    );

    echo "<div class='container'>";
    
    if (!$token) {
        // Show LinkedIn connect option
        echo "<div class='alert alert-info'>";
        echo get_string('linkedin_connect_required', 'local_linkedinbadge');
        echo "</div>";

        $oauth = new \local_linkedinbadge\linkedin_oauth();
        // Save current page (including badge param) so we can return after OAuth
        global $SESSION;
        $SESSION->linkedin_return = $PAGE->url->out(false);
        $auth_url = $oauth->get_auth_url();
        
        echo "<div class='mt-3'>";
        echo "<a href='" . $auth_url . "' class='btn btn-primary'>";
        echo "<i class='fa fa-linkedin'></i> ";
        echo get_string('connect_linkedin', 'local_linkedinbadge');
        echo "</a> ";
        
        echo "<a href='" . new moodle_url('/badges/mybadges.php') . "' class='btn btn-secondary'>";
        echo get_string('cancel', 'moodle');
        echo "</a>";
        echo "</div>";
    } else {
        // Show sharing interface
        echo "<div class='card'>";
        echo "<div class='card-body'>";
        
        // Badge title
        echo "<h3 class='card-title'>" . get_string('share_badge', 'local_linkedinbadge', format_string($badge->name)) . "</h3>";
        
        // Get the best badge image file from file storage and build a pluginfile URL
        try {
            $fs = get_file_storage();
            $ctx = !empty($badge->courseid) ? context_course::instance($badge->courseid) : context_system::instance();
            $files = $fs->get_area_files($ctx->id, 'badges', 'badgeimage', $badge->id, 'id', false);
            $bestfile = null;
            $bestscore = 0;
            foreach ($files as $f) {
                if ($f->is_directory()) { continue; }
                $tmp = tempnam(sys_get_temp_dir(), 'badge_');
                if ($tmp && $f->copy_content_to($tmp)) {
                    $info = @getimagesize($tmp);
                    @unlink($tmp);
                    $score = 0;
                    if ($info) {
                        $score = ($info[0] * $info[1]);
                    } else {
                        $score = $f->get_filesize();
                    }
                    if ($score > $bestscore) {
                        $bestscore = $score;
                        $bestfile = $f;
                    }
                }
            }
            if ($bestfile) {
                $image_url = moodle_url::make_pluginfile_url(
                    $ctx->id,
                    'badges',
                    'badgeimage',
                    $badge->id,
                    '/',
                    $bestfile->get_filename(),
                    false
                );
            } else {
                // fallback to system file url if none found
                $image_url = moodle_url::make_pluginfile_url(
                    context_system::instance()->id,
                    'badges',
                    'badgeimage',
                    $badge->id,
                    '/',
                    'f1',
                    false
                );
            }
        } catch (Exception $e) {
            $image_url = moodle_url::make_pluginfile_url(
                context_system::instance()->id,
                'badges',
                'badgeimage',
                $badge->id,
                '/',
                'f1',
                false
            );
        }
        
        // Display badge preview
        echo "<div class='badge-preview text-center mb-4'>";
        echo "<img src='" . $image_url . "' alt='" . format_string($badge->name) . "' class='mb-3' style='max-width: 200px;'>";
        echo "<div class='badge-description'>";
        echo format_text($badge->description, FORMAT_HTML);
        echo "</div>";
        echo "</div>";

        // Show credential link and ID so user can add it to their LinkedIn profile
        if (!empty($issued) && !empty($issued->uniquehash)) {
            $credential_url = $CFG->wwwroot . '/local/linkedinbadge/credential.php?hash=' . $issued->uniquehash;
            $credential_id = (int)$issued->id;

            echo "<div class='mb-3 d-flex align-items-center'>";
            echo "<a href='" . $credential_url . "' target='_blank' class='btn btn-outline-secondary mr-3'>";
            echo get_string('show_credential', 'local_linkedinbadge');
            echo "</a>";

            echo "<div>";
            echo "<div><strong>" . get_string('credential_id', 'local_linkedinbadge') . ":</strong> " . s($credential_id) . "</div>";
            echo "<small class='text-muted'>" . get_string('credential_help', 'local_linkedinbadge') . "</small>";
            echo "</div>";
            echo "</div>";
        }
        
        // The form is now replaced by a button that triggers client-side sharing.
        // The textarea is kept for copying the message.
        $a = new stdClass();
        $a->badge = format_string($badge->name);
        $a->site = $SITE->fullname;
        $a->url = $credential_url;
        $default_message = get_string('default_share_message', 'local_linkedinbadge', $a);

        echo "<form action='post_badge.php' method='post'>";
        echo "<input type='hidden' name='badgeid' value='" . $badgeid . "'>";
        echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
        
        echo "<div class='form-group'>";
        echo "<label for='share_message'>" . get_string('share_message_label', 'local_linkedinbadge') . "</label>";
        echo "<textarea name='message' id='share_message' class='form-control' rows='5'>" . s($default_message) . "</textarea>";
        echo "</div>";

        echo "<div class='mt-3'>";
        echo "<button type='submit' class='btn btn-primary'><i class='fa fa-linkedin'></i> " . get_string('share_on_linkedin', 'local_linkedinbadge') . "</button>";
        echo "<a href='" . new moodle_url('/badges/mybadges.php') . "' class='btn btn-secondary ml-2'>" . get_string('cancel', 'moodle') . "</a>";
        echo "</div>";
        echo "</form>";
    }
    
    echo "</div>"; // Close container
    
} catch (Exception $e) {
    echo "<div class='container'>";
    echo $OUTPUT->notification($e->getMessage(), 'error');
    
    echo "<div class='mt-3'>";
    echo "<a href='" . new moodle_url('/badges/mybadges.php') . "' class='btn btn-secondary'>";
    echo get_string('return_to_badges', 'local_linkedinbadge');
    echo "</a>";
    echo "</div>";
    echo "</div>";
}

echo $OUTPUT->footer(); 

// Inline helper to copy share text before opening LinkedIn
echo "<script>
function copyMessageText(){
    try{
        var ta = document.getElementById('message');
        if (!ta) return;
        var text = ta.value || '';
        var hint = document.getElementById('copy-hint');
        if (text && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function(){
                if (hint) hint.textContent = 'Text copied. Paste it in LinkedIn.';
            }).catch(function(){
                ta.select();
                document.execCommand('copy');
                if (hint) hint.textContent = 'Text copied. Paste it in LinkedIn.';
            });
        } else {
            ta.select();
            document.execCommand('copy');
            if (hint) hint.textContent = 'Text copied. Paste it in LinkedIn.';
        }
    } catch(e) {}
}
</script>";
