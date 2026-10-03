<?php

return [
    'navigation' => [
        'label' => 'Secrets compartits'
    ],

    'page' => [
        'title'   => 'Secrets compartits',
        'created' => 'Secret creat'
    ],

    'form' => [
        'heading'     => 'Nou secret',
        'description' => 'El contingut es xifra, es mostra al lector mitjançant un enllaç i s\'esborra quan caduca o s\'esgoten les visualitzacions, el que passi primer.',
        'submit'      => 'Envia',

        'content' => [
            'label'       => 'Secret',
            'placeholder' => 'Escriu la contrasenya o el text a compartir…'
        ],

        'expires_in' => [
            'label'   => 'Caduca després de',
            'minutes' => ':count minut|:count minuts',
            'hours'   => ':count hora|:count hores',
            'days'    => ':count dia|:count dies'
        ],

        'max_views' => [
            'label'  => 'Visualitzacions màximes',
            'helper' => 'Quantes vegades es pot revelar el secret.'
        ],

        'passphrase' => [
            'label'  => 'Frase d\'accés',
            'helper' => 'Opcional. El lector l\'haurà d\'escriure per veure el secret; envia-la-hi per un altre canal.'
        ],

        'note' => [
            'label'  => 'Nota',
            'helper' => 'Opcional. Només la veus tu; identifica el secret a la llista de sota.'
        ],

        'requires_retrieval_step' => [
            'label'  => 'Fes servir un pas de recuperació d\'1 clic',
            'helper' => 'Evita que els sistemes de xat i els escàners d\'URL consumeixin visualitzacions. Sense ell, obrir l\'enllaç ja consumeix una visualització.'
        ],

        'allows_deletion' => [
            'label'  => 'Permet l\'esborrat immediat',
            'helper' => 'Permet que el lector esborri el secret un cop recuperat.'
        ],

        'recipient' => [
            'label'       => 'Destinatari',
            'placeholder' => 'Qualsevol amb l\'enllaç',
            'helper'      => 'Tria un usuari per restringir-li el secret: rebrà un avís i haurà d\'iniciar sessió per obrir-lo. Deixa-ho buit per obtenir un enllaç que qualsevol pot obrir.',
            'invalid'     => 'El destinatari seleccionat no és vàlid.'
        ]
    ],

    'link' => [
        'heading' => 'El teu enllaç secret està a punt',
        'copy'    => 'Copia l\'enllaç',
        'copied'  => 'Enllaç copiat',

        'description' => [
            'anyone'    => 'Qualsevol amb aquest enllaç pot obrir el secret. Copia\'l ara i envia\'l al lector.',
            'recipient' => 'S\'ha avisat :name. Només aquesta persona pot obrir aquest enllaç, després d\'iniciar sessió.'
        ]
    ],

    'table' => [
        'heading'    => 'Secrets enviats',
        'note'       => 'Nota',
        'creator'    => 'Enviat per',
        'recipient'  => 'Destinatari',
        'anyone'     => 'Qualsevol amb l\'enllaç',
        'status'     => 'Estat',
        'views'      => 'Visualitzacions',
        'expires_at' => 'Caduca',
        'created_at' => 'Creat',

        'revoke' => [
            'label'       => 'Revoca',
            'description' => 'El secret s\'esborra i el seu enllaç deixa de funcionar.',
            'done'        => 'Secret revocat'
        ],

        'empty' => [
            'heading'     => 'Encara no hi ha secrets',
            'description' => 'Els secrets que enviïs apareixeran aquí.'
        ]
    ],

    'status' => [
        'active'    => 'Actiu',
        'expired'   => 'Caducat',
        'exhausted' => 'Visualitzacions esgotades',
        'revoked'   => 'Revocat',
        'deleted'   => 'Esborrat pel lector'
    ],

    'events' => [
        'action'     => 'Activitat',
        'heading'    => 'Registre d\'activitat',
        'close'      => 'Tanca',
        'empty'      => 'Ningú ha obert aquest secret encara.',
        'created_at' => 'Quan',
        'type'       => 'Esdeveniment',
        'user'       => 'Usuari',
        'ip_address' => 'Adreça IP',
        'user_agent' => 'Navegador',
        'guest'      => 'Convidat',

        'types' => [
            'viewed'               => 'Vist',
            'passphrase_failed'    => 'Frase d\'accés incorrecta',
            'deleted_by_recipient' => 'Esborrat pel lector',
            'revoked'              => 'Revocat'
        ]
    ],

    'reveal' => [
        'title'                        => 'Secret compartit',
        'unavailable'                  => 'Aquest secret ja no està disponible. Pot ser que hagi caducat, que s\'hagi vist el nombre màxim de vegades o que s\'hagi esborrat.',
        'deleted'                      => 'El secret s\'ha esborrat i el seu enllaç ja no funciona.',
        'delete_confirmation'          => 'El secret s\'esborrarà per a tothom i l\'enllaç deixarà de funcionar.',
        'delete_confirmation_targeted' => 'El secret s\'esborrarà i l\'enllaç deixarà de funcionar.',

        'confirm' => [
            'subheading' => "Algú ha compartit un secret amb tu.\nRevelar-lo consumeix una de les seves visualitzacions."
        ],

        'passphrase' => [
            'subheading' => 'Aquest secret està protegit. Escriu la frase d\'accés que t\'han donat per revelar-lo.',
            'label'      => 'Frase d\'accés',
            'required'   => 'Escriu la frase d\'accés.',
            'invalid'    => 'La frase d\'accés no és correcta.',
            'throttled'  => 'Massa intents. Espera un minut i torna-ho a provar.',
            'locked_out' => 'Massa frases d\'accés incorrectes. Aquest secret està en pausa durant una estona; torna-ho a provar més tard.'
        ],

        'revealed' => [
            'subheading' => "{0} Copia'l en un lloc segur.\nEra la seva última visualització: l'enllaç ja no funcionarà més.|{1} Copia'l en un lloc segur.\nL'enllaç es pot obrir :count vegada més.|[2,*] Copia'l en un lloc segur.\nL'enllaç es pot obrir :count vegades més.",
            'label'      => 'Secret',
            'copy'       => 'Copia',
            'copied'     => 'Copiat'
        ],

        'actions' => [
            'reveal' => 'Revela el secret',
            'delete' => 'Esborra\'l ara'
        ]
    ],

    'notifications' => [
        'mail' => [
            'subject'        => ':sender ha compartit un secret amb tu',
            'intro'          => ':sender ha compartit un secret amb tu.',
            'limits'         => 'Es pot revelar :count vegada i caduca el :date.|Es pot revelar :count vegades i caduca el :date.',
            'action'         => 'Veure el secret',
            'login_required' => 'Has d\'haver iniciat sessió al teu compte per obrir-lo.'
        ],

        'database' => [
            'title'  => ':sender ha compartit un secret amb tu',
            'body'   => 'Caduca el :date.',
            'action' => 'Veure el secret'
        ]
    ],

    'commands' => [
        'prune' => [
            'done' => 'S\'han esborrat :wiped secrets caducats i eliminat :deleted d\'antics.'
        ]
    ]
];
