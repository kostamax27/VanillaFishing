<?php

declare(strict_types=1);

namespace santana\fishing;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use santana\fishing\item\FishingRod;

function chance(float $chance) : bool{
	$string = strrchr(strval($chance), ".");
	if($string == false){
		return mt_rand(1, 100) <= $chance;
	}
	$count = strlen(substr($string, 1));
	$multiply = intval("1" . str_repeat("0", $count));
	return mt_rand(1, (100 * $multiply)) <= ($chance * $multiply);
}

final class VanillaFishing extends PluginBase{
	private array $lootTable = [];

	private array $fishing = [];

	use SingletonTrait {
		setInstance as private;
		getInstance as private getSingletonInstance;
	}

	public static function getInstance() : VanillaFishing{
		return self::getSingletonInstance();
	}

	public function getLootTable() : array{
		return $this->lootTable;
	}

	public function setLootTable(array $lootTable) : void{
		$this->lootTable = $lootTable;
	}

	public function getFishing() : array{
		return $this->fishing;
	}

	public function setFishing(array $fishing) : void{
		$this->fishing = $fishing;
	}

	public function getRandomLoot() : Item{
		foreach($this->lootTable as $data){
			if(chance($data["chance"])){
				return clone $data["item"];
			}
		}
		return ItemFactory::air();
	}

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		//TODO: Register Everything
		ItemFactory::getInstance()->register(new FishingRod(new ItemIdentifier(ItemIds::FISHING_ROD, 0), "Fishing Rod"), true);
		$this->registerVanillaLoot();
	}

	/**
	 * Values Taken from https://minecraft.fandom.com/wiki/Fishing
	 */
	protected function registerVanillaLoot() : void{
		// TODO: Add a System for Making Randomly Enchanted Items
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::FISH), 60.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::SALMON), 25.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::CLOWNFISH), 2.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::PUFFERFISH), 13.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::BOW), 16.7);
//      $this->addItemToLootTable($this->getRandomlyEnchantedItem(ItemIds::BOW), 0.8);
//      $this->addItemToLootTable($this->getRandomlyEnchantedItem(ItemIds::BOOK), 0.8);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::FISHING_ROD), 16.7);
//      $this->addItemToLootTable($this->getRandomlyEnchantedItem(ItemIds::FISHING_ROD), 0.8);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::NAMETAG), 16.7);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::NAUTILUS_SHELL), 16.7);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::SADDLE), 16.7);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::LILY_PAD), 17.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::BOWL), 10.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::FISHING_ROD), 2.0); // Add a Randomly Enchanted Bow instead
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::LEATHER), 10.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::LEATHER_BOOTS), 10.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::ROTTEN_FLESH), 10.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::STICK), 5.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::STRING), 5.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::GLASS_BOTTLE), 10.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::BONE), 10.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::DYE), 1.0);
		$this->addItemToLootTable(ItemFactory::getInstance()->get(ItemIds::TRIPWIRE_HOOK), 10.0);
	}

	protected function addItemToLootTable(Item $item, float $chance) : void{
		$this->lootTable[] = [
			"chance" => $chance,
			"item" => $item
		];
	}
}
