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
        
        // Get badge image URL using correct method
        $context = context_system::instance();
        $image_url = moodle_url::make_pluginfile_url(
            $context->id,
            'badges',
            'badgeimage',
            $badge->id,
            '/',
            'f1',
            false
        );
        
        // Display badge preview
        echo "<div class='badge-preview text-center mb-4'>";
        echo "<img src='" . $image_url . "' alt='" . format_string($badge->name) . "' class='mb-3' style='max-width: 200px;'>";
        echo "<div class='badge-description'>";
        echo format_text($badge->description, FORMAT_HTML);
        echo "</div>";
        echo "</div>";
        
        // Share form
        echo "<form method='post' action='post_badge.php' class='mt-4'>";
        echo "<input type='hidden' name='badge' value='" . $badgeid . "'>";
        echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
        
        // Default share message
        $site_name = format_string($SITE->fullname);
        $badge_name = format_string($badge->name);
        $default_message = get_string('default_share_message', 'local_linkedinbadge', [
            'badge' => $badge_name,
            'site' => $site_name,
            'description' => format_string($badge->description)
        ]);
        
        echo "<div class='form-group'>";
        echo "<label for='message' class='form-label'>" . get_string('customize_message', 'local_linkedinbadge') . "</label>";
        echo "<textarea class='form-control' id='message' name='message' rows='4'>";
        echo htmlspecialchars($default_message);
        echo "</textarea>";
        echo "<small class='form-text text-muted'>" . get_string('customize_message_help', 'local_linkedinbadge') . "</small>";
        echo "</div>";
        
        echo "<div class='mt-4'>";
        echo "<button type='submit' class='btn btn-primary'>";
        echo "<i class='fa fa-linkedin'></i> ";
        echo get_string('share_on_linkedin', 'local_linkedinbadge');
        echo "</button> ";
        
        echo "<a href='" . new moodle_url('/badges/mybadges.php') . "' class='btn btn-secondary'>";
        echo get_string('cancel', 'moodle');
        echo "</a>";
        echo "</div>";
        
        echo "</form>";
        echo "</div>"; // card-body
        echo "</div>"; // card
    }
    echo "</div>"; // container
    
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
