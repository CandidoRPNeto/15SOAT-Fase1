<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Console\ServeCommand;
use Tests\TestCase;

/**
 * Regressão: o `php artisan serve` apagava as DD_* do ambiente do `php -S`,
 * que é quem atende as requisições HTTP. O sidecar do dd-trace então caía no
 * default localhost:8126 e nenhum trace de requisição web chegava ao Agent.
 */
class ServeCommandDatadogEnvTest extends TestCase
{
    /** @var list<string> */
    private array $originalPassthrough;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPassthrough = ServeCommand::$passthroughVariables;
    }

    protected function tearDown(): void
    {
        ServeCommand::$passthroughVariables = $this->originalPassthrough;
        putenv('DD_TRACE_AGENT_URL');
        putenv('WORKSHOP_NOT_DATADOG');
        parent::tearDown();
    }

    public function test_datadog_variables_are_passed_through_to_the_embedded_server(): void
    {
        putenv('DD_TRACE_AGENT_URL=http://127.0.0.1:8126');

        (new AppServiceProvider($this->app))->boot();

        $this->assertContains('DD_TRACE_AGENT_URL', ServeCommand::$passthroughVariables);
    }

    public function test_unrelated_variables_are_not_passed_through(): void
    {
        putenv('WORKSHOP_NOT_DATADOG=1');

        (new AppServiceProvider($this->app))->boot();

        $this->assertNotContains('WORKSHOP_NOT_DATADOG', ServeCommand::$passthroughVariables);
    }

    public function test_repeated_boots_do_not_duplicate_entries(): void
    {
        putenv('DD_TRACE_AGENT_URL=http://127.0.0.1:8126');

        (new AppServiceProvider($this->app))->boot();
        (new AppServiceProvider($this->app))->boot();

        $occurrences = array_count_values(ServeCommand::$passthroughVariables)['DD_TRACE_AGENT_URL'] ?? 0;
        $this->assertSame(1, $occurrences);
    }
}
