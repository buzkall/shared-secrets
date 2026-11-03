<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | The model secrets are sent from and targeted to. When null, the model of
    | the default auth provider is used. The title attribute labels a user in
    | the recipient select and the table; the search columns are the ones the
    | recipient select looks into.
    |
    */

    'user_model'           => null,
    'user_title_attribute' => 'name',
    'user_search_columns'  => ['name', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */

    'table_names' => [
        'secrets' => 'shared_secrets',
        'events'  => 'shared_secret_events'
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | The lifetimes a sender can pick from, in minutes, and the one selected
    | by default. A secret is wiped when it expires or runs out of views,
    | whichever comes first.
    |
    */

    'expiry' => [
        'options' => [60, 720, 1440, 4320, 10080, 20160, 43200],
        'default' => 10080
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    */

    'max_views' => [
        'max'     => 10,
        'default' => 3
    ],

    'content_max_length' => 10000,

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | When the site describes its languages with a backed enum or an Eloquent
    | model, set its class as the source and the sender picks the language
    | the reader's page and the recipient's notifications are shown in. The
    | values are locales (en, es, pt_BR…). An enum's label is its getLabel()
    | when it implements Filament's HasLabel, its case name otherwise; a
    | model reads the value and label attributes below. Null hides the
    | selector and secrets are shown in the application locale.
    |
    | A regional variant (ba, pt_BR…) usually only translates what differs
    | from its base language. Map it to the locale its missing strings come
    | from; unmapped locales keep the application's fallback locale.
    |
    | When each language is served from its own domain, hosts maps a locale
    | to its host (example.cat, or https://example.cat) and the link is built
    | on it. A regional variant uses its fallback's host. Give it either the
    | map or a static method returning it, as [App\Enums\Language::class,
    | 'hosts'], called once per request. Null, a locale without a host, a
    | value with a path, or a panel bound to its own domains keeps the
    | application URL. Every host listed must serve the panel and its login.
    |
    */

    'languages' => [
        'source'          => null,
        'value_attribute' => 'code',
        'label_attribute' => 'name',
        'fallbacks'       => [],
        'hosts'           => null
    ],

    /*
    |--------------------------------------------------------------------------
    | Passphrase
    |--------------------------------------------------------------------------
    |
    | Wrong passphrases are throttled per secret and IP address. Once a secret
    | accumulates max_failures wrong attempts within lockout_minutes, from any
    | address, it refuses every passphrase until those attempts are older than
    | that. The secret is paused, never wiped, so its link is not enough to
    | destroy it.
    |
    */

    'passphrase' => [
        'attempts_per_minute' => 5,
        'max_failures'        => 10,
        'lockout_minutes'     => 60
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | The shared-secrets:prune command wipes expired secrets and deletes the
    | rows that were closed more than prune_after_days ago. Set it to null to
    | keep the audit rows forever. The command is scheduled hourly unless the
    | schedule is disabled here.
    |
    */

    'prune_after_days' => 30,

    'schedule' => [
        'enabled' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Channels used to tell a targeted user that a secret is waiting. The
    | database notification is only sent when a panel that user can access has
    | database notifications enabled.
    |
    */

    'notifications' => [
        'mail'     => true,
        'database' => true
    ]

];
