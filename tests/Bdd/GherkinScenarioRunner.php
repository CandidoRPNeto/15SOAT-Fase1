<?php

namespace Tests\Bdd;

use Behat\Gherkin\Keywords\CachedArrayKeywords;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Gherkin\Parser;
use LogicException;
use ReflectionMethod;
use ReflectionObject;

/**
 * Minimal Gherkin runner on top of behat/gherkin's parser. Behat itself does
 * not install on Laravel 13 (Symfony 8); this keeps BDD inside PHPUnit, so
 * scenarios share RefreshDatabase and count towards coverage.
 *
 * Example: (new GherkinScenarioRunner)->run($feature, $scenario, new ServiceOrderSagaSteps);
 */
final class GherkinScenarioRunner
{
    public static function parse(string $path): FeatureNode
    {
        $parser = new Parser(new Lexer(CachedArrayKeywords::withDefaultKeywords()));
        $feature = $parser->parse((string) file_get_contents($path), $path);

        return $feature ?? throw new LogicException("No feature found in {$path}");
    }

    public function run(FeatureNode $feature, ScenarioInterface $scenario, object $context): void
    {
        $background = $feature->getBackground()?->getSteps() ?? [];

        foreach ([...$background, ...$scenario->getSteps()] as $step) {
            $this->runStep($step, $context);
        }
    }

    private function runStep(StepNode $step, object $context): void
    {
        foreach ((new ReflectionObject($context))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Step::class) as $attribute) {
                if (preg_match('/^'.$attribute->newInstance()->pattern.'$/u', $step->getText(), $matches) === 1) {
                    $method->invokeArgs($context, array_slice($matches, 1));

                    return;
                }
            }
        }

        throw new LogicException("Undefined step: \"{$step->getText()}\" (line {$step->getLine()}); add a #[Step] to ".$context::class);
    }
}
