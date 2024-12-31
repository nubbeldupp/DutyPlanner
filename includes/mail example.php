<?php
require_once 'config.php';

function sendMail($to, $subject, $message) {
    // Basic email headers
    $headers = array(
        'From: ' . SITE_NAME . ' <no-reply@example.com>',
        'Reply-To: no-reply@example.com',
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8'
    );

    // Log email attempt
    error_log(sprintf(
        "Sending email to %s with subject: %s",
        $to,
        $subject
    ));

    // Send email using PHP's mail function
    $success = mail(
        $to,
        $subject,
        $message,
        implode("\r\n", $headers)
    );

    // Log the result
    if (!$success) {
        error_log(sprintf(
            "Failed to send email to %s with subject: %s",
            $to,
            $subject
        ));
    }

    return $success;
} 