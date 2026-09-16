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
use Illuminate\Foundation\Console\ServeCommand;
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
        $this->passDatadogEnvToServeCommand();

        Auth::viaRequest('client_jwt', function (Request $request) {
            return $this->resolveUserFromClientJwt($request);
        });
    }

    /**
     * Repassa as variáveis DD_* ao servidor embutido do `php artisan serve`.
     *
     * O ServeCommand roda o `php -S` como processo filho e apaga do ambiente
     * dele tudo que não estiver em ServeCommand::$passthroughVariables (o
     * `in_array(...) ? [$key => $value] : [$key => false]` do próprio
     * comando). Como é o `php -S` que atende TODAS as requisições HTTP, sem
     * isto ele perde DD_TRACE_AGENT_URL/DD_AGENT_HOST e o sidecar do dd-trace
     * cai no default `localhost:8126` — "Connection refused" dentro do
     * container e nenhum trace de requisição web chega ao Agent.
     *
     * A falha é silenciosa: a extensão ddtrace vem via INI, não via ambiente,
     * então continua carregada e os spans seguem sendo gerados — apenas nunca
     * são entregues.
     *
     * Exemplo: com DD_TRACE_AGENT_URL=http://172.22.0.1:8126 no ambiente, o
     * `php -S` passa a enxergar essa variável e os traces chegam ao Agent.
     */
    private function passDatadogEnvToServeCommand(): void
    {
        foreach (array_keys(getenv()) as $name) {
            if (! str_starts_with((string) $name, 'DD_')) {
                continue;
            }

            if (! in_array($name, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $name;
            }
        }
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
