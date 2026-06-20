<?php

declare(strict_types=1);

namespace App\Modules\Marketing;

use App\Core\Controller;
use App\Core\Request;
use App\Support\Mailer;
use Throwable;

final class LandingController extends Controller
{
    public function index(Request $request): void
    {
        $contactEmail = $this->contactEmail();
        $mailTo = $contactEmail !== ''
            ? 'mailto:' . rawurlencode($contactEmail) . '?subject=' . rawurlencode('HR Platform Inquiry')
            : null;

        $this->render('marketing.landing', [
            'title' => $this->productName() . ' | HR Operations Platform',
            'productName' => $this->productName(),
            'contactEmail' => $contactEmail,
            'mailTo' => $mailTo,
            'isAuthenticated' => auth()->check(),
            'dashboardUrl' => url('/dashboard'),
            'loginUrl' => url('/login'),
        ], 'marketing');
    }

    public function submitDemoRequest(Request $request): void
    {
        $this->validateCsrf($request);

        $data = [
            'name' => trim((string) $request->input('name', '')),
            'work_email' => strtolower(trim((string) $request->input('work_email', ''))),
            'company' => trim((string) $request->input('company', '')),
            'team_size' => trim((string) $request->input('team_size', '')),
            'message' => trim((string) $request->input('message', '')),
        ];

        $this->app->session()->flash('old_input', $data);

        if ($data['name'] === '' || $data['work_email'] === '' || $data['company'] === '' || $data['team_size'] === '' || $data['message'] === '') {
            $this->app->session()->flash('error', 'Please complete all demo request fields.');
            $this->redirect('/#book-demo');
        }

        if (!filter_var($data['work_email'], FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Please enter a valid work email address.');
            $this->redirect('/#book-demo');
        }

        if (mb_strlen($data['name']) > 120 || mb_strlen($data['work_email']) > 190 || mb_strlen($data['company']) > 150 || mb_strlen($data['team_size']) > 60 || mb_strlen($data['message']) > 2000) {
            $this->app->session()->flash('error', 'One or more fields exceed the allowed length.');
            $this->redirect('/#book-demo');
        }

        $recipient = $this->contactEmail();
        if ($recipient === '') {
            $this->app->session()->flash('error', 'Demo inquiries are temporarily unavailable. Please contact us directly later.');
            $this->redirect('/#book-demo');
        }

        $mailer = new Mailer((array) config('app.mail', []));
        if (!$mailer->isEnabled()) {
            $this->app->session()->flash('error', 'Demo inquiries are temporarily unavailable. Please contact us directly later.');
            $this->redirect('/#book-demo');
        }

        try {
            $subject = sprintf('%s demo request from %s', $this->productName(), $data['company']);
            $mailer->send(
                $recipient,
                $subject,
                $this->demoRequestHtml($data, $request),
                $this->demoRequestText($data, $request)
            );
        } catch (Throwable) {
            $this->app->session()->flash('error', 'We could not submit your request right now. Please try again shortly.');
            $this->redirect('/#book-demo');
        }

        $this->app->session()->remove('_flash');
        $this->app->session()->flash('success', 'Thanks. We received your request and will reach out to schedule a walkthrough.');
        $this->redirect('/#book-demo');
    }

    private function validateCsrf(Request $request): void
    {
        if (!$this->app->csrf()->validate((string) $request->input('_token'))) {
            $this->app->session()->flash('error', 'Your session expired. Please submit the form again.');
            $this->redirect('/#book-demo');
        }
    }

    private function productName(): string
    {
        return trim((string) config('app.marketing.product_name', 'Peoplova'));
    }

    private function contactEmail(): string
    {
        return trim((string) config('app.marketing.demo_email', ''));
    }

    private function demoRequestHtml(array $data, Request $request): string
    {
        $items = [
            'Name' => e($data['name']),
            'Work email' => e($data['work_email']),
            'Company' => e($data['company']),
            'Team size' => e($data['team_size']),
            'Message' => nl2br(e($data['message'])),
            'IP address' => e((string) ($request->ip() ?? 'Unknown')),
            'User agent' => e((string) ($request->userAgent() ?? 'Unknown')),
        ];

        $rows = '';
        foreach ($items as $label => $value) {
            $rows .= '<tr>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-weight:700;width:160px;">' . e($label) . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#334155;">' . $value . '</td>'
                . '</tr>';
        }

        return '<div style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px;color:#0f172a;">'
            . '<div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">'
            . '<div style="padding:24px 28px;background:linear-gradient(135deg,#ff6b42,#ff3d33);color:#ffffff;">'
            . '<div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.82;">New demo request</div>'
            . '<h1 style="margin:8px 0 0;font-size:28px;line-height:1.1;">' . e($this->productName()) . '</h1>'
            . '</div>'
            . '<div style="padding:24px 28px;">'
            . '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#475569;">A new prospect requested a walkthrough from the public landing page.</p>'
            . '<table style="width:100%;border-collapse:collapse;">' . $rows . '</table>'
            . '</div></div></div>';
    }

    private function demoRequestText(array $data, Request $request): string
    {
        return implode(PHP_EOL, [
            $this->productName() . ' demo request',
            'Name: ' . $data['name'],
            'Work email: ' . $data['work_email'],
            'Company: ' . $data['company'],
            'Team size: ' . $data['team_size'],
            'Message: ' . $data['message'],
            'IP address: ' . ($request->ip() ?? 'Unknown'),
            'User agent: ' . ($request->userAgent() ?? 'Unknown'),
        ]);
    }
}
