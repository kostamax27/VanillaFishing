<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\event;

use kostamax27\loottable\LootContext;
use kostamax27\loottable\LootTable;
use kostamax27\vanillafishing\FishingHook;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\item\FishingRod;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Called when a hooked fish is reeled in, after the loot table has been
 * rolled. All non-readonly properties may be modified to change what the
 * player receives; cancel to give nothing (the hook is still retracted).
 */
final class PlayerFishingCatchEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @param LootTable $table the table that was rolled, picked by the biome at the hook
	 * @param LootContext $context what it was rolled with
	 * @param list<Item> $drops
	 */
	public function __construct(
		Player $player,
		readonly public FishingHook $hook,
		readonly public FishingRod $rod,
		readonly public LootTable $table,
		readonly public LootContext $context,
		public array $drops,
		public int $experience,
		public int $rod_damage
	){
		$this->player = $player;
	}
}
