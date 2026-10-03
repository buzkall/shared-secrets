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
