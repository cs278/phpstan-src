<?php

namespace ArrayPad;

use function PHPStan\Testing\assertType;

class Foo
{

	public function special(): void
	{
		assertType('array{}', array_pad([], 0, true));
	}

	/** @param list<int> $input */
	public function list(array $input, string $value): void
	{
		// array_pad(list<TypeA>, int, <TypeB>)
		//     => list<TypeA|TypeB>

		// array_pad(list<TypeA>, int<min, -1>|int<1, max>, <TypeB>)
		// 	=> non-empty-list<TypeA|TypeB>

		assertType('list<int>', array_pad($input, 0, $value));
		assertType('list<int|string>', array_pad($input, random_int(-1, 1), $value));
		assertType('non-empty-list<int|string>', array_pad($input, 1, $value));
		assertType('non-empty-list<int|string>', array_pad($input, -1, $value));

		assertType('list<int>', array_pad($input, 0, 0));
		assertType('non-empty-list<int>', array_pad($input, 1, 0));
		assertType('non-empty-list<int>', array_pad($input, -1, 0));
	}


	/** @param array<int,bool> $input */
	public function integerIndexed(array $input, string $value0, int $value1): void
	{
		assertType('array<int, bool>', array_pad($input, 0, $value0));
		assertType('array<int, bool|string>', array_pad($input, random_int(-1, 1), $value0));
		assertType('non-empty-array<int, bool|string>', array_pad($input, 1, $value0));
		assertType('non-empty-array<int, bool|string>', array_pad($input, -1, $value0));

		\assert(\count($input) > 0);

		assertType('non-empty-array<int, bool>', array_pad($input, 0, $value0));
		assertType('non-empty-array<int, bool|string>', array_pad($input, random_int(-1, 1), $value0));
		assertType('non-empty-array<int, bool|string>', array_pad($input, 1, $value0));
		assertType('non-empty-array<int, bool|string>', array_pad($input, -1, $value0));

		assertType('non-empty-list<true>', array_pad([1 => true], 2, true));
	}

	/** @param array<string,bool> $input */
	public function stringIndexed(array $input, string $value0, int $value1): void
	{
		assertType('array<string, bool>', array_pad($input, 0, $value0));
		assertType('array<int<0, max>|string, bool|string>', array_pad($input, random_int(-1, 1), $value0));
		assertType('non-empty-array<int<0, max>|string, bool|string>', array_pad($input, 1, $value0));
		assertType('non-empty-array<int<0, max>|string, bool|string>', array_pad($input, -1, $value0));

		\assert(\count($input) > 0);

		assertType('non-empty-array<string, bool>', array_pad($input, 0, $value0));
		assertType('non-empty-array<int<0, max>|string, bool|string>', array_pad($input, random_int(-1, 1), $value0));
		assertType('non-empty-array<int<0, max>|string, bool|string>', array_pad($input, 1, $value0));
		assertType('non-empty-array<int<0, max>|string, bool|string>', array_pad($input, -1, $value0));
	}
}




// array_pad(array<TypeA>, int, <TypeB>)
//     => array<array-key, TypeA|TypeB>


// array_pad(non-empty-array<TypeA>, int<1, max>, <TypeB>)
//    => non-empty-array<array-key, TypeA|TypeB>



// array_pad(array<TypeA>, int<1, max>, <TypeB>)
//   => non-empty-array<array-key, TypeA|TypeB>
