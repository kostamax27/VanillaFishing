<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\function;

use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootFunction{

	/**
	 * Applies the function to $item. The returned item may be the same
	 * instance mutated, or a different one (e.g. furnace_smelt).
	 */
	public function apply(Item $item, LootContext $context) : Item;
}