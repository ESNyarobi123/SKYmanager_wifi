<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'clickpesa' => [
        'client_id' => env('CLICKPESA_CLIENT_ID'),
        'api_key' => env('CLICKPESA_API_KEY'),
        'webhook_secret' => env('CLICKPESA_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('CLICKPESA_WEBHOOK_SIGNATURE_HEADER', 'X-ClickPesa-Signature'),
    ],

    'ztp' => [
        'vps_ip' => env('ZTP_VPS_IP'),
        'portal_domain' => env('ZTP_PORTAL_DOMAIN', 'portal.skymanager.co.tz'),
        'vpn_subnet' => env('ZTP_VPN_SUBNET', '10.10.0.0/24'),
        'sstp_secret' => env('ZTP_SSTP_SECRET'),
    ],

    'wireguard' => [
        'vps_endpoint' => env('WG_VPS_ENDPOINT'),
        'vps_public_key' => env('WG_VPS_PUBLIC_KEY'),
        'listen_port' => env('WG_LISTEN_PORT', 51820),
        'api_subnet' => env('WG_API_SUBNET', '10.10.0.0/24'),
        // Linux WG interface on VPS (shown in generated "sudo wg set …" helper). WG_INTERFACE_NAME preferred; SKYMANAGER_WG_VPS_INTERFACE is an alias.
        'vps_interface_name' => env('WG_INTERFACE_NAME', env('SKYMANAGER_WG_VPS_INTERFACE', 'wg0')),
        'auto_assign_router_ips' => filter_var(env('WG_AUTO_ASSIGN_IPS', false), FILTER_VALIDATE_BOOL),
    ],

];
