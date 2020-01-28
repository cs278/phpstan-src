<?php declare(strict_types = 1);

namespace PHPStan\Reflection;

use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

class FunctionVariantWithPhpDocs extends FunctionVariant implements ParametersAcceptorWithPhpDocs
{

	/** @var Type */
	private $phpDocReturnType;

	/** @var Type */
	private $nativeReturnType;

	/**
	 * @param TemplateTypeMap $templateTypeMap
	 * @param array<int, \PHPStan\Reflection\ParameterReflectionWithPhpDocs> $parameters
	 * @param bool $isVariadic
	 * @param Type $returnType
	 * @param Type $phpDocReturnType
	 * @param Type $nativeReturnType
	 */
	public function __construct(
		TemplateTypeMap $templateTypeMap,
		?TemplateTypeMap $resolvedTemplateTypeMap,
		array $parameters,
		bool $isVariadic,
		Type $returnType,
		bool $isReturnByReference = null, // @todo
		Type $phpDocReturnType,
		Type $nativeReturnType
	)
	{
		parent::__construct(
			$templateTypeMap,
			$resolvedTemplateTypeMap,
			$parameters,
			$isVariadic,
			$returnType,
			$isReturnByReference
		);
		$this->phpDocReturnType = $phpDocReturnType;
		$this->nativeReturnType = $nativeReturnType;
	}

	/**
	 * @return array<int, \PHPStan\Reflection\ParameterReflectionWithPhpDocs>
	 */
	public function getParameters(): array
	{
		/** @var \PHPStan\Reflection\ParameterReflectionWithPhpDocs[] $parameters */
		$parameters = parent::getParameters();

		return $parameters;
	}

	public function getPhpDocReturnType(): Type
	{
		return $this->phpDocReturnType;
	}

	public function getNativeReturnType(): Type
	{
		return $this->nativeReturnType;
	}

}
