<?php

declare(strict_types=1);

namespace santana\fishing;

use kostamax27\VanillaLootTables\json\LootTableDeserializerHelper;
use kostamax27\VanillaLootTables\LootTableFactory;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use santana\fishing\item\FishingRod;
use Symfony\Component\Filesystem\Path;
use function file_exists;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class VanillaFishing extends PluginBase{
	protected function onEnable() : void{
		//TODO: Register Everything
		$fishingRod = new FishingRod(new ItemIdentifier(ItemTypeIds::FISHING_ROD), "Fishing Rod");
		$creativeEntry = CreativeInventory::getInstance()->getEntry(CreativeInventory::getInstance()->getItemIndex(VanillaItems::FISHING_ROD()));
		if($creativeEntry !== null){
			CreativeInventory::getInstance()->add($fishingRod, $creativeEntry->getCategory(), $creativeEntry->getGroup());
		}
		StringToItemParser::getInstance()->override("fishing_rod", fn() => $fishingRod);
		GlobalItemDataHandlers::getDeserializer()->map(ItemTypeNames::FISHING_ROD, fn() => clone $fishingRod);

		$itemSerializer = GlobalItemDataHandlers::getSerializer();
		$reflIS = new \ReflectionClass($itemSerializer);
		$reflProp = $reflIS->getProperty("itemSerializers");
		$reflProp->setAccessible(true);
		$val = $reflProp->getValue($itemSerializer);
		$val[$fishingRod->getTypeId()] = static fn() => new SavedItemData(ItemTypeNames::FISHING_ROD);
		$reflProp->setValue($itemSerializer, $val);

		$this->registerVanillaLoot();

		$this->getServer()->getPluginManager()->registerEvent(PlayerItemHeldEvent::class, static function(PlayerItemHeldEvent $event) : void{
			$newItem = $event->getItem();
			if($newItem->getTypeId() !== ItemTypeIds::FISHING_ROD){
				$player = $event->getPlayer();
				FishingRod::setHooked($player, null);
			}
		}, EventPriority::MONITOR, $this);
	}

	protected function registerVanillaLoot() : void{
		try{
			$path = Path::join($this->getDataFolder(), "loot_tables");
			if(!file_exists($path)){
				Filesystem::recursiveCopy($this->getResourcePath("loot_tables"), $path);
			}

			$factory = LootTableFactory::getInstance();
			foreach(self::searchJson($path) as $filePath){
				$raw = Filesystem::fileGetContents($filePath);
				try{
					$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
				}catch(\JsonException $e){
					throw new \InvalidArgumentException("JSON parsing failed for loot table \"$filePath\": " . $e->getMessage(), $e->getCode(), $e);
				}
				try{
					$lootTable = LootTableDeserializerHelper::deserializeLootTable($data);
				}catch(SavedDataLoadingException $e){
					throw new \InvalidArgumentException("Deserialization failed for loot table \"$filePath\": " . $e->getMessage(), $e->getCode(), $e);
				}
				$name = Path::makeRelative($filePath, $path);

				$factory->register($lootTable, $name);
			}
		}catch(\InvalidArgumentException $e){
			$this->getLogger()->error($e->getMessage());
			throw new DisablePluginException();
		}
	}

	private static function searchJson(string $dir) : \Generator{
		/** @var \SplFileInfo $file */
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file){
			if($file->isFile() && $file->getExtension() === "json"){
				yield $file->getPathname();
			}
		}
	}

}
