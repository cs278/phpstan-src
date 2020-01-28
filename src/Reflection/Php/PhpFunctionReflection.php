<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Php;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\Cache\Cache;
use PHPStan\Parser\FunctionCallStatementFinder;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\FunctionVariantWithPhpDocs;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorWithPhpDocs;
use PHPStan\Reflection\ReflectionWithFilename;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypehintHelper;
use PHPStan\Type\VoidType;

class PhpFunctionReflection implements FunctionReflection, ReflectionWithFilename
{

	/** @var \ReflectionFunction */
	private $reflection;

	/** @var \PHPStan\Parser\Parser */
	private $parser;

	/** @var \PHPStan\Parser\FunctionCallStatementFinder */
	private $functionCallStatementFinder;

	/** @var \PHPStan\Cache\Cache */
	private $cache;

	/** @var \PHPStan\Type\Generic\TemplateTypeMap */
	private $templateTypeMap;

	/** @var \PHPStan\Type\Type[] */
	private $phpDocParameterTypes;

	/** @var \PHPStan\Type\Type|null */
	private $phpDocReturnType;

	/** @var \PHPStan\Type\Type|null */
	private $phpDocThrowType;

	/** @var string|null  */
	private $deprecatedDescription;

	/** @var bool */
	private $isDeprecated;

	/** @var bool */
	private $isInternal;

	/** @var bool */
	private $isFinal;

	/** @var string|false */
	private $filename;

	/** @var FunctionVariantWithPhpDocs[]|null */
	private $variants;

	/**
	 * @param \ReflectionFunction $reflection
	 * @param Parser $parser
	 * @param FunctionCallStatementFinder $functionCallStatementFinder
	 * @param Cache $cache
	 * @param TemplateTypeMap $templateTypeMap
	 * @param \PHPStan\Type\Type[] $phpDocParameterTypes
	 * @param Type|null $phpDocReturnType
	 * @param Type|null $phpDocThrowType
	 * @param string|null $deprecatedDescription
	 * @param bool $isDeprecated
	 * @param bool $isInternal
	 * @param bool $isFinal
	 * @param string|false $filename
	 */
	public function __construct(
		\ReflectionFunction $reflection,
		Parser $parser,
		FunctionCallStatementFinder $functionCallStatementFinder,
		Cache $cache,
		TemplateTypeMap $templateTypeMap,
		array $phpDocParameterTypes,
		?Type $phpDocReturnType,
		?Type $phpDocThrowType,
		?string $deprecatedDescription,
		bool $isDeprecated,
		bool $isInternal,
		bool $isFinal,
		$filename
	)
	{
		$this->reflection = $reflection;
		$this->parser = $parser;
		$this->functionCallStatementFinder = $functionCallStatementFinder;
		$this->cache = $cache;
		$this->templateTypeMap = $templateTypeMap;
		$this->phpDocParameterTypes = $phpDocParameterTypes;
		$this->phpDocReturnType = $phpDocReturnType;
		$this->phpDocThrowType = $phpDocThrowType;
		$this->isDeprecated = $isDeprecated;
		$this->deprecatedDescription = $deprecatedDescription;
		$this->isInternal = $isInternal;
		$this->isFinal = $isFinal;
		$this->filename = $filename;
	}

	public function getName(): string
	{
		return $this->reflection->getName();
	}

	/**
	 * @return string|false
	 */
	public function getFileName()
	{
		return $this->filename;
	}

	/**
	 * @return ParametersAcceptorWithPhpDocs[]
	 */
	public function getVariants(): array
	{
		if ($this->variants === null) {
			$this->variants = [
				new FunctionVariantWithPhpDocs(
					$this->templateTypeMap,
					null,
					$this->getParameters(),
					$this->isVariadic(),
					$this->getReturnType(),
					$this->isReturnByReference(),
					$this->getPhpDocReturnType(),
					$this->getNativeReturnType()
				),
			];
		}

		return $this->variants;
	}

	/**
	 * @return \PHPStan\Reflection\ParameterReflectionWithPhpDocs[]
	 */
	private function getParameters(): array
	{
		return array_map(function (\ReflectionParameter $reflection): PhpParameterReflection {
			return new PhpParameterReflection(
				$reflection,
				$this->phpDocParameterTypes[$reflection->getName()] ?? null
			);
		}, $this->reflection->getParameters());
	}

	private function isVariadic(): bool
	{
		$isNativelyVariadic = $this->reflection->isVariadic();
		if (!$isNativelyVariadic && $this->reflection->getFileName() !== false) {
			$fileName = $this->reflection->getFileName();
			$functionName = $this->reflection->getName();
			$modifiedTime = filemtime($fileName);
			if ($modifiedTime === false) {
				$modifiedTime = time();
			}
			$variableCacheKey = sprintf('%d-v1', $modifiedTime);
			$key = sprintf('variadic-function-%s-%s', $functionName, $fileName);
			$cachedResult = $this->cache->load($key, $variableCacheKey);
			if ($cachedResult === null) {
				$nodes = $this->parser->parseFile($fileName);
				$result = $this->callsFuncGetArgs($nodes);
				$this->cache->save($key, $variableCacheKey, $result);
				return $result;
			}

			return $cachedResult;
		}

		return $isNativelyVariadic;
	}

	/**
	 * @param \PhpParser\Node[] $nodes
	 * @return bool
	 */
	private function callsFuncGetArgs(array $nodes): bool
	{
		foreach ($nodes as $node) {
			if ($node instanceof Function_) {
				$functionName = (string) $node->namespacedName;

				if ($functionName === $this->reflection->getName()) {
					return $this->functionCallStatementFinder->findFunctionCallInStatements(ParametersAcceptor::VARIADIC_FUNCTIONS, $node->getStmts()) !== null;
				}

				continue;
			}

			if ($node instanceof ClassLike) {
				continue;
			}

			if ($node instanceof Namespace_) {
				if ($this->callsFuncGetArgs($node->stmts)) {
					return true;
				}
			}

			if (!$node instanceof Declare_ || $node->stmts === null) {
				continue;
			}

			if ($this->callsFuncGetArgs($node->stmts)) {
				return true;
			}
		}

		return false;
	}

	private function getReturnType(): Type
	{
		if ($this->reflection->getName() === 'count') {
			return new IntegerType();
		}

		return TypehintHelper::decideTypeFromReflection(
			$this->reflection->getReturnType(),
			$this->phpDocReturnType
		);
	}

	private function getPhpDocReturnType(): Type
	{
		if ($this->phpDocReturnType !== null) {
			return $this->phpDocReturnType;
		}

		return new MixedType();
	}

	private function getNativeReturnType(): Type
	{
		return TypehintHelper::decideTypeFromReflection($this->reflection->getReturnType());
	}

	public function getDeprecatedDescription(): ?string
	{
		if ($this->isDeprecated) {
			return $this->deprecatedDescription;
		}

		return null;
	}

	public function isDeprecated(): TrinaryLogic
	{
		return TrinaryLogic::createFromBoolean(
			$this->isDeprecated || $this->reflection->isDeprecated()
		);
	}

	public function isInternal(): TrinaryLogic
	{
		return TrinaryLogic::createFromBoolean($this->isInternal);
	}

	public function isFinal(): TrinaryLogic
	{
		return TrinaryLogic::createFromBoolean($this->isFinal);
	}

	public function getThrowType(): ?Type
	{
		return $this->phpDocThrowType;
	}

	public function hasSideEffects(): TrinaryLogic
	{
		if ($this->getReturnType() instanceof VoidType) {
			return TrinaryLogic::createYes();
		}
		return TrinaryLogic::createMaybe();
	}

	public function isReturnByReference(): bool
	{
		return $this->reflection->returnsReference();
	}

}
