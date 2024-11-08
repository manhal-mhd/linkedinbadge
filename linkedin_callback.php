<?php
// '->dirroot . '/local/linkedinbadge/linkedin_callback.php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/linkedinbadge/lib.php');
require_login();

global $SESSION;

try {
    // Log all incoming parameters
    \local_linkedinbadge\logger::log('LinkedIn Callback Received', [
        'GET' => $_GET,
        'Session State' => $SESSION->linkedin_state ?? 'Not Set'
    ]);

    // Check for LinkedIn errors
    if (isset($_GET['error'])) {
        \local_linkedinbadge\logger::log('LinkedIn Error Received', [
            'error' => $_GET['error'],
            'description' => $_GET['error_description'] ?? 'No description'
        ]);
        throw new moodle_exception("LinkedIn Error: {$_GET['error']} - {$_GET['error_description']}");
    }

    // Verify authorization code
    if (!isset($_GET['code'])) {
        \local_linkedinbadge\logger::log('No Authorization Code');
        throw new moodle_exception('No authorization code received');
    }

    // Verify state parameter
    if (!isset($_GET['state']) || !isset($SESSION->linkedin_state)) {
        \local_linkedinbadge\logger::log('State Parameter Issue', [
            'received_state' => $_GET['state'] ?? 'Not provided',
            'session_state' => $SESSION->linkedin_state ?? 'Not found'
        ]);
        throw new moodle_exception('Invalid state parameter');
    }

    // Exchange code for token
    $oauth = new \local_linkedinbadge\linkedin_oauth();
    $result = $oauth->handle_callback($_GET['code']);

    if ($result) {
        \local_linkedinbadge\logger::log('LinkedIn Connection Successful');
        redirect(
            new moodle_url('/local/linkedinbadge/your_desired_page.php'), // Update this line
            get_string('success:connection', 'local_linkedinbadge'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        \local_linkedinbadge\logger::log('Token Exchange Failed');
        throw new moodle_exception('Failed to obtain access token');
    }

} catch (moodle_exception $e) {
    \local_linkedinbadge\logger::log('Callback Error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    redirect(
        new moodle_url('/local/linkedinbadge/your_desired_page.php'), // Update this line
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
} catch (Exception $e) {
    \local_linkedinbadge\logger::log('Unexpected Error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    redirect(
        new moodle_url('/local/linkedinbadge/your_desired_page.php'), // Update this line
        get_string('error:unexpected', 'local_linkedinbadge', $e->getMessage()),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

