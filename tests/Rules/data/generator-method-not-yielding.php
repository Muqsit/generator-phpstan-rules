<?php

declare(strict_types=1);

/**
 * @param int $min
 * @param int $max
 * @return Generator<int>
 */
function range_generator(int $min, int $max) : Generator{
	for($i = $min; $i <= $max; $i++){
		yield $i;
	}
}

function range_user(Generator $generator) : void{
	foreach($generator as $value){
	}
}

function range_test() : Generator{
	range_generator(0, 1);
	foreach(range_generator(2, 3) as $value){
	}
	iterator_to_array(range_generator(2, 3));
	yield range_generator(2, 3);
	yield from range_generator(2, 3);
}
