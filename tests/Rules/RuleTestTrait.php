<?php

declare(strict_types=1);

namespace Muqsit\GeneratorPHPStanRules\Rules;

trait RuleTestTrait{

	public static function getAdditionalConfigFiles() : array{
		$files = parent::getAdditionalConfigFiles();
		$files[] = __DIR__ . "/../../extension.neon";
		return $files;
	}
}