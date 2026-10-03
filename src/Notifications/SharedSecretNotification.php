<?php

namespace Arzcode\SharedSecrets\Notifications;

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SharedSecretNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected ?SharedSecret $secret = null;

    /**
     * Only the id travels through the queue, so the payload never holds the
     * secret itself.
     */
    public function __construct(public string $secretId, public string $senderName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * A secret revoked, expired or pruned before the queue gets to it has
     * nothing left to announce.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->secret()?->isAvailable() ?? false;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $secret = $this->secret();

        $message = (new MailMessage)
            ->subject(__('shared-secrets::shared-secrets.notifications.mail.subject', ['sender' => $this->senderName]))
            ->line(__('shared-secrets::shared-secrets.notifications.mail.intro', ['sender' => $this->senderName]));

        if (! $secret instanceof SharedSecret) {
            return $message;
        }

        $message->line(trans_choice('shared-secrets::shared-secrets.notifications.mail.limits', $secret->max_views, [
            'count' => $secret->max_views,
            'date'  => $secret->expires_at->format('d/m/Y H:i:s')
        ]));

        $url = SecretUrl::for($secret);

        if ($url !== null) {
            $message->action(__('shared-secrets::shared-secrets.notifications.mail.action'), $url);
        }

        return $message->line(__('shared-secrets::shared-secrets.notifications.mail.login_required'));
    }

    protected function secret(): ?SharedSecret
    {
        return $this->secret ??= SharedSecret::query()->find($this->secretId);
    }
}
