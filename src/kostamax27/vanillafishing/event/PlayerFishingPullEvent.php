<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\event;

use kostamax27\vanillafishing\FishingHook;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Called when reeling in a hook stuck to an entity. $motion is added to
 * the entity's motion; cancel to release it without pulling.
 */
final class PlayerFishingPullEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		readonly public FishingHook $hook,
		readonly public Entity $entity,
		public Vector3 $motion,
		public int $rod_damage
	){
		$this->player = $player;
	}
}