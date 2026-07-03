<?php
/**
 * RTLConverter — Waitlist form mailer
 * Receives POST data from the waitlist form and forwards it to the admin
 * via Gmail SMTP using PHPMailer.
 *
 * ───────────────────────────────────────────────────────────────
 *  SETUP
 * ───────────────────────────────────────────────────────────────
 *  1. Install PHPMailer:
 *       composer require phpmailer/phpmailer
 *
 *  2. Fill in your credentials below (or move them to a .env file
 *     and load with vlucas/phpdotenv).
 *
 *  3. Make sure your Gmail account has:
 *       – 2-Step Verification enabled
 *       – An "App Password" created for "Mail" (16-char code)
 *     Google guide: https://support.google.com/accounts/answer/185833
 * ───────────────────────────────────────────────────────────────
 */

// ─── CONFIGURATION ────────────────────────────────────────────
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);                       // 587 = STARTTLS, 465 = SSL
define('SMTP_USERNAME', 'matancohen365@gmail.com');    // ← your Gmail address
define('SMTP_PASSWORD', 'dvle rhtp mwvs ibdn');     // ← your 16-char App Password
define('ADMIN_EMAIL',   SMTP_USERNAME);             // same address receives the mail
define('SITE_NAME',     'RTLConverter');
// ──────────────────────────────────────────────────────────────

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Sanitise & validate input ────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$name  = clean($_POST['fullName'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$site  = clean($_POST['site']     ?? '');
$stack = clean($_POST['stack']    ?? '');

if (!$email) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'A valid email address is required.']);
    exit;
}

// ─── Load PHPMailer ───────────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true); // true = throw Exceptions

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // use ENCRYPTION_SMTPS for port 465
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // From / To
    $mail->setFrom(SMTP_USERNAME, SITE_NAME . ' Waitlist');
    $mail->addAddress(ADMIN_EMAIL, 'Admin');
    $mail->addReplyTo($email, $name ?: 'Subscriber');

    // Content
    $mail->isHTML(true);
    $mail->Subject = '[' . SITE_NAME . '] New waitlist signup: ' . ($name ?: $email);

    $stackRow = $stack ? "<tr><td style='padding:6px 12px;color:#9BA1B0;width:160px;'>Stack</td><td style='padding:6px 12px;'>" . $stack . "</td></tr>" : '';
    $siteRow  = $site  ? "<tr><td style='padding:6px 12px;color:#9BA1B0;'>Company / Site</td><td style='padding:6px 12px;'>" . $site  . "</td></tr>" : '';

    $mail->Body = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#0B0D12;font-family:sans-serif;color:#EDEAE2;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='max-width:600px;margin:40px auto;'>
    <tr>
      <td style='background:#12151D;border:1px solid #232838;border-radius:16px;padding:32px;'>
        <p style='margin:0 0 8px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#5FC4B8;font-weight:600;'>RTLConverter</p>
        <h1 style='margin:0 0 24px;font-size:22px;font-weight:600;'>New waitlist signup 🎉</h1>
        <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #232838;border-radius:10px;overflow:hidden;font-size:14px;'>
          <tr style='background:#0B0D12;'>
            <td style='padding:6px 12px;color:#9BA1B0;width:160px;'>Name</td>
            <td style='padding:6px 12px;'>" . ($name ?: '<em style=\"color:#5a6072\">not provided</em>') . "</td>
          </tr>
          <tr>
            <td style='padding:6px 12px;color:#9BA1B0;'>Email</td>
            <td style='padding:6px 12px;'><a href='mailto:" . $email . "' style='color:#5FC4B8;text-decoration:none;'>" . $email . "</a></td>
          </tr>
          " . $siteRow . "
          " . $stackRow . "
        </table>
        <p style='margin:24px 0 0;font-size:12px;color:#5a6072;'>Submitted at " . gmdate('Y-m-d H:i') . " UTC via the RTLConverter waitlist form.</p>
      </td>
    </tr>
  </table>
</body>
</html>";

    // Plain-text fallback
    $mail->AltBody = implode("\n", array_filter([
        'New waitlist signup for ' . SITE_NAME,
        'Name:  ' . ($name  ?: '(not provided)'),
        'Email: ' . $email,
        $site  ? 'Site:  ' . $site  : '',
        $stack ? 'Stack: ' . $stack : '',
        '',
        'Submitted at ' . gmdate('Y-m-d H:i') . ' UTC',
    ]));

    $mail->send();

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    // Log the real error server-side; never expose SMTP details to the browser
    error_log('[RTLConverter mailer] ' . $mail->ErrorInfo);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Could not send email. Please try again later.']);
}
