<?php declare(strict_types = 1);

namespace PHPStan\Reflection;

use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

class InaccessibleMethod implements ParametersAcceptor
{

	/** @var MethodReflection */
	private $methodReflection;

	public function __construct(MethodReflection $methodReflection)
	{
		$this->methodReflection = $methodReflection;
	}

	public function getMethod(): MethodReflection
	{
		return $this->methodReflection;
	}

	public function getTemplateTypeMap(): TemplateTypeMap
	{
		return TemplateTypeMap::createEmpty();
	}

	public function getResolvedTemplateTypeMap(): TemplateTypeMap
	{
		return TemplateTypeMap::createEmpty();
	}

	/**
	 * @return array<int, \PHPStan\Reflection\ParameterReflection>
	 */
	public function getParameters(): array
	{
		return [];
	}

	public function isVariadic(): bool
	{
		return true;
	}

	public function getReturnType(): Type
	{
		return new MixedType();
	}

	public function isReturnByReference(): bool
	{
		return false; // @todo Correct default?
	}

}
