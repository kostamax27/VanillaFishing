<?php

declare(strict_types=1);

namespace santana\fishing\item;

use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use santana\fishing\entity\FishingHook;
use function mt_rand;

final class FishingRod extends \pocketmine\item\FishingRod{
	/** @var \WeakMap<Player, FishingHook> */
	protected static \WeakMap $hooked;

	public static function getHooked(Player $player) : ?FishingHook{
		if(!isset(self::$hooked)){
			self::$hooked = new \WeakMap();
		}
		if(isset(self::$hooked[$player]) && !self::$hooked[$player]->isClosed()){
			return self::$hooked[$player];
		}
		return null;
	}

	public static function setHooked(Player $player, ?FishingHook $hook) : void{
		self::getHooked($player)?->flagForDespawn();
		if($hook !== null){
			self::$hooked[$player] = $hook;
		}else{
			unset(self::$hooked[$player]);
		}
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		$entity = self::getHooked($player);
		if($entity !== null && !$entity->isFlaggedForDespawn()){
			$changed = false;
			if($entity->isCaught()){
				$changed = true;
				$this->applyDamage(1);
			}
			if($entity->getTargetEntity() !== null){
				$changed = true;
				$this->applyDamage(mt_rand(1, 2));
			}
			$entity->reelLine();
			self::setHooked($player, null);
			return $changed ? ItemUseResult::SUCCESS : ItemUseResult::NONE;
		}

		$location = $player->getLocation();
		$location->y += $player->getEyeHeight();
		$entity = new FishingHook($location, $player, null);
		$entity->setMotion($player->getDirectionVector()->multiply(0.7));

		$ev = new ProjectileLaunchEvent($entity);
		$ev->call();
		if($ev->isCancelled()){
			$ev->getEntity()->flagForDespawn();
			return ItemUseResult::FAIL;
		}
		$entity->spawnToAll();
		$location->getWorld()->addSound($location, new ThrowSound(), [$player]);
		self::setHooked($player, $entity);

		return ItemUseResult::NONE;
	}
}
