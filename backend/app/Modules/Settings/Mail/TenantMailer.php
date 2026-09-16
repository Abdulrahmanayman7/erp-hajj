<?php

namespace App\Modules\Settings\Mail;

use App\Modules\Settings\Support\ResolvedMailConfiguration;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Builds a per-send mailer without mutating global mail config.
 * Tenant SMTP uses MailManager::build() (isolated instance, not cached by name).
 */
final class TenantMailer
{
    public function __construct(
        private readonly MailFactory $mailFactory,
    ) {}

    public function mailerFor(ResolvedMailConfiguration $config): MailerContract
    {
        if ($config->usesTenantSmtp() && $config->smtpTransport !== null) {
            $manager = $this->mailManager();

            /** @var Mailer $mailer */
            $mailer = $manager->build([
                'name' => 'tenant_smtp_ondemand',
                'transport' => 'smtp',
                'scheme' => $config->smtpTransport['scheme'],
                'host' => $config->smtpTransport['host'],
                'port' => $config->smtpTransport['port'],
                'username' => $config->smtpTransport['username'],
                'password' => $config->smtpTransport['password'],
                'timeout' => 30,
                'from' => [
                    'address' => $config->fromAddress,
                    'name' => $config->fromName,
                ],
            ]);

            return $mailer;
        }

        $name = $config->serverMailerName ?: (string) config('mail.default');

        return $this->mailFactory->mailer($name);
    }

    /**
     * @return array{ok: bool, message: string, error_code: ?string}
     */
    public function sendTestHtml(
        ResolvedMailConfiguration $config,
        string $toEmail,
        string $subject,
        string $htmlBody,
    ): array {
        if (! $config->deliverable) {
            return [
                'ok' => false,
                'message' => 'إرسال البريد غير مفعّل. أعد إعداد SMTP أو فعّل بريد الخادم.',
                'error_code' => 'MAIL_UNAVAILABLE',
            ];
        }

        try {
            $mailer = $this->mailerFor($config);
            $fromAddress = $config->fromAddress;
            $fromName = $config->fromName;

            $mailer->html($htmlBody, function ($message) use ($toEmail, $subject, $fromAddress, $fromName): void {
                $message->to($toEmail)->subject($subject);
                if ($fromAddress !== '') {
                    $message->from($fromAddress, $fromName !== '' ? $fromName : null);
                }
            });

            return [
                'ok' => true,
                'message' => 'تم إرسال رسالة الاختبار بنجاح.',
                'error_code' => null,
            ];
        } catch (Throwable $e) {
            $this->logSanitizedFailure('tenant_mail_test_failed', $e, $config);

            return [
                'ok' => false,
                'message' => $this->safeUserMessage($e),
                'error_code' => 'MAIL_SEND_FAILED',
            ];
        }
    }

    public function safeUserMessage(Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, 'authenticat')
            || str_contains($raw, '535')
            || str_contains($raw, 'username')
            || str_contains($raw, 'password')
            || str_contains($raw, 'login')) {
            return 'تعذر تسجيل الدخول إلى خادم البريد. تحقق من اسم المستخدم وكلمة المرور.';
        }

        if (str_contains($raw, 'sender')
            || str_contains($raw, 'from address')
            || str_contains($raw, '553')
            || str_contains($raw, '550')
            || str_contains($raw, 'not permitted')
            || str_contains($raw, 'not allowed')) {
            return 'رفض خادم البريد عنوان المرسل المحدد.';
        }

        if ($e instanceof TransportExceptionInterface
            || str_contains($raw, 'connection')
            || str_contains($raw, 'timed out')
            || str_contains($raw, 'could not connect')
            || str_contains($raw, 'stream_socket')) {
            return 'تعذر الاتصال بخادم SMTP. تحقق من اسم الخادم والمنفذ.';
        }

        return 'تعذر إرسال البريد. تحقق من إعدادات SMTP ثم أعد المحاولة.';
    }

    public function logSanitizedFailure(string $event, Throwable $e, ResolvedMailConfiguration $config): void
    {
        Log::warning($event, [
            'source' => $config->source,
            'status' => $config->status,
            'host' => $config->smtpTransport['host'] ?? null,
            'port' => $config->smtpTransport['port'] ?? null,
            'exception_class' => $e::class,
            'exception_safe' => $this->sanitizeExceptionMessage($e->getMessage()),
        ]);
    }

    private function sanitizeExceptionMessage(string $message): string
    {
        // Strip likely credential material from DSN-like strings.
        $message = preg_replace('#://([^:\s]+):([^@\s]+)@#', '://***:***@', $message) ?? $message;
        $message = preg_replace('/password[=:]\s*\S+/i', 'password=***', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }

    private function mailManager(): MailManager
    {
        $factory = $this->mailFactory;
        if ($factory instanceof MailManager) {
            return $factory;
        }

        return app(MailManager::class);
    }
}
