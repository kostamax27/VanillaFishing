<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\looting;

use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;

interface LootingEvaluator{

	/**
	 * Returns the looting level in effect for a roll.
	 *
	 * @return int<0, max>
	 */
	public function evaluate(LootContext $context) : int;
}