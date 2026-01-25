<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');

// Public credential page used for social previews (contains OG meta tags).
$hash = optional_param('hash', '', PARAM_ALPHANUMEXT);
$v = optional_param('v', '', PARAM_ALPHANUMEXT);
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

// Output a public HTML page with OG meta tags for LinkedIn to scrape.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300, s-maxage=300');
header('Pragma: public');

// Prepare OG image (cache-busted)
$ogimage = $CFG->wwwroot . '/local/linkedinbadge/ogimage.php?hash=' . $hash;
if (!empty($v)) {
    $ogimage .= '&v=' . rawurlencode($v);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:title" content="<?php echo s(format_string($badge->name)); ?>" />
    <meta property="og:description" content="<?php echo s(format_text($badge->description, FORMAT_PLAIN)); ?>" />
    <meta property="og:image" content="<?php echo s($ogimage); ?>" />
    <meta property="og:image:secure_url" content="<?php echo s($ogimage); ?>" />
    <meta property="og:image:url" content="<?php echo s($ogimage); ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="<?php echo s(format_string($badge->name)); ?>" />
    <meta property="og:url" content="<?php echo s($credential_url); ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="<?php echo s($ogimage); ?>" />
    <link rel="image_src" href="<?php echo s($ogimage); ?>" />
    <title><?php echo s(format_string($badge->name)); ?></title>
    <style>
        :root{--card-bg:#ffffff;--muted:#6c757d;--accent:#2b6fb3}
        html,body{height:100%;margin:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f9;color:#222}
        .container{max-width:980px;margin:28px auto;padding:18px}
        .card{background:var(--card-bg);border-radius:12px;box-shadow:0 6px 18px rgba(30,45,60,0.08);overflow:hidden}
        .card-body{display:flex;flex-wrap:wrap;align-items:center;padding:28px}
        .badge-preview{flex:0 0 420px;display:flex;align-items:center;justify-content:center;padding:18px}
        .badge-preview img{max-width:100%;height:auto;border-radius:6px;background:#fff}
        .meta{flex:1 1 380px;padding:18px}
        .title{font-size:22px;font-weight:700;margin:0 0 8px}
        .issuer{color:var(--muted);margin:0 0 12px}
        .desc{margin:14px 0;color:#333}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;border:0;background:var(--accent);color:white;text-decoration:none;font-weight:600}
        .btn.secondary{background:#e9eef6;color:#123}
        .small{font-size:13px;color:var(--muted)}
        .info-row{display:flex;gap:12px;align-items:center;margin-top:12px}
        @media(max-width:820px){.card-body{flex-direction:column}.badge-preview{flex-basis:100%}.meta{flex-basis:100%}}
    </style>
    <script>
        function copyLink() {
            const url = document.getElementById('cred-url').value;
            navigator.clipboard.writeText(url).then(()=>{
                const b = document.getElementById('copyBtn'); b.innerText='Copied'; setTimeout(()=>b.innerText='Copy link',1500);
            });
        }
        function shareLinkedIn(){
            const url = encodeURIComponent(document.getElementById('cred-url').value);
            window.open('https://www.linkedin.com/sharing/share-offsite/?url='+url, '_blank');
        }
    </script>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-body">
            <div class="badge-preview">
                <img src="<?php echo s($ogimage); ?>" alt="<?php echo s(format_string($badge->name)); ?>">
            </div>
            <div class="meta">
                <h1 class="title"><?php echo format_string($badge->name); ?></h1>
                <div class="issuer small">Issued by: <strong><?php echo s($SITE->fullname); ?></strong></div>
                <div class="desc"><?php echo format_text($badge->description, FORMAT_HTML); ?></div>

                <div class="info-row">
                    <input id="cred-url" type="text" readonly value="<?php echo s($credential_url); ?>" style="flex:1;padding:8px;border-radius:6px;border:1px solid #ddd;background:#fafafa" />
                    <button id="copyBtn" class="btn secondary" onclick="copyLink()">Copy link</button>
                </div>

                <div class="actions" style="margin-top:14px">
                    <a class="btn" href="<?php echo $CFG->wwwroot . '/badges/badge.php?hash=' . $hash; ?>" target="_blank">View verification</a>
                    <button class="btn secondary" onclick="shareLinkedIn()">Share on LinkedIn</button>
                    <a class="btn secondary" href="mailto:?subject=I earned a badge&body=<?php echo rawurlencode($credential_url); ?>">Email</a>
                </div>
                <div class="small" style="margin-top:12px">Badge image is optimized for social sharing (1200×630). If you need changes, contact your site administrator.</div>
            </div>
        </div>
    </div>
    <script type="application/ld+json">
    <?php echo json_encode([
        "@context" => "https://schema.org",
        "@type" => "CreativeWork",
        "name" => format_string($badge->name),
        "description" => format_text($badge->description, FORMAT_PLAIN),
        "image" => $ogimage,
        "url" => $credential_url
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>
    </script>
</div>
</body>
</html>

<?php
