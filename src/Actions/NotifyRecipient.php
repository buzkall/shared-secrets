<?php

namespace Arzcode\SharedSecrets\Actions;

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Notifications\SharedSecretNotification;
use Arzcode\SharedSecrets\Support\Languages;
use Arzcode\SharedSecrets\Support\RecipientPanels;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Support\Users;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\Eloquent\Model;

class NotifyRecipient
{
    public function __construct(protected Dispatcher $notifications) {}

    /**
     * Tell a targeted user that a secret is waiting: always by email, and in
     * the panel bell when a panel they can access shows database
     * notifications.
     */
    public function handle(SharedSecret $secret, Model $recipient, Authenticatable $sender): void
    {
        $senderName = $sender instanceof Model ? Users::label($sender) : '';

        // The language picked by the sender wins over the recipient's preference.
        $locale = $secret->chosenLocale();

        if (config()->boolean('shared-secrets.notifications.mail', true)) {
            $notification = new SharedSecretNotification($secret->id, $senderName)->afterCommit();

            if ($locale !== null) {
                $notification->locale($locale);
            }

            $this->notifications->send($recipient, $notification);
        }

        if (! config()->boolean('shared-secrets.notifications.database', true)) {
            return;
        }

        $url = SecretUrl::for($secret);

        if ($url === null || ! RecipientPanels::hasDatabaseNotifications($recipient)) {
            return;
        }

        // The strings are resolved now, in the chosen language and its fallback.
        $notification = Languages::using($locale, fn(): Notification => Notification::make()
            ->title(__('shared-secrets::shared-secrets.notifications.database.title', ['sender' => $senderName]))
            ->body(__('shared-secrets::shared-secrets.notifications.database.body', [
                'date' => $secret->expires_at->format('d/m/Y H:i:s')
            ]))
            ->icon('heroicon-o-key')
            ->actions([
                Action::make('view')
                    ->label(__('shared-secrets::shared-secrets.notifications.database.action'))
                    ->button()
                    ->url($url)
                    ->markAsRead()
            ]));

        $notification->sendToDatabase($recipient);
    }
}
