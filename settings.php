<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_linkedinbadge', get_string('pluginname', 'local_linkedinbadge'));
    $ADMIN->add('localplugins', $settings);

    // LinkedIn settings
    $settings->add(new admin_setting_heading(
        'local_linkedinbadge/linkedin_settings',
        get_string('linkedin_settings', 'local_linkedinbadge'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_linkedinbadge/linkedin_client_id',
        get_string('linkedin_client_id', 'local_linkedinbadge'),
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_linkedinbadge/linkedin_client_secret',
        get_string('linkedin_client_secret', 'local_linkedinbadge'),
        '',
        ''
    ));
}
