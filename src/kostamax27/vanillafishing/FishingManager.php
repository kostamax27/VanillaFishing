<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing;

use Closure;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootContext;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootDifficulty;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootTable;
use kostamax27\vanillafishing\event\PlayerFishingCastEvent;
use kostamax27\vanillafishing\event\PlayerFishingCatchEvent;
use kostamax27\vanillafishing\event\PlayerFishingPullEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\FishingRod;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Random;
use pocketmine\world\sound\ItemBreakSound;
use pocketmine\world\sound\ThrowSound;
use function cos;
use function deg2rad;
use function sin;
use function sqrt;

final class FishingManager{

	private const CAST_POWER = 1.3;

	private const ROD_DAMAGE_FISH = 1;
	private const ROD_DAMAGE_GROUND = 2;
	private const ROD_DAMAGE_ENTITY = 5;

	private const PULL_SCALE = 0.1;
	private const PULL_LIFT = 0.1;

	/** @var array<int, FishingHook> player id => hook */
	private array $hooks = [];

	/** @var list<Closure() : void>|null */
	private ?array $listeners = null;

	/**
	 * @param Closure(FishingHook) : LootTable $table_selector
	 * @param FishingEnchantments $enchantments
	 * @param Random $random
	 */
	public function __construct(
		readonly private Closure $table_selector,
		readonly private FishingEnchantments $enchantments,
		readonly private Random $random
	){}

	public function init(Plugin $plugin) : void{
		if($this->listeners !== null){
			return;
		}
		$manager = $plugin->getServer()->getPluginManager();
		$use = $manager->registerEvent(PlayerItemUseEvent::class, function(PlayerItemUseEvent $event) : void{
			$rod = $event->getItem();
			if(!$rod instanceof FishingRod){
				return;
			}
			$player = $event->getPlayer();
			if($this->getHook($player) !== null){
				$this->reel($player, $rod);
			}else{
				$this->cast($player, $rod);
			}
		}, EventPriority::NORMAL, $plugin);
		$held = $manager->registerEvent(PlayerItemHeldEvent::class, function(PlayerItemHeldEvent $event) : void{
			if(!$event->getItem() instanceof FishingRod){
				$this->retract($event->getPlayer());
			}
		}, EventPriority::MONITOR, $plugin);
		$quit = $manager->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			$this->retract($event->getPlayer());
		}, EventPriority::MONITOR, $plugin);
		$this->listeners = [
			static function() use ($use, $held, $quit) : void{
				$handlers = HandlerListManager::global();
				$handlers->unregisterAll($use);
				$handlers->unregisterAll($held);
				$handlers->unregisterAll($quit);
			},
			function() : void{
				foreach($this->hooks as $hook){
					if(!$hook->isClosed()){
						$hook->flagForDespawn();
					}
				}
				$this->hooks = [];
			},
		];
	}

	public function destroy() : void{
		if($this->listeners === null){
			return;
		}
		foreach($this->listeners as $listener){
			$listener();
		}
		$this->listeners = null;
	}

	public function getHook(Player $player) : ?FishingHook{
		$hook = $this->hooks[$player->getId()] ?? null;
		if($hook !== null && ($hook->isClosed() || $hook->isFlaggedForDespawn())){
			unset($this->hooks[$player->getId()]);
			return null;
		}
		return $hook;
	}

	public function getTableAt(FishingHook $hook) : LootTable{
		return ($this->table_selector)($hook);
	}

	public function cast(Player $player, FishingRod $rod) : void{
		($ev = new PlayerFishingCastEvent($player, $rod, $player->getDirectionVector()->multiply(self::CAST_POWER)))->call();
		if($ev->isCancelled()){
			return;
		}
		$this->retract($player);
		$location = $player->getLocation();
		$yaw = deg2rad($location->getYaw());
		$hook = new FishingHook(
			Location::fromObject($player->getEyePos()->add(-sin($yaw) * 0.15, -0.378, cos($yaw) * 0.15), $location->getWorld(), $location->getYaw(), $location->getPitch()),
			$player,
			$this->random,
			$rod->getEnchantmentLevel($this->enchantments->lure)
		);
		$hook->setMotion($ev->motion);
		$hook->spawnToAll();
		$player->getWorld()->addSound($player->getPosition(), new ThrowSound());
		$this->hooks[$player->getId()] = $hook;
	}

	public function reel(Player $player, FishingRod $rod) : void{
		$hook = $this->getHook($player);
		if($hook === null){
			return;
		}
		$attached = $hook->getAttached();
		if($attached !== null){
			$this->pull($player, $hook, $rod, $attached);
		}elseif($hook->isHooked()){
			$this->catch($player, $hook, $rod);
		}elseif($hook->isStuckInGround()){
			$this->damageRod($player, $rod, self::ROD_DAMAGE_GROUND);
		}
		$this->retract($player);
	}

	public function retract(Player $player) : void{
		$hook = $this->getHook($player);
		if($hook !== null){
			$hook->flagForDespawn();
			unset($this->hooks[$player->getId()]);
		}
	}

	private function catch(Player $player, FishingHook $hook, FishingRod $rod) : void{
		$world = $hook->getWorld();
		$table = $this->getTableAt($hook);
		$context = new LootContext(
			random: $this->random,
			killer: $player,
			tool: $rod,
			luck: (float) $rod->getEnchantmentLevel($this->enchantments->luck_of_the_sea),
			difficulty: LootDifficulty::fromWorld($world)
		);
		($ev = new PlayerFishingCatchEvent($player, $hook, $rod, $table, $context, $table->generate($context), $this->random->nextRange(1, 6), self::ROD_DAMAGE_FISH))->call();
		if($ev->isCancelled()){
			return;
		}

		$from = $hook->getPosition()->add(0, 0.13, 0);
		$motion = $this->pullMotion($player, $from);
		foreach($ev->drops as $item){
			$world->dropItem($from, $item, $motion, 0);
		}
		if($ev->experience > 0){
			$world->dropExperience($player->getPosition(), $ev->experience);
		}
		$this->damageRod($player, $rod, $ev->rod_damage);
	}

	private function pull(Player $player, FishingHook $hook, FishingRod $rod, Entity $entity) : void{
		($ev = new PlayerFishingPullEvent($player, $hook, $entity, $this->pullMotion($player, $entity->getPosition()), self::ROD_DAMAGE_ENTITY))->call();
		if($ev->isCancelled()){
			return;
		}
		$entity->setMotion($entity->getMotion()->addVector($ev->motion));
		$this->damageRod($player, $rod, $ev->rod_damage);
	}

	private function pullMotion(Player $player, Vector3 $from) : Vector3{
		$delta = $player->getEyePos()->subtractVector($from);
		return new Vector3($delta->x * self::PULL_SCALE, $delta->y * self::PULL_SCALE + self::PULL_LIFT * sqrt(sqrt($delta->length())), $delta->z * self::PULL_SCALE);
	}

	private function damageRod(Player $player, FishingRod $rod, int $damage) : void{
		if($damage <= 0 || !$player->hasFiniteResources()){
			return;
		}
		$rod->applyDamage($damage);
		if($rod->isBroken()){
			$player->broadcastSound(new ItemBreakSound());
		}
		$player->getInventory()->setItemInHand($rod);
	}
}