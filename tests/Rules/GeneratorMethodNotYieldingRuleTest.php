<?php

declare(strict_types=1);

namespace Muqsit\GeneratorPHPStanRules\Rules;

use Muqsit\GeneratorPHPStanRules\GeneratorMethodNotYieldingRule;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends GeneratorMethodNotYieldingRule
 */
final class GeneratorMethodNotYieldingRuleTest extends RuleTestCase{
	use RuleTestTrait;

	protected function getRule() : GeneratorMethodNotYieldingRule{
		return new GeneratorMethodNotYieldingRule(self::getContainer()->getByType(ReflectionProvider::class));
	}

	public function testRule() : void{
		$this->analyse([__DIR__ . "/data/generator-method-not-yielding.php"], [
			[
				'Generator method returned by \range_generator(0, 1) is unused',
				22
			],
			[
				'Generator method returned by \range_generator(2, 3) is unused',
				26
			],
		]);
	}
}