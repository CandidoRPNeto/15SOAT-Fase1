<?php

namespace Tests\Unit;

use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Log::withContext() é uma facade — precisa de um container mínimo
        // pra resolver. Testamos a lógica do middleware sem subir a app
        // inteira (RefreshDatabase/TestCase do Laravel), então montamos só
        // o suficiente pra facade funcionar.
        $app = new Application;
        $app->singleton('log', function () {
            return new class
            {
                public array $context = [];

                public function withContext(array $context = []): void
                {
                    $this->context = array_merge($this->context, $context);
                }
            };
        });
        Facade::setFacadeApplication($app);
    }

    public function test_generates_a_correlation_id_when_no_header_is_present(): void
    {
        $middleware = new AssignCorrelationId;
        $request = Request::create('/api/v1/service-orders');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $id = $response->headers->get(AssignCorrelationId::HEADER);
        $this->assertNotEmpty($id);
        $this->assertSame($id, $request->attributes->get('correlation_id'));
    }

    public function test_reuses_the_incoming_gateway_header_instead_of_generating_a_new_id(): void
    {
        $middleware = new AssignCorrelationId;
        $request = Request::create('/api/v1/service-orders');
        $request->headers->set(AssignCorrelationId::HEADER, 'gateway-generated-id-123');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame('gateway-generated-id-123', $response->headers->get(AssignCorrelationId::HEADER));
        $this->assertSame('gateway-generated-id-123', $request->attributes->get('correlation_id'));
    }

    public function test_adds_the_correlation_id_to_the_log_context(): void
    {
        $middleware = new AssignCorrelationId;
        $request = Request::create('/api/v1/service-orders');
        $request->headers->set(AssignCorrelationId::HEADER, 'ctx-check-id');

        $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame('ctx-check-id', Log::getFacadeRoot()->context['request_id']);
    }
}
