<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\condition;

use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\entity\EntityInspector;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;

final class IsBabyLootCondition implements LootCondition{

	public function __construct(
		readonly public EntityInspector $inspector
	){}

	public function test(LootContext $context) : bool{
		return $context->entity !== null && $this->inspector->isBaby($context->entity);
	}
}