<?php declare(strict_types = 1);

namespace PHPStan\Type\Php;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\FunctionTypeSpecifyingExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use stdClass;
use function count;
use function strtolower;

final class SetTypeFunctionTypeSpecifyingExtension implements FunctionTypeSpecifyingExtension, TypeSpecifierAwareExtension
{

	private TypeSpecifier $typeSpecifier;

	public function isFunctionSupported(FunctionReflection $functionReflection, FuncCall $node, TypeSpecifierContext $context): bool
	{
		return strtolower($functionReflection->getName()) === 'settype'
			&& count($node->getArgs()) > 1
			&& $context->null();
	}

	public function specifyTypes(FunctionReflection $functionReflection, FuncCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
	{
		$value = $node->getArgs()[0]->value;
		$valueType = $scope->getType($value);
		$castType = $scope->getType($node->getArgs()[1]->value);

		// var_dump($castType->describe(VerbosityLevel::precise()));

		$castTypes = [
			'bool',
			'boolean',
			'int',
			'integer',
			'float',
			'double',
			'string',
			'array',
			'object',
			'null',
		];

		$types = [];

		// if ()

		foreach ($castTypes as $type) {
			if ($castType->isSuperTypeOf(new ConstantStringType($type))->yes()) {
				switch ($type) {
					case 'bool':
					case 'boolean':
						$resultType = $valueType->toBoolean();
						break;
					case 'int':
					case 'integer':
						$resultType = $valueType->toInteger();
						break;
					case 'float':
					case 'double':
						$resultType = $valueType->toFloat();
						break;
					case 'string':
						$resultType = $valueType->toString();
						break;
					case 'array':
						$resultType = $valueType->toArray();
						break;
					case 'object':
						$resultType = new ObjectType(stdClass::class);
						break;
					case 'null':
						$resultType = new NullType();
						break;
				}



				if (!$resultType->equals(new ErrorType()) || $castType->isConstantScalarValue()->yes()) {
					$types[] = $resultType;
				} else {
					$types[] = $valueType;
				}
			}
		}

		// var_dump($types);
		// var_dump(TypeCombinator::union(...$types)->describe(VerbosityLevel::precise()));

		return $this->typeSpecifier->create(
			$value,
			TypeCombinator::union(...$types),
			TypeSpecifierContext::createTruthy(),
			$scope,
		)->setAlwaysOverwriteTypes();


		$castToType = TypeCombinator::union(
			new ConstantStringType('bool'),
			new ConstantStringType('boolean'),
			new ConstantStringType('int'),
			new ConstantStringType('integer'),
			new ConstantStringType('float'),
			new ConstantStringType('double'),
			new ConstantStringType('string'),
			new ConstantStringType('array'),
			new ConstantStringType('object'),
			new ConstantStringType('null'),
		);

		$castToType = TypeCombinator::intersect($castToType, $castType);

		var_dump($castToType->describe(VerbosityLevel::precise()));


		$types = [
			$valueType->toBoolean(),
			$valueType->toInteger(),
			$valueType->toFloat(),
			$valueType->toString(),
			$valueType->toArray(),
			new ObjectType(stdClass::class),
			new NullType(),
		];



		$constantStrings = $castType->getConstantStrings();
		if (count($constantStrings) < 1) {
			return $this->typeSpecifier->create(
				$value,
				TypeCombinator::union(...$types),
				TypeSpecifierContext::createTruthy(),
				$scope,
			)->setAlwaysOverwriteTypes();
		}

		$types = [];

		foreach ($constantStrings as $constantString) {
			switch ($constantString->getValue()) {
				case 'bool':
				case 'boolean':
					$types[] = $valueType->toBoolean();
					break;
				case 'int':
				case 'integer':
					$types[] = $valueType->toInteger();
					break;
				case 'float':
				case 'double':
					$types[] = $valueType->toFloat();
					break;
				case 'string':
					$types[] = $valueType->toString();
					break;
				case 'array':
					$types[] = $valueType->toArray();
					break;
				case 'object':
					$types[] = new ObjectType(stdClass::class);
					break;
				case 'null':
					$types[] = new NullType();
					break;
				default:
					$types[] = new ErrorType();
			}
		}

		return $this->typeSpecifier->create(
			$value,
			TypeCombinator::union(...$types),
			TypeSpecifierContext::createTruthy(),
			$scope,
		)->setAlwaysOverwriteTypes();
	}

	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}

}
