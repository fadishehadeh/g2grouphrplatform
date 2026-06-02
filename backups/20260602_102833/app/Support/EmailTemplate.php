<?php

declare(strict_types=1);

namespace App\Support;

final class EmailTemplate
{
    public static function otp(string $name, string $code, string $context = 'HR System', ?string $logoUrl = null): string
    {
        $safeName = self::esc($name);
        $safeCode = self::esc($code);
        $safeContext = self::esc($context);

        $content = '<p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">Hi <strong style="color:#111827;">' . $safeName . '</strong>,</p>'
            . '<p style="margin:0 0 22px;color:#334155;font-size:15px;line-height:1.6;">Use this verification code to complete your sign-in to ' . $safeContext . '. It expires in <strong style="color:#111827;">10 minutes</strong>.</p>'
            . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:24px 18px;text-align:center;margin:22px 0;">'
            . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:34px;font-weight:800;letter-spacing:10px;color:#111827;">' . $safeCode . '</div>'
            . '</div>'
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">If you did not request this code, contact your system administrator immediately.</p>';

        return self::layout('Login Verification', 'Your secure sign-in code', $content, [
            'logoUrl' => $logoUrl,
            'badge' => 'Security',
        ]);
    }

    public static function accessInvitation(string $name, string $email, string $setPasswordLink, string $expiresText, ?string $logoUrl = null): string
    {
        $content = '<p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.6;">Hello <strong style="color:#111827;">' . self::esc($name) . '</strong>,</p>'
            . '<p style="margin:0 0 18px;color:#334155;font-size:15px;line-height:1.6;">Your employee account has been created. Set your password to access the HR Management System.</p>'
            . self::keyValue('Login email', $email)
            . self::button($setPasswordLink, 'Set My Password')
            . '<p style="margin:18px 0 0;color:#64748b;font-size:13px;line-height:1.6;">This link expires in ' . self::esc($expiresText) . '. If you did not expect this invitation, you can ignore this email.</p>';

        return self::layout('Welcome to ' . Branding::appName(), 'Set up your employee account', $content, [
            'logoUrl' => $logoUrl,
            'badge' => 'Account access',
        ]);
    }

    public static function passwordReset(string $resetLink, string $expiresAt, ?string $logoUrl = null): string
    {
        $content = '<p style="margin:0 0 18px;color:#334155;font-size:15px;line-height:1.6;">A password reset was requested for your account.</p>'
            . self::button($resetLink, 'Reset Your Password')
            . '<p style="margin:18px 0 0;color:#64748b;font-size:13px;line-height:1.6;">This link expires at <strong style="color:#111827;">' . self::esc($expiresAt) . '</strong>. If you did not request this, you can safely ignore this message.</p>';

        return self::layout('Password Reset', 'Reset your account password', $content, [
            'logoUrl' => $logoUrl,
            'badge' => 'Security',
        ]);
    }

    public static function notification(string $title, string $bodyHtml, ?string $actionUrl = null, ?string $actionLabel = null, ?string $logoUrl = null): string
    {
        if (str_contains($bodyHtml, 'data-hr-email-template="1"')) {
            return $bodyHtml;
        }

        $content = '<div style="color:#334155;font-size:15px;line-height:1.6;">' . $bodyHtml . '</div>';

        if ($actionUrl !== null && $actionUrl !== '') {
            $content .= self::button($actionUrl, $actionLabel ?: 'Open in HR System');
        }

        return self::layout($title, 'New HR notification', $content, [
            'logoUrl' => $logoUrl,
            'badge' => 'Notification',
        ]);
    }

    private static function layout(string $title, string $preheader, string $contentHtml, array $options = []): string
    {
        $logoUrl = (string) ($options['logoUrl'] ?? Branding::defaultLogoUrl());
        $badge = (string) ($options['badge'] ?? 'HR');
        $appName = Branding::appName();
        $brand = Branding::brandColor();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body data-hr-email-template="1" style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . self::esc($preheader) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:28px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,.08);">'
            . '<tr><td style="height:5px;background:' . $brand . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:26px 30px 18px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="vertical-align:middle;"><img src="' . self::esc($logoUrl) . '" alt="' . self::esc($appName) . '" style="max-height:54px;max-width:156px;display:block;border:0;"></td>'
            . '<td align="right" style="vertical-align:middle;"><span style="display:inline-block;border:1px solid #fecaca;background:#fff5f5;color:#b91c1c;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;">' . self::esc($badge) . '</span></td>'
            . '</tr></table>'
            . '<h1 style="margin:24px 0 8px;color:#111827;font-size:26px;line-height:1.18;font-weight:800;letter-spacing:0;">' . self::esc($title) . '</h1>'
            . '<p style="margin:0;color:#64748b;font-size:14px;line-height:1.5;">' . self::esc($preheader) . '</p>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 30px 30px;">' . $contentHtml . '</td></tr>'
            . '<tr><td style="padding:18px 30px;background:#f8fafc;border-top:1px solid #e5e7eb;color:#64748b;font-size:12px;line-height:1.5;">'
            . 'This is an automated message from ' . self::esc($appName) . '. Please do not share security codes or password links.'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function button(string $url, string $label): string
    {
        return '<p style="margin:24px 0 0;"><a href="' . self::esc($url) . '" style="display:inline-block;background:' . Branding::brandColor() . ';color:#ffffff;text-decoration:none;border-radius:8px;padding:12px 22px;font-weight:800;font-size:14px;box-shadow:0 8px 16px rgba(255,61,51,.22);">' . self::esc($label) . '</a></p>';
    }

    private static function keyValue(string $label, string $value): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:18px 0;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">'
            . '<tr><td style="padding:12px 14px;color:#64748b;font-size:13px;width:130px;">' . self::esc($label) . '</td>'
            . '<td style="padding:12px 14px;color:#111827;font-size:14px;font-weight:700;">' . self::esc($value) . '</td></tr></table>';
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
