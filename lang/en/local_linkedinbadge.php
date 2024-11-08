<?php
// General
$string['pluginname'] = 'Local LinkedIn Badge Share';
$string['plugindesc'] = 'Share Moodle badges directly to LinkedIn';

// Settings page
$string['linkedin_settings'] = 'LinkedIn Settings';
$string['linkedin_settings_desc'] = 'Configure your LinkedIn application credentials';
$string['linkedin_client_id'] = 'LinkedIn Client ID';
$string['linkedin_client_id_desc'] = 'Enter the Client ID from your LinkedIn application';
$string['linkedin_client_secret'] = 'LinkedIn Client Secret';
$string['linkedin_client_secret_desc'] = 'Enter the Client Secret from your LinkedIn application';

// LinkedIn sharing
$string['share_on_linkedin'] = 'Share on LinkedIn';
$string['share_badge'] = 'Share {$a} Badge on LinkedIn';
$string['customize_message'] = 'Customize your message';
$string['customize_message_help'] = 'You can modify this message before sharing on LinkedIn.';
$string['linkedin_connect_required'] = 'To share your badge on LinkedIn, you\'ll need to connect your LinkedIn account first.';
$string['connect_linkedin'] = 'Connect LinkedIn Account';
$string['return_to_badges'] = 'Return to My Badges';

// Connection status
$string['linkedin_connected'] = 'LinkedIn account connected';
$string['linkedin_not_connected'] = 'LinkedIn account not connected';
$string['connection_status'] = 'LinkedIn Connection Status';

// Default message template
$string['default_share_message'] = 'I\'m proud to announce that I\'ve earned the {$a->badge} badge from {$a->site}! 🏆
{$a->description}';

// Success messages
$string['success:badge_shared'] = 'Your badge has been successfully shared on LinkedIn!';
$string['success:connection'] = 'Successfully connected to LinkedIn';
$string['view_on_linkedin'] = 'View on LinkedIn';

// Error messages
$string['error:badgenotearned'] = 'You have not earned this badge.';
$string['error:share_failed'] = 'Failed to share badge: {$a}';
$string['error:linkedin_api'] = 'LinkedIn API error: {$a}';
$string['error:connection'] = 'LinkedIn connection error: {$a}';
$string['error:token_missing'] = 'LinkedIn token not found';
$string['error:curl'] = 'cURL error: {$a}';
$string['error:json_parse'] = 'JSON parse error: {$a}';
$string['error:storage'] = 'Token storage error: {$a}';

// Buttons and actions
$string['try_again'] = 'Try Again';
$string['share_button'] = 'Share to LinkedIn';
$string['message_label'] = 'Your Message';
$string['cancel'] = 'Cancel';

// Admin interface
$string['manage_connections'] = 'Manage LinkedIn Connections';
$string['settings_saved'] = 'LinkedIn settings saved successfully';
$string['test_connection'] = 'Test LinkedIn Connection';

