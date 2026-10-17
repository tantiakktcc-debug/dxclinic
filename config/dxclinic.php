<?php

return [
    'clinic_name' => env('DXCLINIC_NAME', 'DxClinic'),
    'timezone' => env('DXCLINIC_TIMEZONE', 'Asia/Kolkata'),
    'polling_interval' => env('DXCLINIC_POLLING_INTERVAL', 5),
    'online_booking_enabled' => env('DXCLINIC_ONLINE_BOOKING', true),
    'telephone_grace_days' => env('DXCLINIC_GRACE_DAYS', 5),
    'token_pad_length' => env('DXCLINIC_TOKEN_PAD_LENGTH', 3),
    'backup_passphrase' => env('BACKUP_PASSPHRASE'),

    /*
    |--------------------------------------------------------------------------
    | Canonical Developer Website & Attribution
    |--------------------------------------------------------------------------
    */
    'developer_website' => env('DEVELOPER_WEBSITE', 'https://nexorasolutionsind.co.in/'),
    'developer_name' => 'Kundan Kumar',
    'developer_company' => 'Nexora Solution',
    'developer_email' => env('DEVELOPER_EMAIL', 'contact@nexorasolutionsind.co.in'),
    'developer_phone' => env('DEVELOPER_PHONE', null),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp & Meta Cloud API Configuration
    |--------------------------------------------------------------------------
    */
    'whatsapp_verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'dxclinic_verify_token_secure'),
    'whatsapp_app_secret' => env('WHATSAPP_APP_SECRET', null),
    'whatsapp' => [
        /*
         * Active provider selection:
         *   BAILEYS  → BaileysWhatsAppProvider (development, local gateway 127.0.0.1:9090)
         *   META     → MetaWhatsAppProvider    (production, Meta Cloud API)
         *   mock     → MockWhatsAppProvider    (tests)
         *
         * Set WHATSAPP_PROVIDER=BAILEYS in .env for development.
         * Set WHATSAPP_PROVIDER=META in .env for production.
         */
        'provider' => env('WHATSAPP_PROVIDER', 'baileys'),

        // Meta Cloud API
        'app_id' => env('META_APP_ID', null),
        'app_secret' => env('META_APP_SECRET', env('WHATSAPP_APP_SECRET', null)),
        'config_id' => env('META_EMBEDDED_SIGNUP_CONFIG_ID', env('META_CONFIG_ID', null)),
        'live_enabled' => (bool) env('WHATSAPP_META_LIVE_ENABLED', false),
        'dev_test_phone' => env('WHATSAPP_DEVELOPMENT_TEST_PHONE', null),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'dxclinic_verify_token_secure'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'max_retries' => (int) env('WHATSAPP_MAX_RETRIES', 3),
        'timeout_seconds' => (int) env('WHATSAPP_TIMEOUT', 15),

        // Authoritative Meta v4 Embedded Signup Configuration
        'embedded_signup' => [
            'app_id' => env('META_APP_ID', null),
            'config_id' => env('META_EMBEDDED_SIGNUP_CONFIG_ID', null),
            'version' => env('META_EMBEDDED_SIGNUP_VERSION', 'v4'),
            'session_info_version' => env('META_EMBEDDED_SIGNUP_SESSION_INFO_VERSION', '3'),
            'feature_type' => env('META_EMBEDDED_SIGNUP_FEATURE_TYPE', 'whatsapp_business_app_onboarding'),
            'landing_url' => env('META_EMBEDDED_SIGNUP_URL', null),
        ],

        /*
         * Baileys Development Gateway Configuration
         *
         * SECURITY:
         * - Gateway binds ONLY to loopback 127.0.0.1:9090 (never 0.0.0.0)
         * - BAILEYS_GATEWAY_SECRET used for HMAC-SHA256 request signing (server-side only)
         * - Never expose BAILEYS_GATEWAY_SECRET to browser, JS, HTML, localStorage, logs, or URLs
         * - The gateway can optionally be routed through a secure reverse proxy or tunnel for development/WebSocket access.
         */
        'baileys' => [
            'gateway_url'         => env('BAILEYS_GATEWAY_URL', 'http://127.0.0.1:9090'),
            'gateway_secret'      => env('BAILEYS_GATEWAY_SECRET', ''),
            'webhook_secret'      => env('BAILEYS_WEBHOOK_SECRET', ''),
            'webhook_verify_token' => env('BAILEYS_WEBHOOK_VERIFY_TOKEN', 'baileys_dev_verify_secure'),
            'timeout'             => (int) env('BAILEYS_GATEWAY_TIMEOUT', 15),
            'session_dir'         => env('BAILEYS_SESSION_DIR', storage_path('app/baileys-sessions')),
        ],
    ],
];
