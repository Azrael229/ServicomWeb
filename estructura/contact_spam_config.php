<?php
declare(strict_types=1);

/**
 * Reglas editables del filtro antispam local.
 *
 * Este archivo no contiene credenciales. Las listas se comparan sin distinguir
 * mayúsculas, minúsculas ni acentos. Los umbrales buscan bloquear automatización
 * evidente sin penalizar mensajes comerciales breves y legítimos.
 */
return [
    'blocked_tlds' => [
        'ru',
    ],

    'blocked_domains' => [
        'mail.ru',
        'inbox.ru',
        'ventura17.ru',
        'bk.ru',
        'bekommenmail.com',
        'duhastmail.com',
        'fuhrenmail.com',
        'dezikmail.com',
        'hotmail.cim',
    ],

    'max_links' => 2,

    'blocked_scripts' => [
        'Cyrillic',
        'Arabic',
        'Hebrew',
        'Han',
        'Hiragana',
        'Katakana',
        'Hangul',
    ],
    'blocked_script_min_characters' => 2,

    'allowed_technical_words' => [
        'software',
        'bluetooth',
        'wifi',
        'rs232',
        'usb',
        'excel',
        'pdf',
    ],

    'blocked_phrases' => [
        // Agregar posteriormente frases recurrentes de spam.
    ],

    'short_foreign_phrases' => [
        'hello',
        'contact me',
        'business proposal',
        'seo services',
        'buy now',
    ],

    'spanish_signal_words' => [
        'a', 'al', 'con', 'de', 'del', 'el', 'en', 'es', 'la', 'las', 'lo',
        'los', 'mi', 'no', 'para', 'por', 'que', 'se', 'su', 'un', 'una', 'y',
        'ajuste', 'bascula', 'basculas', 'calibracion', 'calibrar', 'conectar',
        'conexion', 'computadora', 'cotizacion', 'enciende', 'equipo',
        'informacion', 'mantenimiento', 'mensaje', 'necesito', 'plataforma',
        'precio', 'reparacion', 'reparar', 'requiero', 'servicio', 'solicito',
    ],

    'foreign_signal_words' => [
        'english' => [
            'about', 'and', 'are', 'business', 'buy', 'contact', 'for', 'from',
            'hello', 'i', 'information', 'me', 'need', 'now', 'our', 'please',
            'proposal', 'provide', 'services', 'the', 'we', 'website', 'your',
        ],
        'french' => [
            'avec', 'bonjour', 'besoin', 'contactez', 'de', 'des', 'et', 'je',
            'nous', 'pour', 'proposition', 'services', 'une', 'votre',
        ],
        'portuguese' => [
            'com', 'contato', 'de', 'e', 'eu', 'informacao', 'negocio',
            'nosso', 'para', 'preciso', 'proposta', 'servicos', 'uma', 'voce',
        ],
        'italian' => [
            'affari', 'bisogno', 'con', 'contatto', 'di', 'e', 'informazioni',
            'io', 'per', 'proposta', 'servizi', 'una', 'vostro',
        ],
    ],
    'foreign_minimum_signals' => 2,
    'foreign_signal_margin' => 2,

    'max_consecutive_characters' => 12,
    'max_consecutive_words' => 5,
    'max_repeated_phrase_occurrences' => 3,
    'max_duplicate_blocks' => 2,
];
