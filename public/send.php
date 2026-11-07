<?php
/**
 * Nigeria Health Expo - form handler for cPanel / PHP hosting.
 *
 * Receives the visitor-registration and exhibitor-enquiry forms from the website, then:
 *   1. saves a backup copy on the server (outside public_html, so it is not downloadable),
 *   2. emails the organisers,
 *   3. emails the person who submitted an automatic confirmation.
 */

// ---------- Settings (edit here) ----------
$NOTIFY_TO             = 'contractform@nigeriahealthexpo.com'; // where form submissions arrive
$FROM_EMAIL            = 'contractform@nigeriahealthexpo.com'; // must be a mailbox on this domain
$FROM_NAME             = 'Nigeria Health Expo';
$REPLY_TO              = 'nigeriahealthexpo@gmail.com';        // where replies to confirmations go
$EVENT_NAME            = 'Nigeria Health Expo 2027';
$MAX_PER_IP_PER_HOUR   = 10;
$MAX_PER_EMAIL_PER_DAY = 3;
// -------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($ok, $error = '', $code = 200)
{
    http_response_code($code);
    echo json_encode($ok ? array('ok' => true) : array('ok' => false, 'error' => $error));
    exit;
}

// Strip control characters / line breaks (blocks email-header injection) and limit length.
function clean($v, $max)
{
    if (!is_string($v)) {
        return '';
    }
    $v = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $v);
    $v = trim(preg_replace('/ {2,}/', ' ', $v));
    if (function_exists('mb_substr')) {
        return mb_substr($v, 0, $max, 'UTF-8');
    }
    return substr($v, 0, $max);
}

// Returns false when $key has already been used $limit times in the last $window seconds.
function rate_ok($dir, $key, $limit, $window)
{
    $file = $dir . '/rate-' . md5($key) . '.json';
    $now = time();
    $recent = array();
    if (is_file($file)) {
        $arr = json_decode((string) @file_get_contents($file), true);
        if (is_array($arr)) {
            foreach ($arr as $t) {
                if (is_int($t) && $t > $now - $window) {
                    $recent[] = $t;
                }
            }
        }
    }
    if (count($recent) >= $limit) {
        return false;
    }
    $recent[] = $now;
    @file_put_contents($file, json_encode($recent), LOCK_EX);
    return true;
}

function mime_subject($text)
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function mail_headers($fromName, $fromEmail, $replyTo)
{
    return 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n"
        . 'Reply-To: ' . $replyTo . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "X-Auto-Response-Suppress: OOF, AutoReply\r\n";
}

// ---------- Request checks ----------
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.', 405);
}

$host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '';

// Only accept submissions that come from this website.
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    if (!$originHost || strcasecmp($originHost, $host) !== 0) {
        respond(false, 'Invalid request.', 403);
    }
}

// ---------- Form definitions: field => array(label, required, max length) ----------
$FORMS = array(
    'attend' => array(
        'label'  => 'Visitor registration',
        'fields' => array(
            'name'         => array('Full name', true, 120),
            'email'        => array('Work email', true, 160),
            'phone'        => array('Phone', true, 40),
            'organisation' => array('Organisation', false, 160),
            'role'         => array('Role', true, 80),
        ),
    ),
    'exhibit' => array(
        'label'  => 'Exhibitor enquiry',
        'fields' => array(
            'first_name'        => array('First name', true, 80),
            'last_name'         => array('Last name', true, 80),
            'job_title'         => array('Job title', true, 120),
            'company'           => array('Company', true, 160),
            'website'           => array('Company website', false, 200),
            'mobile'            => array('Mobile', true, 40),
            'email'             => array('Email', true, 160),
            'country'           => array('Country', true, 80),
            'business_category' => array('Business category', true, 120),
            'heard_about_us'    => array('Heard about us via', true, 80),
        ),
    ),
);

$kind = isset($_POST['form']) && is_string($_POST['form']) ? $_POST['form'] : '';
if (!isset($FORMS[$kind])) {
    respond(false, 'Invalid request.', 400);
}

// Hidden "honeypot" field: real visitors never fill it in, bots do. Pretend it worked.
if (!empty($_POST['hp'])) {
    respond(true);
}

// ---------- Validate ----------
$def  = $FORMS[$kind];
$data = array();
foreach ($def['fields'] as $key => $f) {
    $val = clean(isset($_POST[$key]) ? $_POST[$key] : '', $f[2]);
    if ($f[1] && $val === '') {
        respond(false, 'Please fill in: ' . $f[0] . '.', 422);
    }
    $data[$key] = $val;
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}

if (isset($data['website']) && $data['website'] !== '') {
    if (!preg_match('~^https?://~i', $data['website'])) {
        $data['website'] = 'https://' . $data['website'];
    }
    if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
        respond(false, 'Please check the company website address.', 422);
    }
}

if ($kind === 'attend') {
    $fullName = $data['name'];
    $parts    = explode(' ', $fullName);
    $first    = $parts[0];
} else {
    $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
    $first    = $data['first_name'];
}

// ---------- Storage folder (outside public_html) + abuse limits ----------
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '' ? $_SERVER['DOCUMENT_ROOT'] : __DIR__;
$dir     = dirname($docRoot) . '/nhe-form-data';
if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
}
if (!is_dir($dir) || !is_writable($dir)) {
    $dir = sys_get_temp_dir();
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
if (!rate_ok($dir, 'ip-' . $ip, $MAX_PER_IP_PER_HOUR, 3600)
    || !rate_ok($dir, 'em-' . strtolower($data['email']), $MAX_PER_EMAIL_PER_DAY, 86400)) {
    respond(false, 'Too many submissions. Please try again later.', 429);
}

// ---------- 1. Backup copy ----------
$entry = array('time' => gmdate('c'), 'form' => $kind, 'ip' => $ip, 'data' => $data);
@file_put_contents($dir . '/submissions.jsonl', json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);

// Local testing (php -S localhost): keep the backup but do not send real emails.
if (in_array(strtolower($host), array('localhost', '127.0.0.1', '[::1]'), true)) {
    respond(true);
}

// ---------- 2. Email to the organisers ----------
$lines = array();
foreach ($def['fields'] as $key => $f) {
    $lines[] = str_pad($f[0] . ':', 24) . ($data[$key] === '' ? '-' : $data[$key]);
}
$body = $def['label'] . " received from the website\n\n"
    . implode("\n", $lines)
    . "\n\nSubmitted: " . gmdate('Y-m-d H:i') . " UTC\nIP address: " . $ip . "\n"
    . "Reply to this email to reach the sender directly.\n";

$sent = @mail(
    $NOTIFY_TO,
    mime_subject('[' . $EVENT_NAME . '] ' . $def['label'] . ' - ' . $fullName),
    $body,
    mail_headers($FROM_NAME, $FROM_EMAIL, $data['email']),
    '-f' . $FROM_EMAIL
);

if (!$sent) {
    respond(false, 'We could not send your details just now.', 500);
}

// ---------- 3. Automatic confirmation to the sender ----------
if ($kind === 'attend') {
    $subject = 'Your registration for ' . $EVENT_NAME;
    $text    = "Hello " . $first . ",\n\n"
        . "Thank you for registering for " . $EVENT_NAME . ". We have received your details and a member of the team will be in touch with you shortly.\n\n"
        . "Visitor registration is free.\n";
} else {
    $subject = 'Your exhibitor enquiry for ' . $EVENT_NAME;
    $text    = "Hello " . $first . ",\n\n"
        . "Thank you for your interest in exhibiting at " . $EVENT_NAME . ". We have received your enquiry and a member of the team will be in touch with you within 24 hours.\n";
}
$text .= "\nIf you have any questions, reply to this email or write to " . $REPLY_TO . ".\n\n"
    . $EVENT_NAME . "\nPowered by HFN & GHPR\n\n"
    . "If you did not make this request, you can safely ignore this email.\n";

@mail(
    $data['email'],
    mime_subject($subject),
    $text,
    mail_headers($FROM_NAME, $FROM_EMAIL, $REPLY_TO),
    '-f' . $FROM_EMAIL
);

respond(true);
