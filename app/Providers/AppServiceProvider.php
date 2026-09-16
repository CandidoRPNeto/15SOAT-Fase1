<?php

namespace App\Providers;

use App\Application\Ports\ServiceOrderRepository;
use App\Contracts\EmailStatusUpdateServiceInterface;
use App\Contracts\MessagingServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Enums\UserRole;
use App\Infrastructure\Messaging\StubEmailStatusUpdateService;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Models\User;
use App\Services\StubMessagingService;
use App\Services\StubPaymentService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use UnexpectedValueException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentServiceInterface::class, StubPaymentService::class);
        $this->app->bind(MessagingServiceInterface::class, StubMessagingService::class);
        $this->app->bind(ServiceOrderRepository::class, EloquentServiceOrderRepository::class);
        $this->app->bind(EmailStatusUpdateServiceInterface::class, StubEmailStatusUpdateService::class);
    }

    public function boot(): void
    {
        Auth::viaRequest('client_jwt', function (Request $request) {
            return $this->resolveUserFromClientJwt($request);
        });
    }

    /**
     * Valida o JWT (RS256) emitido pela Function Serverless de auth por CPF
     * e resolve o User correspondente. Retorna null pra qualquer falha —
     * token ausente, assinatura inválida, expirado, issuer errado, usuário
     * não encontrado, ou não mais um cliente ativo — sem distinguir o
     * motivo pro chamador (mesma postura de "não autenticado" para todos os
     * casos, não vaza qual verificação falhou). Ver RFC-003.
     */
    private function resolveUserFromClientJwt(Request $request): ?User
    {
        $token = $request->bearerToken();
        $publicKey = config('services.client_jwt.public_key');

        if (! $token || ! $publicKey) {
            return null;
        }

        try {
            // ExpiredException e SignatureInvalidException (firebase/php-jwt)
            // são subclasses de UnexpectedValueException — cobertas por
            // este único catch, junto com token malformado.
            $claims = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (UnexpectedValueException) {
            return null;
        }

        if (($claims->iss ?? null) !== config('services.client_jwt.issuer')) {
            return null;
        }

        $user = User::find($claims->sub ?? null);

        if (! $user || ! $user->hasRole(UserRole::CLIENT) || ! $user->isActive()) {
            return null;
        }

        return $user;
    }
}
