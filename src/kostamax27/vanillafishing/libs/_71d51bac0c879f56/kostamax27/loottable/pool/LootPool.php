<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\pool;

use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootPool{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}