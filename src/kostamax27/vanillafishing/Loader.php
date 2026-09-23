<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing;

use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootTable;
use kostamax27\vanillafishing\libs\_71d51bac0c879f56\kostamax27\loottable\LootTableFactory;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Random;
use Symfony\Component\Filesystem\Path;
use function array_fill_keys;

final class Loader extends PluginBase{

	public const LOOT_TABLES = ["gameplay/fishing.json", "gameplay/jungle_fishing.json", "gameplay/fishing/fish.json", "gameplay/fishing/junk.json",
		"gameplay/fishing/treasure.json", "gameplay/fishing/jungle_fish.json", "gameplay/fishing/jungle_junk.json"];

	public const JUNGLE_BIOMES = [BiomeIds::JUNGLE, BiomeIds::JUNGLE_HILLS, BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_MUTATED, BiomeIds::JUNGLE_EDGE_MUTATED,
		BiomeIds::BAMBOO_JUNGLE, BiomeIds::BAMBOO_JUNGLE_HILLS];

	private FishingManager $manager;

	protected function onEnable() : void{
		foreach(self::LOOT_TABLES as $file){
			$this->saveResource(Path::join("loot_tables", $file));
		}
		$factory = LootTableFactory::createDefault($this->getDataFolder());
		$fishing = $factory->fromFile(Path::join($this->getDataFolder(), "loot_tables", "gameplay", "fishing.json"));
		$jungle = $factory->fromFile(Path::join($this->getDataFolder(), "loot_tables", "gameplay", "jungle_fishing.json"));

		$jungle_biomes = array_fill_keys(self::JUNGLE_BIOMES, true);
		$this->manager = new FishingManager(static function(FishingHook $hook) use ($fishing, $jungle, $jungle_biomes) : LootTable{
			$position = $hook->getPosition();
			$x = $position->getFloorX();
			$y = $position->getFloorY();
			$z = $position->getFloorZ();
			return isset($jungle_biomes[$hook->getWorld()->getBiomeId($x, $y, $z)]) ? $jungle : $fishing;
		}, FishingEnchantments::register(), new Random());
		$this->manager->init($this);
	}

	protected function onDisable() : void{
		$this->manager->destroy();
	}

	public function getManager() : FishingManager{
		return $this->manager;
	}
}