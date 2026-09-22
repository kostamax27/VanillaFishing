<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\event;

use kostamax27\vanillafishing\FishingHook;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called when a flying hook reaches an entity, before it deals its 0-damage
 * hit and sticks. Cancel and the hook passes through.
 */
final class PlayerFishingHookEntityEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		readonly public FishingHook $hook,
		readonly public Entity $entity
	){
		$this->player = $player;
	}
}
