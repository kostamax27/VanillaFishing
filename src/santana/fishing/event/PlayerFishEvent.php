<?php

declare(strict_types=1);

namespace santana\fishing\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class PlayerFishEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		private Item $fishingRod,
		private Item $loot,
		private int $experience,
	){
		$this->player = $player;
	}

	public function getFishingRod() : Item{
		return clone $this->fishingRod;
	}

	public function getLoot() : Item{
		return clone $this->loot;
	}

	public function setLoot(Item $loot) : void{
		$this->loot = clone $loot;
	}

	public function getExperience() : int{
		return $this->experience;
	}

	public function setExperience(int $experience) : void{
		$this->experience = $experience;
	}
}
