<?php declare(strict_types = 1);

namespace PHPStan\Reflection\SignatureMap;

use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\Native\NativeFunctionReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringAlwaysAcceptingObjectWithToStringType;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\UnionType;

class NativeFunctionReflectionProvider
{

	/** @var NativeFunctionReflection[] */
	private static $functionMap = [];

	/** @var \PHPStan\Reflection\SignatureMap\SignatureMapProvider */
	private $signatureMapProvider;

	public function __construct(SignatureMapProvider $signatureMapProvider)
	{
		$this->signatureMapProvider = $signatureMapProvider;
	}

	public function findFunctionReflection(string $functionName): ?NativeFunctionReflection
	{
		$lowerCasedFunctionName = strtolower($functionName);
		if (isset(self::$functionMap[$lowerCasedFunctionName])) {
			return self::$functionMap[$lowerCasedFunctionName];
		}

		if (!$this->signatureMapProvider->hasFunctionSignature($lowerCasedFunctionName)) {
			return null;
		}

		$variantName = $lowerCasedFunctionName;
		$variants = [];
		$i = 0;
		while ($this->signatureMapProvider->hasFunctionSignature($variantName)) {
			$functionSignature = $this->signatureMapProvider->getFunctionSignature($variantName, null);
			$returnType = $functionSignature->getReturnType();
			if ($lowerCasedFunctionName === 'pow') {
				$returnType = TypeUtils::toBenevolentUnion($returnType);
			}
			$variants[] = new FunctionVariant(
				TemplateTypeMap::createEmpty(),
				null,
				array_map(static function (ParameterSignature $parameterSignature) use ($lowerCasedFunctionName): NativeParameterReflection {
					$type = $parameterSignature->getType();
					if (
						$parameterSignature->getName() === 'args'
						&& (
							$lowerCasedFunctionName === 'printf'
							|| $lowerCasedFunctionName === 'sprintf'
						)
					) {
						$type = new UnionType([
							new StringAlwaysAcceptingObjectWithToStringType(),
							new IntegerType(),
							new FloatType(),
							new NullType(),
							new BooleanType(),
						]);
					}
					return new NativeParameterReflection(
						$parameterSignature->getName(),
						$parameterSignature->isOptional(),
						$type,
						$parameterSignature->passedByReference(),
						$parameterSignature->isVariadic(),
						null
					);
				}, $functionSignature->getParameters()),
				$functionSignature->isVariadic(),
				$returnType,
				false // @todo
			);

			$i++;
			$variantName = sprintf($lowerCasedFunctionName . '\'' . $i);
		}

		if ($this->signatureMapProvider->hasFunctionMetadata($lowerCasedFunctionName)) {
			$hasSideEffects = TrinaryLogic::createFromBoolean($this->signatureMapProvider->getFunctionMetadata($lowerCasedFunctionName)['hasSideEffects']);
		} else {
			$hasSideEffects = TrinaryLogic::createMaybe();
		}
		$functionReflection = new NativeFunctionReflection(
			$lowerCasedFunctionName,
			$variants,
			null,
			$hasSideEffects
		);
		self::$functionMap[$lowerCasedFunctionName] = $functionReflection;

		return $functionReflection;
	}

}
