<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\condition;

use Closure;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;

final class ClosureLootCondition implements LootCondition{

	/**
	 * @param Closure(LootContext) : bool $closure
	 */
	public function __construct(
		readonly private Closure $closure
	){}

	public function test(LootContext $context) : bool{
		return ($this->closure)($context);
	}
}