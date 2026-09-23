<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing;

use kostamax27\vanillafishing\event\PlayerFishingBiteEvent;
use kostamax27\vanillafishing\event\PlayerFishingHookEntityEvent;
use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\FishingRod;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\sound\WaterSplashSound;
use function cos;
use function deg2rad;
use function floor;
use function max;
use function min;
use function sin;

final class FishingHook extends Projectile implements NeverSavedWithChunkEntity{

	private const STATE_WAITING = 0;
	private const STATE_APPROACHING = 1;
	private const STATE_HOOKED = 2;

	private const MAX_DISTANCE_SQUARED = 32 ** 2;

	private const AIR_GRAVITY = 0.042;
	private const AIR_DRAG = 0.953;

	private const WATER_ENTRY_XZ = 0.82;
	private const WATER_ENTRY_Y = 0.6;
	private const WATER_DRAG_XZ = 0.5;
	private const WATER_BUOYANCY_WINDOW = 0.59;

	public static function getNetworkTypeId() : string{ return EntityIds::FISHING_HOOK; }

	private int $state = self::STATE_WAITING;
	private int $countdown = 0;
	private ?Entity $attached = null;

	private float $fish_x = 0.0;
	private float $fish_z = 0.0;
	private float $fish_angle = 0.0;

	private ?Vector3 $velocity = null;
	private bool $spawn_tick = true;
	private bool $in_water = false;

	public function __construct(
		Location $location,
		readonly private Player $owner,
		readonly private Random $random,
		readonly private int $lure_level
	){
		parent::__construct($location, $owner);
		$this->countdown = $this->waitTicks();
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.15, 0.15); }

	protected function getInitialDragMultiplier() : float{ return 0.047; }

	protected function getInitialGravity() : float{ return 0.042; }

	protected function despawnsOnEntityHit() : bool{ return false; }

	public function getOwner() : Player{
		return $this->owner;
	}

	public function isHooked() : bool{
		return $this->state === self::STATE_HOOKED;
	}

	public function getAttached() : ?Entity{
		return $this->attached !== null && $this->attached->isAlive() && $this->attached->getWorld() === $this->getWorld() ? $this->attached : null;
	}

	public function isStuckInGround() : bool{
		return $this->attached === null && $this->blockHit !== null;
	}

	public function onUpdate(int $currentTick) : bool{
		$this->in_water = $this->getWorld()->getBlock($this->location) instanceof Water;
		return parent::onUpdate($currentTick);
	}

	public function hasMovementUpdate() : bool{
		return $this->attached === null && ($this->in_water || parent::hasMovementUpdate());
	}

	public function canCollideWith(Entity $entity) : bool{
		return $entity !== $this->owner && $this->attached === null && parent::canCollideWith($entity);
	}

	protected function tryChangeMovement() : void{
		$motion = $this->motion;
		if($this->in_water){
			if($this->velocity !== null){
				$this->velocity = null;
				$motion->x *= self::WATER_ENTRY_XZ;
				$motion->y *= self::WATER_ENTRY_Y;
				$motion->z *= self::WATER_ENTRY_XZ;
				return;
			}
			$below = max(0.0, min(1.0, ($this->waterSurface() - $this->location->y) / self::WATER_BUOYANCY_WINDOW));
			$motion->x *= self::WATER_DRAG_XZ;
			$motion->z *= self::WATER_DRAG_XZ;
			$motion->y = 0.4 * $motion->y + 0.069 - (0.077 - ($this->random->nextFloat() - 0.5) * 0.02) * (1 - $below);
			return;
		}
		$velocity = $this->velocity ??= clone $motion;
		$motion->x = $motion->y = $motion->z = 0.0;
		for($i = 0; $i < 2; ++$i){
			$velocity->x *= self::AIR_DRAG;
			$velocity->z *= self::AIR_DRAG;
			$velocity->y = $velocity->y * self::AIR_DRAG - ($this->spawn_tick && $i === 0 ? self::AIR_GRAVITY / 2 : self::AIR_GRAVITY);
			$motion->x += $velocity->x;
			$motion->y += $velocity->y;
			$motion->z += $velocity->z;
		}
		if($this->spawn_tick){
			$this->spawn_tick = false;
			$motion->x *= 0.99;
			$motion->y *= 0.99;
			$motion->z *= 0.99;
		}
	}

	private function waterSurface() : int{
		$world = $this->getWorld();
		$x = (int) floor($this->location->x);
		$y = (int) floor($this->location->y);
		$z = (int) floor($this->location->z);
		while($world->getBlockAt($x, $y + 1, $z) instanceof Water){
			++$y;
		}
		return $y + 1;
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		($ev = new PlayerFishingHookEntityEvent($this->owner, $this, $entityHit))->call();
		if($ev->isCancelled()){
			return;
		}
		parent::onHitEntity($entityHit, $hitResult);
		$this->attached = $entityHit;
		$this->state = self::STATE_WAITING;
		$this->setTargetEntity($entityHit);
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		$this->velocity = null;
		if(!$this->in_water){
			parent::onHitBlock($blockHit, $hitResult);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$has_update = parent::entityBaseTick($tickDiff);
		if($this->isFlaggedForDespawn()){
			return $has_update;
		}
		if(!$this->ownerCanHold()){
			$this->flagForDespawn();
			return true;
		}
		if($this->attached !== null){
			$attached = $this->getAttached();
			if($attached === null){
				$this->flagForDespawn();
				return true;
			}
			$this->setPosition($attached->getPosition()->add(0, $attached->getSize()->getHeight() * 0.8, 0));
			return true;
		}
		if($this->in_water){
			$this->tickFishing($tickDiff);
		}elseif($this->state !== self::STATE_WAITING){
			$this->state = self::STATE_WAITING;
			$this->countdown = $this->waitTicks();
		}
		return true;
	}

	private function ownerCanHold() : bool{
		return $this->owner->isConnected()
			&& $this->owner->isAlive()
			&& $this->owner->getWorld() === $this->getWorld()
			&& $this->owner->getInventory()->getItemInHand() instanceof FishingRod
			&& $this->owner->getPosition()->distanceSquared($this->location) <= self::MAX_DISTANCE_SQUARED;
	}

	private function tickFishing(int $tick_diff) : void{
		$this->countdown -= $tick_diff;
		switch($this->state){
			case self::STATE_WAITING:
				$chance = 0.15;
				if($this->countdown < 20){
					$chance += (20 - $this->countdown) * 0.05;
				}elseif($this->countdown < 40){
					$chance += (40 - $this->countdown) * 0.02;
				}elseif($this->countdown < 60){
					$chance += (60 - $this->countdown) * 0.01;
				}
				if($this->random->nextFloat() < $chance){
					$this->tease();
				}
				if($this->countdown <= 0){
					$this->state = self::STATE_APPROACHING;
					$this->countdown = $this->random->nextRange(20, 80);
					$this->fish_angle = $this->random->nextFloat() * 360;
				}
				break;
			case self::STATE_APPROACHING:
				$this->fish_angle += ($this->random->nextFloat() - $this->random->nextFloat()) * 9.188;
				$this->fish_x = sin(deg2rad($this->fish_angle)) * $this->countdown * 0.1;
				$this->fish_z = cos(deg2rad($this->fish_angle)) * $this->countdown * 0.1;
				$this->broadcastFish(ActorEvent::FISH_HOOK_POSITION);
				if($this->countdown <= 0){
					($ev = new PlayerFishingBiteEvent($this->owner, $this, $this->random->nextRange(20, 40)))->call();
					if($ev->isCancelled()){
						$this->state = self::STATE_WAITING;
						$this->countdown = $this->waitTicks();
						break;
					}
					$this->state = self::STATE_HOOKED;
					$this->countdown = $ev->reel_ticks;
					$this->motion->y = -0.4 * (0.6 + $this->random->nextFloat() * 0.4);
					$this->broadcastEvent(ActorEvent::FISH_HOOK_HOOK);
					$this->getWorld()->addSound($this->location, new WaterSplashSound(0.25));
				}
				break;
			case self::STATE_HOOKED:
				if($this->countdown <= 0){
					$this->state = self::STATE_WAITING;
					$this->countdown = $this->waitTicks();
				}
				break;
		}
	}

	private function tease() : void{
		$angle = deg2rad($this->random->nextFloat() * 360);
		$distance = 2.5 + $this->random->nextFloat() * 3.5;
		$x = $this->location->x + sin($angle) * $distance;
		$z = $this->location->z + cos($angle) * $distance;
		if(!($this->getWorld()->getBlockAt((int) floor($x), (int) floor($this->location->y), (int) floor($z)) instanceof Water)){
			return;
		}
		$this->fish_x = $x;
		$this->fish_z = $z;
		$this->broadcastFish(ActorEvent::FISH_HOOK_TEASE);
	}

	private function broadcastFish(int $event) : void{
		$this->broadcastEvent($event);
		$this->networkPropertiesDirty = true;
		$this->sendData(null, $this->getDirtyNetworkData());
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setFloat(EntityMetadataProperties::FISH_X, $this->fish_x);
		$properties->setFloat(EntityMetadataProperties::FISH_Z, $this->fish_z);
		$properties->setFloat(EntityMetadataProperties::FISH_ANGLE, $this->fish_angle);
	}

	private function waitTicks() : int{
		return max(20, $this->random->nextRange(100, 600) - $this->lure_level * 100);
	}

	private function broadcastEvent(int $event) : void{
		$this->getWorld()->broadcastPacketToViewers($this->location, ActorEventPacket::create($this->getId(), $event, 0, null));
	}
}