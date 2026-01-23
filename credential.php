<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');

// Public credential page used for social previews (contains OG meta tags).
$hash = optional_param('hash', '', PARAM_ALPHANUMEXT);
if (empty($hash)) {
    http_response_code(400);
    echo 'Missing credential hash';
    exit;
}

global $DB, $CFG, $OUTPUT;

$issued = $DB->get_record('badge_issued', ['uniquehash' => $hash], '*', IGNORE_MISSING);
if (!$issued) {
    http_response_code(404);
    echo 'Credential not found';
    exit;
}

$badge = $DB->get_record('badge', ['id' => $issued->badgeid], '*', MUST_EXIST);

// Build a suitable image for social sharing: prefer a pluginfile URL to the badge image
try {
    $fs = get_file_storage();
    $ctx = !empty($badge->courseid) ? context_course::instance($badge->courseid) : context_system::instance();
    $files = $fs->get_area_files($ctx->id, 'badges', 'badgeimage', $badge->id, 'id', false);
    $imgurl = '';
    foreach ($files as $f) {
        if ($f->is_directory()) { continue; }
        $imgurl = moodle_url::make_pluginfile_url($ctx->id, 'badges', 'badgeimage', $badge->id, '/', $f->get_filename(), false)->out(false);
        break;
    }
} catch (Exception $e) {
    $imgurl = '';
}

$credential_url = $CFG->wwwroot . '/local/linkedinbadge/credential.php?hash=' . $hash;

// Output a minimal HTML page with OG meta tags for LinkedIn to scrape.
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8">';
echo '<meta property="og:title" content="' . s(format_string($badge->name)) . '" />';
echo '<meta property="og:description" content="' . s(format_text($badge->description, FORMAT_PLAIN)) . '" />';
if (!empty($imgurl)) {
    echo '<meta property="og:image" content="' . s($imgurl) . '" />';
}
echo '<meta property="og:url" content="' . s($credential_url) . '" />';
echo '<meta name="twitter:card" content="summary_large_image" />';
echo '<title>' . s(format_string($badge->name)) . '</title>';
echo '</head><body>';
echo '<h1>' . format_string($badge->name) . '</h1>';
if (!empty($imgurl)) {
    echo '<p><img src="' . s($imgurl) . '" alt="' . s(format_string($badge->name)) . '" style="max-width:300px;"/></p>';
}
echo '<p>' . format_text($badge->description, FORMAT_HTML) . '</p>';
echo '<p><a href="' . $CFG->wwwroot . '/badges/view.php?hash=' . $hash . '">View verification</a></p>';
echo '</body></html>';
