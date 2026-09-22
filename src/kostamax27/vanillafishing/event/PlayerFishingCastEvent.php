<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\item\FishingRod;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Called before a fishing hook is spawned. $motion may be changed to alter
 * the cast; cancel to prevent the cast.
 */
final class PlayerFishingCastEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		readonly public FishingRod $rod,
		public Vector3 $motion
	){
		$this->player = $player;
	}
}
