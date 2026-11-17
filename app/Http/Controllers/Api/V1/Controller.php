<?php

namespace App\Http\Controllers\Api\V1;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Workshop OS API',
    description: 'API para gerenciamento de ordens de serviço de oficina mecânica. Perfis: receptionist, mechanic, client.',
    contact: new OA\Contact(email: 'dev@workshop.com')
)]
// URL dirigida por ambiente (constante declarada em config/l5-swagger.php,
// alimentada por L5_SWAGGER_CONST_HOST). Estava fixa em :8000, o que fazia o
// "Try it out" do Swagger bater fora do cluster — no kind quem atende é o
// Traefik em :30080, e nesta máquina o :8000 é outro projeto.
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Servidor local')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Autenticação via Laravel Sanctum. Inclua o token no header: Authorization: Bearer {token}'
)]
abstract class Controller extends \App\Http\Controllers\Controller {}
