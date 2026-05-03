<?php

namespace App\Http\Controllers\Api\V1;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Workshop OS API',
    description: 'API para gerenciamento de ordens de serviço de oficina mecânica. Perfis: receptionist, mechanic, client.',
    contact: new OA\Contact(email: 'dev@workshop.com')
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Servidor local')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Autenticação via Laravel Sanctum. Inclua o token no header: Authorization: Bearer {token}'
)]
abstract class Controller extends \App\Http\Controllers\Controller
{
}
