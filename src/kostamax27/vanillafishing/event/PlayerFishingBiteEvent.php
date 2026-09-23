<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing\event;

use kostamax27\vanillafishing\FishingHook;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

/**
 * Called when a fish reaches the hook. $reel_ticks is how long the player
 * has to reel in; cancel and the fish swims off, the hook goes back to
 * waiting.
 */
final class PlayerFishingBiteEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		readonly public FishingHook $hook,
		public int $reel_ticks
	){
		$this->player = $player;
	}
}