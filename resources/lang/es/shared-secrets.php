<?php

return [
    'navigation' => [
        'label' => 'Secretos compartidos'
    ],

    'page' => [
        'title'   => 'Secretos compartidos',
        'created' => 'Secreto creado'
    ],

    'form' => [
        'heading'     => 'Nuevo secreto',
        'description' => 'El contenido se cifra, se muestra al lector a través de un enlace y se borra cuando caduca o se agotan las visualizaciones, lo que ocurra primero.',
        'submit'      => 'Enviar',

        'content' => [
            'label'       => 'Secreto',
            'placeholder' => 'Escribe la contraseña o el texto a compartir…'
        ],

        'expires_in' => [
            'label'   => 'Caduca tras',
            'minutes' => ':count minuto|:count minutos',
            'hours'   => ':count hora|:count horas',
            'days'    => ':count día|:count días'
        ],

        'max_views' => [
            'label'  => 'Visualizaciones máximas',
            'helper' => 'Cuántas veces se puede ver el secreto.'
        ],

        'passphrase' => [
            'label'  => 'Frase de acceso',
            'helper' => 'Opcional. El lector tendrá que escribirla para ver el secreto; envíasela por otro canal.'
        ],

        'note' => [
            'label'  => 'Nota',
            'helper' => 'Opcional. Solo la ves tú; identifica el secreto en la lista de abajo.'
        ],

        'requires_retrieval_step' => [
            'label'  => 'Usar un paso de recuperación de 1 clic',
            'helper' => 'Evita que los sistemas de chat y los escáneres de URL consuman visualizaciones. Sin él, abrir el enlace ya consume una visualización.'
        ],

        'allows_deletion' => [
            'label'  => 'Permitir el borrado inmediato',
            'helper' => 'Permite que el lector borre el secreto una vez recuperado.'
        ],

        'recipient' => [
            'label'       => 'Destinatario',
            'placeholder' => 'Cualquiera con el enlace',
            'helper'      => 'Elige un usuario para restringirle el secreto: recibirá un aviso y tendrá que iniciar sesión para abrirlo. Déjalo vacío para obtener un enlace que cualquiera puede abrir.',
            'invalid'     => 'El destinatario seleccionado no es válido.'
        ]
    ],

    'link' => [
        'heading' => 'Tu enlace secreto está listo',
        'copy'    => 'Copiar enlace',
        'copied'  => 'Enlace copiado',

        'description' => [
            'anyone'    => 'Cualquiera con este enlace puede abrir el secreto. Cópialo ahora y envíaselo al lector.',
            'recipient' => 'Se ha avisado a :name. Solo esa persona puede abrir este enlace, tras iniciar sesión.'
        ]
    ],

    'table' => [
        'heading'    => 'Secretos enviados',
        'note'       => 'Nota',
        'creator'    => 'Enviado por',
        'recipient'  => 'Destinatario',
        'anyone'     => 'Cualquiera con el enlace',
        'status'     => 'Estado',
        'views'      => 'Visualizaciones',
        'expires_at' => 'Caduca',
        'created_at' => 'Creado',

        'revoke' => [
            'label'       => 'Revocar',
            'description' => 'El secreto se borra y su enlace deja de funcionar.',
            'done'        => 'Secreto revocado'
        ],

        'empty' => [
            'heading'     => 'Aún no hay secretos',
            'description' => 'Los secretos que envíes aparecerán aquí.'
        ]
    ],

    'status' => [
        'active'    => 'Activo',
        'expired'   => 'Caducado',
        'exhausted' => 'Visualizaciones agotadas',
        'revoked'   => 'Revocado',
        'deleted'   => 'Borrado por el lector'
    ],

    'events' => [
        'action'     => 'Actividad',
        'heading'    => 'Registro de actividad',
        'close'      => 'Cerrar',
        'empty'      => 'Nadie ha abierto este secreto todavía.',
        'created_at' => 'Cuándo',
        'type'       => 'Evento',
        'user'       => 'Usuario',
        'ip_address' => 'Dirección IP',
        'user_agent' => 'Navegador',
        'guest'      => 'Invitado',

        'types' => [
            'viewed'               => 'Visto',
            'passphrase_failed'    => 'Frase de acceso incorrecta',
            'deleted_by_recipient' => 'Borrado por el lector',
            'revoked'              => 'Revocado'
        ]
    ],

    'reveal' => [
        'title'                        => 'Secreto compartido',
        'unavailable'                  => 'Este secreto ya no está disponible. Puede que haya caducado, que se haya visto el número máximo de veces o que se haya borrado.',
        'deleted'                      => 'El secreto se ha borrado y su enlace ya no funciona.',
        'delete_confirmation'          => 'El secreto se borrará para todo el mundo y el enlace dejará de funcionar.',
        'delete_confirmation_targeted' => 'El secreto se borrará y el enlace dejará de funcionar.',

        'confirm' => [
            'subheading' => "Alguien ha compartido un secreto contigo.\nRevelarlo consume una de sus visualizaciones."
        ],

        'passphrase' => [
            'subheading' => 'Este secreto está protegido. Escribe la frase de acceso que te han dado para revelarlo.',
            'label'      => 'Frase de acceso',
            'required'   => 'Escribe la frase de acceso.',
            'invalid'    => 'La frase de acceso no es correcta.',
            'throttled'  => 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.',
            'locked_out' => 'Demasiadas frases de acceso incorrectas. Este secreto está en pausa durante un rato; vuelve a intentarlo más tarde.'
        ],

        'revealed' => [
            'subheading' => "{0} Cópialo en un lugar seguro.\nEra su última visualización: el enlace ya no funcionará más.|{1} Cópialo en un lugar seguro.\nEl enlace se puede abrir :count vez más.|[2,*] Cópialo en un lugar seguro.\nEl enlace se puede abrir :count veces más.",
            'label'      => 'Secreto',
            'copy'       => 'Copiar',
            'copied'     => 'Copiado'
        ],

        'actions' => [
            'reveal' => 'Revelar secreto',
            'delete' => 'Borrarlo ahora'
        ]
    ],

    'notifications' => [
        'mail' => [
            'subject'        => ':sender ha compartido un secreto contigo',
            'intro'          => ':sender ha compartido un secreto contigo.',
            'limits'         => 'Se puede revelar :count vez y caduca el :date.|Se puede revelar :count veces y caduca el :date.',
            'action'         => 'Ver secreto',
            'login_required' => 'Tienes que haber iniciado sesión en tu cuenta para abrirlo.'
        ],

        'database' => [
            'title'  => ':sender ha compartido un secreto contigo',
            'body'   => 'Caduca el :date.',
            'action' => 'Ver secreto'
        ]
    ],

    'commands' => [
        'prune' => [
            'done' => 'Se han borrado :wiped secretos caducados y eliminado :deleted antiguos.'
        ]
    ]
];
