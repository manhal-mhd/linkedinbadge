<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Create settings page
    $settings = new admin_settingpage(
        'local_linkedinbadge',
        get_string('pluginname', 'local_linkedinbadge')
    );
    
    $ADMIN->add('localplugins', $settings);

    // Add settings section
    $settings->add(new admin_setting_heading(
        'local_linkedinbadge/linkedin_settings',
        get_string('linkedin_settings', 'local_linkedinbadge'),
        get_string('linkedin_settings_desc', 'local_linkedinbadge')
    ));

    // LinkedIn Client ID
    $settings->add(new admin_setting_configtext(
        'local_linkedinbadge/linkedin_client_id',
        get_string('linkedin_client_id', 'local_linkedinbadge'),
        get_string('linkedin_client_id_desc', 'local_linkedinbadge'),
        '',
        PARAM_TEXT
    ));

    // LinkedIn Client Secret
    $settings->add(new admin_setting_configpasswordunmask(
        'local_linkedinbadge/linkedin_client_secret',
        get_string('linkedin_client_secret', 'local_linkedinbadge'),
        get_string('linkedin_client_secret_desc', 'local_linkedinbadge'),
        ''
    ));
}

