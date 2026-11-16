<?php

namespace Tests\Bdd;

use Attribute;

/**
 * Binds a step-definition method to a Gherkin step text (regex, anchored).
 * Captured groups are passed to the method as string arguments.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Step
{
    public function __construct(public string $pattern) {}
}
