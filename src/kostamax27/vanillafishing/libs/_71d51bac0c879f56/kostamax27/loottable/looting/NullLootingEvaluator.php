<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\looting;

use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;

final class NullLootingEvaluator implements LootingEvaluator{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function evaluate(LootContext $context) : int{
		return 0;
	}
}