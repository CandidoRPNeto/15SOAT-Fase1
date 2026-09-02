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

    // Fase 3 — Function Serverless de auth por CPF (workshop-os-lambda-auth).
    // Ver docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md.
    'lambda_auth' => [
        // Segredo compartilhado que só o Lambda conhece, enviado no header
        // X-Internal-Api-Key — protege POST /internal/clients/cpf-lookup
        // (ver EnsureInternalApiKey). Nunca commitado; sem valor aqui não
        // há default fraco por engano — a ausência do env var faz o
        // middleware negar tudo (ver EnsureInternalApiKey::handle).
        'internal_api_key' => env('LAMBDA_INTERNAL_API_KEY'),
    ],

    'client_jwt' => [
        // Chave pública RS256 do Lambda — só verifica assinatura, nunca
        // assina. A chave privada correspondente vive só no Lambda (AWS),
        // nunca nesta aplicação. RS256 escolhido explicitamente pra não
        // precisar sincronizar um segredo simétrico entre AWS e Dokploy
        // (ver RFC-003).
        'public_key' => env('CLIENT_JWT_PUBLIC_KEY'),
        'issuer' => env('CLIENT_JWT_ISSUER', 'workshop-os-lambda-auth'),
    ],

];
