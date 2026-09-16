<?php

namespace App\Modules\Settings\Mail;

use App\Models\User;
use App\Modules\Settings\Support\TenantMailConfigurationResolver;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Mail notification channel that selects an isolated tenant SMTP mailer
 * (or server fallback) per notifiable — never Config::set global SMTP.
 */
final class TenantMailChannel extends MailChannel
{
    public function __construct(
        MailFactory $mailer,
        Markdown $markdown,
        private readonly TenantMailConfigurationResolver $resolver,
        private readonly TenantMailer $tenantMailer,
    ) {
        parent::__construct($mailer, $markdown);
    }

    /**
     * @param  mixed  $notifiable
     * @return SentMessage|null
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toMail($notifiable);

        if (! $notifiable->routeNotificationFor('mail', $notification) &&
            ! $message instanceof Mailable) {
            return;
        }

        $tenantId = $notifiable instanceof User && $notifiable->tenant_id !== null
            ? (int) $notifiable->tenant_id
            : null;

        $resolved = $this->resolver->resolveForTenantId($tenantId);

        if (! $resolved->deliverable) {
            throw new \RuntimeException('Mail delivery is unavailable for this recipient.');
        }

        $mailer = $this->tenantMailer->mailerFor($resolved);

        try {
            if ($message instanceof Mailable) {
                // Pass the concrete mailer instance (not the factory) so tenant SMTP is used.
                return $message->send($mailer);
            }

            return $mailer->send(
                $this->buildView($message),
                array_merge($message->data(), $this->additionalMessageData($notification)),
                $this->messageBuilder($notifiable, $notification, $message)
            );
        } catch (Throwable $e) {
            $this->tenantMailer->logSanitizedFailure('tenant_mail_send_failed', $e, $resolved);
            throw $e;
        }
    }
}
