<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Render a global HTML email template wrapping the given content.
 */
function render_email_template(string $subject, string $contentHtml): string {
    $appName = APP_NAME;
    $year = date('Y');
    $baseUrl = trim((string) (defined('BASE_URL') ? BASE_URL : '' ));
    $logoAlt = defined('APP_LOGO_ALT') ? APP_LOGO_ALT : $appName;
    $logoPath = defined('APP_LOGO_PATH') ? APP_LOGO_PATH : '';
    $logoSrc = '';
    if (!empty($baseUrl) && !empty($logoPath)) {
        $publicLogoPath = __DIR__ . '/../public/' . ltrim($logoPath, '/');
        if (is_file($publicLogoPath)) {
            $logoSrc = rtrim($baseUrl, '/') . '/' . ltrim($logoPath, '/');
        }
    }

    $headerBrand = !empty($logoSrc)
        ? '<img src="' . htmlspecialchars($logoSrc) . '" alt="' . htmlspecialchars($logoAlt) . '" style="height:28px; width:auto; display:block;" />'
        : '<span style="font-weight:600; font-size:18px; color:#111827;">' . htmlspecialchars($appName) . '</span>';

    $homeLink = !empty($baseUrl)
        ? '<a href="' . htmlspecialchars($baseUrl) . '" style="color:#059669; text-decoration:none;">' . htmlspecialchars(parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl) . '</a>'
        : htmlspecialchars($appName);

    return '<!DOCTYPE html>'
        . '<html lang="de">'
        . '<head>'
        . '<meta charset="UTF-8" />'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0" />'
        . '<title>' . htmlspecialchars($appName) . ' – ' . htmlspecialchars($subject) . '</title>'
        . '<style>body{margin:0;padding:0;background:#f5f7fb;color:#111827;-webkit-text-size-adjust:none;-ms-text-size-adjust:none}a{color:#059669}p{margin:0 0 12px 0}</style>'
        . '</head>'
        . '<body>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f5f7fb; padding:24px;">'
                . '<tr><td align="center">'
                    . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">'
                        . '<tr>'
                            . '<td style="padding:16px 24px; background:#ffffff; border-bottom:1px solid #e5e7eb;text-align:center;">'
                                . '<div style="display:block;">' . $headerBrand . '</div>'
                            . '</td>'
                        . '</tr>'
                        . '<tr>'
                            . '<td style="padding:24px; line-height:1.6; font-size:16px;">'
                                . $contentHtml
                            . '</td>'
                        . '</tr>'
                        . '<tr>'
                            . '<td style="padding:16px 24px; background:#f9fafb; color:#6b7280; font-size:12px; border-top:1px solid #e5e7eb;">'
                                . '<p style="margin:0;">Diese E-Mail wurde automatisch von ' . htmlspecialchars($appName) . ' gesendet.</p>'
                                . (!empty($baseUrl) ? '<p style="margin:4px 0 0 0;">Besuchen Sie uns: ' . $homeLink . '</p>' : '')
                            . '</td>'
                        . '</tr>'
                    . '</table>'
                    . '<div style="text-align:center; margin-top:12px; font-size:12px; color:#9ca3af;">© ' . htmlspecialchars($year) . ' ' . htmlspecialchars($appName) . '</div>'
                . '</td></tr>'
            . '</table>'
        . '</body>'
        . '</html>';
}

function send_mail(string $to, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        //Recipients
        $mail->SMTPDebug = 0; // Enable verbose debug output for troubleshooting
        $mail->setFrom(MAIL_FROM, APP_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = render_email_template($subject, $htmlBody);
        $mail->AltBody = html_entity_decode(trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody))), ENT_QUOTES, 'UTF-8');
        $mail->CharSet = 'UTF-8';

        $mail->send();
        error_log("Email sent successfully to: $to");
        return true;
    } catch (Exception $e) {
        error_log("Failed to send email to $to. Mailer Error: " . $e->getMessage());
        return false;
    }
}


