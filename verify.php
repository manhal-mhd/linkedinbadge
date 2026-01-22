<?php
require_once('../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/linkedinbadge/verify.php'));
$PAGE->set_title('Plugin Verification');

echo $OUTPUT->header();

// Check component settings
$settings = get_config('local_linkedinbadge');
echo "<h3>Plugin Settings</h3>";
echo "<pre>";
print_r($settings);
echo "</pre>";

// Test class autoloading
try {
    $oauth = new \local_linkedinbadge\linkedin_oauth();
    echo "<div class='alert alert-success'>Class autoloading successful</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Class autoloading failed: " . $e->getMessage() . "</div>";
}

// Check paths
echo "<h3>Path Configuration</h3>";
echo "<pre>";
echo "Plugin directory: " . $CFG->dirroot . '/local/linkedinbadge' . "\n";
echo "Web root: " . $CFG->wwwroot . '/local/linkedinbadge' . "\n";
echo "</pre>";

echo $OUTPUT->footer();
