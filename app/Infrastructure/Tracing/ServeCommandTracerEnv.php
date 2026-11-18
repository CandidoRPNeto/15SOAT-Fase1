<?php

namespace App\Infrastructure\Tracing;

use Illuminate\Foundation\Console\ServeCommand;

/**
 * Repassa as variáveis DD_* ao servidor embutido do `php artisan serve`.
 *
 * O ServeCommand roda o `php -S` como processo filho e apaga do ambiente
 * dele tudo que não estiver em ServeCommand::$passthroughVariables (o
 * `in_array(...) ? [$key => $value] : [$key => false]` do próprio
 * comando). Como é o `php -S` que atende TODAS as requisições HTTP, sem
 * isto ele perde DD_TRACE_AGENT_URL/DD_AGENT_HOST e o sidecar do dd-trace
 * cai no default `localhost:8126` — "Connection refused" dentro do
 * container e nenhum trace de requisição web chega ao trace agent.
 *
 * A falha é silenciosa: a extensão ddtrace vem via INI, não via ambiente,
 * então continua carregada e os spans seguem sendo gerados — apenas nunca
 * são entregues. Billing e Execution ficaram sem traces HTTP até esta classe
 * entrar na lib de mensageria/tracing sincronizada (ADR-015/016).
 *
 * Exemplo: ServeCommandTracerEnv::passThrough(); // no boot() do AppServiceProvider
 */
final class ServeCommandTracerEnv
{
    public static function passThrough(): void
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
}
