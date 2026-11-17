<?php

namespace Tests\Bdd;

use Behat\Gherkin\Node\ScenarioInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Runs every scenario of features/saga_ordem_de_servico.feature as its own test.
 */
class ServiceOrderSagaFeatureTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURE = __DIR__.'/../../features/saga_ordem_de_servico.feature';

    /** @return iterable<string, array{ScenarioInterface}> */
    public static function scenarios(): iterable
    {
        foreach (GherkinScenarioRunner::parse(self::FEATURE)->getScenarios() as $scenario) {
            yield (string) $scenario->getTitle() => [$scenario];
        }
    }

    #[DataProvider('scenarios')]
    public function test_scenario(ScenarioInterface $scenario): void
    {
        $feature = GherkinScenarioRunner::parse(self::FEATURE);

        (new GherkinScenarioRunner)->run($feature, $scenario, new ServiceOrderSagaSteps($this));
    }
}
