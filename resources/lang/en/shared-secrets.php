<?php

return [
    'navigation' => [
        'label' => 'Shared secrets'
    ],

    'page' => [
        'title'   => 'Shared secrets',
        'created' => 'Secret created'
    ],

    'form' => [
        'heading'     => 'New secret',
        'description' => 'The content is encrypted, shown to the reader through a link and wiped once it expires or runs out of views, whichever comes first.',
        'submit'      => 'Push it',

        'content' => [
            'label'       => 'Secret',
            'placeholder' => 'Enter the password or text to share…'
        ],

        'expires_in' => [
            'label'   => 'Expires after',
            'minutes' => ':count minute|:count minutes',
            'hours'   => ':count hour|:count hours',
            'days'    => ':count day|:count days'
        ],

        'max_views' => [
            'label'  => 'Maximum views',
            'helper' => 'How many times the secret can be revealed.'
        ],

        'passphrase' => [
            'label'  => 'Passphrase',
            'helper' => 'Optional. The reader has to type it to see the secret; send it through another channel.'
        ],

        'note' => [
            'label'  => 'Note',
            'helper' => 'Optional. Only you see it; it identifies the secret in the list below.'
        ],

        'requires_retrieval_step' => [
            'label'  => 'Use a 1-click retrieval step',
            'helper' => 'Keeps chat systems and URL scanners from eating up views. Without it, simply opening the link spends a view.'
        ],

        'allows_deletion' => [
            'label'  => 'Allow immediate deletion',
            'helper' => 'Lets the reader delete the secret once retrieved.'
        ],

        'recipient' => [
            'label'       => 'Recipient',
            'placeholder' => 'Anyone with the link',
            'helper'      => 'Pick a user to restrict the secret to them: they are notified and must log in to open it. Leave it empty to get a link anyone can open.',
            'invalid'     => 'The selected recipient is not valid.'
        ]
    ],

    'link' => [
        'heading' => 'Your secret link is ready',
        'copy'    => 'Copy link',
        'copied'  => 'Link copied',

        'description' => [
            'anyone'    => 'Anyone with this link can open the secret. Copy it now and send it to the reader.',
            'recipient' => ':name has been notified. Only they can open this link, after logging in.'
        ]
    ],

    'table' => [
        'heading'    => 'Sent secrets',
        'note'       => 'Note',
        'creator'    => 'Sent by',
        'recipient'  => 'Recipient',
        'anyone'     => 'Anyone with the link',
        'status'     => 'Status',
        'views'      => 'Views',
        'expires_at' => 'Expires',
        'created_at' => 'Created',

        'revoke' => [
            'label'       => 'Revoke',
            'description' => 'The secret is wiped and its link stops working.',
            'done'        => 'Secret revoked'
        ],

        'empty' => [
            'heading'     => 'No secrets yet',
            'description' => 'The secrets you send will be listed here.'
        ]
    ],

    'status' => [
        'active'    => 'Active',
        'expired'   => 'Expired',
        'exhausted' => 'Views used up',
        'revoked'   => 'Revoked',
        'deleted'   => 'Deleted by the reader'
    ],

    'events' => [
        'action'     => 'Activity',
        'heading'    => 'Activity log',
        'close'      => 'Close',
        'empty'      => 'Nobody has opened this secret yet.',
        'created_at' => 'When',
        'type'       => 'Event',
        'user'       => 'User',
        'ip_address' => 'IP address',
        'user_agent' => 'Browser',
        'guest'      => 'Guest',

        'types' => [
            'viewed'               => 'Viewed',
            'passphrase_failed'    => 'Wrong passphrase',
            'deleted_by_recipient' => 'Deleted by the reader',
            'revoked'              => 'Revoked'
        ]
    ],

    'reveal' => [
        'title'                        => 'Shared secret',
        'unavailable'                  => 'This secret is no longer available. It may have expired, been viewed the maximum number of times or been deleted.',
        'deleted'                      => 'The secret has been deleted and its link no longer works.',
        'delete_confirmation'          => 'The secret will be wiped for everyone and the link will stop working.',
        'delete_confirmation_targeted' => 'The secret will be wiped and the link will stop working.',

        'confirm' => [
            'subheading' => "Someone shared a secret with you.\nRevealing it spends one of its views."
        ],

        'passphrase' => [
            'subheading' => 'This secret is protected. Enter the passphrase you were given to reveal it.',
            'label'      => 'Passphrase',
            'required'   => 'Enter the passphrase.',
            'invalid'    => 'That passphrase is not correct.',
            'throttled'  => 'Too many attempts. Wait a minute and try again.',
            'locked_out' => 'Too many wrong passphrases. This secret is paused for a while; try again later.'
        ],

        'revealed' => [
            'subheading' => "{0} Copy it somewhere safe.\nThis was its last view: the link will no longer work.|{1} Copy it somewhere safe.\nThe link can be opened :count more time.|[2,*] Copy it somewhere safe.\nThe link can be opened :count more times.",
            'label'      => 'Secret',
            'copy'       => 'Copy',
            'copied'     => 'Copied'
        ],

        'actions' => [
            'reveal' => 'Reveal secret',
            'delete' => 'Delete it now'
        ]
    ],

    'notifications' => [
        'mail' => [
            'subject'        => ':sender shared a secret with you',
            'intro'          => ':sender has shared a secret with you.',
            'limits'         => 'It can be revealed :count time and expires on :date.|It can be revealed :count times and expires on :date.',
            'action'         => 'View secret',
            'login_required' => 'You need to be logged in to your account to open it.'
        ],

        'database' => [
            'title'  => ':sender shared a secret with you',
            'body'   => 'It expires on :date.',
            'action' => 'View secret'
        ]
    ],

    'commands' => [
        'prune' => [
            'done' => 'Wiped :wiped expired secrets and deleted :deleted old ones.'
        ]
    ]
];
