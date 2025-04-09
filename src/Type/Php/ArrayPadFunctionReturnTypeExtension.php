<?php declare(strict_types = 1);

namespace PHPStan\Type\Php;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Php\PhpVersion;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;

final class ArrayPadFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{

	public function __construct(private PhpVersion $phpVersion)
	{
	}

	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return $functionReflection->getName() === 'array_pad';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): ?Type
	{
		if (count($functionCall->getArgs()) < 3) {
			return null;
		}

		$inputType = $scope->getType($functionCall->getArgs()[0]->value);
		$lengthType = $scope->getType($functionCall->getArgs()[1]->value);
		$valueType = $scope->getType($functionCall->getArgs()[2]->value);

		$isInputNonEmpty = (new NonEmptyArrayType())->isSuperTypeOf($inputType);
		$isLengthZero = (new ConstantIntegerType(0))->isSuperTypeOf($lengthType->toAbsoluteNumber());
		$isResultEmpty = $isInputNonEmpty->negate()->and($isLengthZero);

		// array_pad([], 0, $value)
		if ($isResultEmpty->yes()) {
			return new ConstantArrayType([], []);
		}

		// Padding is always a list.
		$paddingType = TypeCombinator::intersect(new ArrayType(new IntegerType(), $valueType), new AccessoryArrayListType());

		if ($inputType->isConstantArray()->yes()) {
			$result = TypeCombinator::intersect(
				new ArrayType(new IntegerType(), TypeCombinator::union($inputType->getIterableValueType(), $paddingType->getIterableValueType())),
				new AccessoryArrayListType(),
			);
		} elseif ($isLengthZero->yes()) {
			$result = $inputType;
		} else {
			$result = TypeCombinator::union($inputType, $paddingType);
		}

		if ($isResultEmpty->no()) {
			$result = TypeCombinator::intersect($result, new NonEmptyArrayType());
		}

		return $result;
	}

}
