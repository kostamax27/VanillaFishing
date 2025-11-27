<?php

declare(strict_types=1);

namespace santana\fishing\entity;

use kostamax27\VanillaLootTables\LootContext;
use kostamax27\VanillaLootTables\LootTableFactory;
use pocketmine\block\Air;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\WaterParticle;
use santana\fishing\event\PlayerFishEvent;
use santana\fishing\item\FishingRod;
use Symfony\Component\Filesystem\Path;
use function abs;
use function array_key_first;
use function mt_rand;
use function sqrt;

final class FishingHook extends Projectile implements NeverSavedWithChunkEntity{
	public static function getNetworkTypeId() : string{ return EntityIds::FISHING_HOOK; }

	private int $waitingTimer = 120;

	private bool $attracted = false;
	private bool $caught = false;

	private int $caughtTimer = 0;

	private ?Vector3 $fish = null;

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.15, 0.15); }

	protected function getInitialDragMultiplier() : float{ return 0.02; }

	protected function getInitialGravity() : float{ return 0.05; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->waitingTimer = mt_rand(20, 40);
	}

	public function isCaught() : bool{
		return $this->caught;
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		parent::onHitEntity($entityHit, $hitResult);
		$this->setTargetEntity($entityHit);
	}

	protected function despawnsOnEntityHit() : bool{
		return false;
	}

	public function canCollideWith(Entity $entity) : bool{
		return $entity->getId() !== $this->ownerId && $this->getTargetEntity() === null;
	}

	public function onUpdate(int $currentTick) : bool{
		$target = $this->getTargetEntity();
		if($target !== null){
			if(!$target->isAlive()){
				$this->setTargetEntity(null);
			}else{
				$this->setPositionAndRotation($target->getPosition()->add(0, $target->getEyeHeight(), 0), 0, 0);
				$this->setForceMovementUpdate();
			}
		}
		$player = $this->getOwningEntity();
		if($player === null || ($player instanceof Player && $this->getPosition()->distance($player->getPosition()->asVector3()) >= 32)){
			$this->flagForDespawn();
		}
		$hasUpdate = parent::onUpdate($currentTick);
		if(!$hasUpdate){
			return false;
		}
		if($this->isUnderwater()){
			$this->motion->x = 0;
			$this->motion->y = 0.16;
			$this->motion->z = 0;
		}elseif($this->isCollided && $this->keepMovement){
			$this->motion->x = 0;
			$this->motion->z = 0;
			$this->keepMovement = false;
		}
		if($this->isUnderwater()){
			if(!$this->attracted){
				if($this->waitingTimer > 0){
					--$this->waitingTimer;
				}
				if($this->waitingTimer === 0){
					$this->spawnFish();
					$this->caught = false;
					$this->attracted = true;
				}
			}elseif(!$this->caught){
				if($this->attractFish()){
					$random = new Random();
					//                  Vanilla: $this->caughtTimer = $random->nextBoundedInt(20) + 40;
					$this->caughtTimer = $random->nextBoundedInt(20) + 20;
					$this->fishBites();
					$this->caught = true;
				}
			}else{
				if($this->caughtTimer > 0){
					--$this->caughtTimer;
				}
				if($this->caughtTimer === 0){
					$this->attracted = false;
					$this->caught = false;
					//                  Vanilla: $this->waitingTimer = mt_rand(100, 600);
					$this->waitingTimer = mt_rand(40, 60);
				}
			}
		}
		return true;
	}

	public function getWaterHeight() : int{
		for($y = $this->getPosition()->getFloorY(); $y < 256; $y++){
			$block = $this->getWorld()->getBlockAt($this->getPosition()->getFloorX(), $y, $this->getPosition()->getFloorZ());
			if($block instanceof Air){
				return $y;
			}
		}
		return $this->getPosition()->getFloorY();
	}

	public function spawnFish() : void{
		$random = new Random();
		$position = $this->getPosition();
		$this->fish = new Vector3(
			$position->x + ($random->nextFloat() * 1.2 + mt_rand(1, 4)) * ($random->nextFloat() ? -1 : 1),
			$this->getWaterHeight(),
			$position->z + ($random->nextFloat() * 1.2 + mt_rand(1, 4)) * ($random->nextFloat() ? -1 : 1)
		);
	}

	public function reelLine() : void{
		if($this->getOwningEntity() instanceof Player){
			/** @var Player $player */
			$player = $this->getOwningEntity();
			if($this->caught){
				$position = $this->getPosition();
				$motion = $player->getPosition()->subtractVector($position)->multiply(0.1);
				$motion->y += sqrt($player->getPosition()->distance($position)) * 0.08;

				$biomeId = $position->getWorld()->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());

				$lootTable = match ($biomeId) {
					BiomeIds::JUNGLE, BiomeIds::JUNGLE_HILLS, BiomeIds::JUNGLE_EDGE => "jungle_fishing.json",
					default => "fishing.json",
				};

				$table = LootTableFactory::getInstance()->get(Path::join("gameplay", $lootTable));
				if($table !== null){
					$fishingRod = $player->getInventory()->getItemInHand();

					$loots = $table->generate(new LootContext($position->getWorld(), $fishingRod, $player));
					$loot = $loots[array_key_first($loots)] ?? null;

					if($loot !== null){
						$event = new PlayerFishEvent($player, $fishingRod, $loot, mt_rand(1, 3));
						$event->call();
						if(!$event->isCancelled()){
							$itemEntity = $player->getWorld()->dropItem($position, $event->getLoot(), $motion);
							if($itemEntity !== null){
								$player->getXpManager()->addXp($event->getExperience());
							}
						}
					}
				}
			}
			if($this->getTargetEntity() !== null){
				$motion = $player->getDirectionVector()->multiply(-1);
				$this->getTargetEntity()->setMotion($motion);
			}
		}
		$this->flagForDespawn();
	}

	public function attractFish() : bool{
		$multiply = 0.1;
		$position = $this->getPosition();
		$this->fish = $this->fish->withComponents(
			$this->fish->x + ($position->x - $this->fish->x) * $multiply,
			$this->fish->y,
			$this->fish->z + ($position->z - $this->fish->z) * $multiply
		);
		$this->getWorld()->addParticle($this->fish, new WaterParticle());
		$dist = abs(sqrt($position->x * $position->x + $position->z * $position->z) - sqrt($this->fish->x * $this->fish->x + $this->fish->z * $this->fish->z));
		return $dist < 0.15;
	}

	public function fishBites() : void{
		NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [
			ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_HOOK, 0),
			ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_BUBBLE, 0),
			ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_TEASE, 0),
		]);

		$random = new Random();
		$position = $this->getPosition();
		for($i = 0; $i < 5; $i++){
			$this->getWorld()->addParticle($position->withComponents($position->x + $random->nextFloat() * 0.5 - 0.25, $this->getWaterHeight(), $position->z + $random->nextFloat() * 0.5 - 0.25), new BubbleParticle());
		}
		--$this->motion->y;
	}

	protected function onDispose() : void{
		$player = $this->getOwningEntity();
		if($player instanceof Player){
			FishingRod::setHooked($player, null);
		}
		parent::onDispose();
	}
}
