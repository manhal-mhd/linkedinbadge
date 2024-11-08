<?php
defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_footer_html_generation;

class local_linkedinbadge_output_callback {
    /**
     * Handle footer generation
     */
    public static function before_footer_html(before_footer_html_generation $hook) {
        global $PAGE, $USER, $DB;
        
        // Only add button on badge pages
        if (strpos($PAGE->url->get_path(), '/badges/') !== false) {
            $badgeid = optional_param('id', 0, PARAM_INT);
            
            if ($badgeid) {
                // Check if user has this badge
                $issued = $DB->record_exists('badge_issued', 
                    array('badgeid' => $badgeid, 'userid' => $USER->id)
                );
                
                if ($issued) {
                    $share_url = new moodle_url('/local/linkedinbadge/share_badge.php', 
                        array('badge' => $badgeid)
                    );
                    
                    $button = \html_writer::link(
                        $share_url,
                        get_string('share_linkedin', 'local_linkedinbadge'),
                        array('class' => 'btn btn-primary ml-2')
                    );
                    
                    $hook->add_html($button);
                }
            }
        }
    }
}

/**
 * Add LinkedIn menu item to navigation
 */
function local_linkedinbadge_extend_navigation(global_navigation $navigation) {
    global $USER, $DB;
    
    if (isloggedin()) {
        $token = $DB->get_field('user_preferences', 'value', 
            array('userid' => $USER->id, 'name' => 'local_linkedinbadge_linkedin_token')
        );
        
        $text = $token ? 
            get_string('linkedin_connected', 'local_linkedinbadge') : 
            get_string('connect_linkedin', 'local_linkedinbadge');
        
        $url = new moodle_url('/local/linkedinbadge/connect.php');
        
        if ($usernode = $navigation->find('myprofile', navigation_node::TYPE_ROOTNODE)) {
            $usernode->add(
                $text,
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'linkedinconnection',
                new pix_icon('i/badge', '')
            );
        }
    }
}

/**
 * Hook callback registration
 */
function local_linkedinbadge_after_config() {
    global $CFG;
    
    $manager = \core\hook\manager::get_instance();
    $manager->register_hook_callback(
        before_footer_html_generation::class,
        [local_linkedinbadge_output_callback::class, 'before_footer_html']
    );
}
