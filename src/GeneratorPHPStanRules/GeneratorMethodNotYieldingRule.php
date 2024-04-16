<?php

declare(strict_types=1);

namespace Muqsit\GeneratorPHPStanRules;

use Generator;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Printer\Printer;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Classes\InstantiationRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeWithClassName;
use function array_map;
use function array_merge;
use function array_push;

/**
 * @implements Rule<CallLike>
 */
final class GeneratorMethodNotYieldingRule implements Rule{

	public function __construct(
		readonly private ReflectionProvider $reflection_provider
	){}

	public function getNodeType() : string{
		return CallLike::class;
	}

	/**
	 * @param New_ $node
	 * @param Scope $scope
	 * @return list<string>
	 * @see InstantiationRule::getClassNames()
	 */
	private function getClassNames(New_ $node, Scope $scope) : array{
		if($node->class instanceof Node\Name){
			return [$scope->resolveName($node->class)];
		}

		if($node->class instanceof Node\Stmt\Class_){
			return $scope->getType($node)->getObjectClassNames();
		}

		$type = $scope->getType($node->class);

		return array_merge(
			array_map(
				static fn(ConstantStringType $type) : string => $type->getValue(),
				$type->getConstantStrings(),
			),
			$type->getObjectClassNames(),
		);
	}

	/**
	 * @param Node $node
	 * @param Scope $scope
	 * @return ParametersAcceptor[]
	 */
	private function getFunctionVariants(Node $node, Scope $scope) : array{
		/** @var list<MethodReflection|FunctionReflection> $reflection */
		$reflections = [];
		if($node instanceof MethodCall){
			$reflection = $scope->getMethodReflection($scope->getType($node->var), $node->name->toString());
			if($reflection !== null){
				$reflections[] = $reflection;
			}
		}elseif($node instanceof StaticCall){
			if($node->class instanceof Name && $node->name instanceof Identifier){
				$reflection = $scope->getMethodReflection($scope->resolveTypeByName($node->class), $node->name->toString());
				if($reflection !== null){
					$reflections[] = $reflection;
				}
			}
		}elseif($node instanceof FuncCall){
			if($node->name instanceof Name){
				$reflections[] = $this->reflection_provider->getFunction($node->name, null);
			}
		}elseif($node instanceof New_){
			foreach($this->getClassNames($node, $scope) as $class_name){
				$class = $this->reflection_provider->getClass($class_name);
				while($class !== null && !$class->hasConstructor()){
					$class = $class->getParentClass();
				}
				if($class !== null){
					$reflections[] = $class->getConstructor();
				}
			}
		}
		$variants = [];
		foreach($reflections as $reflection){
			array_push($variants, ...$reflection->getVariants());
		}
		return $variants;
	}

	private function returnsGenerator(Node $node, Scope $scope) : bool{
		foreach($this->getFunctionVariants($node, $scope) as $variant){
			$return_type = $variant->getReturnType();
			if($return_type instanceof TypeWithClassName && $return_type->getClassName() === Generator::class){
				return true;
			}
		}
		return false;
	}

	private function traverseParents(Node $node) : Generator{
		$current = $node;
		while(!($current instanceof FunctionLike)){
			yield $current;
			$current = $current->getAttribute("parent");
		}
	}

	private function isGeneratorBeingUsed(CallLike $node, Scope $scope) : bool{
		if($node->getAttribute("next") instanceof Identifier){ // a method of the generator is being invoked
			return true;
		}

		$parent = $node->getAttribute("parent");
		if($parent === null){
			return false;
		}

		foreach($this->traverseParents($parent) as $current){
			if($current instanceof YieldFrom || $current instanceof Foreach_ || $current instanceof ArrayItem /* <- spread operator */){
				return true;
			}

			if($current instanceof Assign){
				return true;
			}

			if($current instanceof Arg){ // passed as an argument to a method call
				if($current->unpack){ // part of something like array_push($items, ...$this->generate())
					return true;
				}

				$current_parent = $current->getAttribute("parent");
				$method_accepts_generators = false;
				foreach($this->getFunctionVariants($current_parent, $scope) as $variant){
					foreach($variant->getParameters() as $parameter){
						$type = $parameter->getType();
						if($type->accepts(new ObjectType(Generator::class), true)->yes()){
							$method_accepts_generators = true;
							break 2;
						}
					}
				}
				return $method_accepts_generators;
			}
		}

		return false;
	}

	public function processNode(Node $node, Scope $scope) : array{
		if(!$this->returnsGenerator($node, $scope)){
			return [];
		}
		if($this->isGeneratorBeingUsed($node, $scope)){
			return [];
		}
		return [
			RuleErrorBuilder::message(
				"Generator method returned by " . (new Printer)->prettyPrint([$node]) . " is unused"
			)->build()
		];
	}
}
