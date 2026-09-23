<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\function;

use Closure;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class ClosureLootFunction implements LootFunction{

	/**
	 * @param Closure(Item, LootContext) : Item $closure
	 */
	public function __construct(
		readonly private Closure $closure
	){}

	public function apply(Item $item, LootContext $context) : Item{
		return ($this->closure)($item, $context);
	}
}