<?php

declare(strict_types=1);

namespace kostamax27\vanillafishing;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\StringToEnchantmentParser;

final class FishingEnchantments{
	public static function register() : self{
		return new self(
			self::registerOne("luck_of_the_sea", "Luck of the Sea", EnchantmentIds::LUCK_OF_THE_SEA),
			self::registerOne("lure", "Lure", EnchantmentIds::LURE)
		);
	}

	private static function registerOne(string $name, string $display_name, int $id) : Enchantment{
		$parser = StringToEnchantmentParser::getInstance();
		$existing = $parser->parse($name);
		if($existing !== null){
			return $existing;
		}
		$enchantment = new Enchantment($display_name, Rarity::RARE, 0, 0, 3, static fn(int $level) : int => 15 + ($level - 1) * 9);
		EnchantmentIdMap::getInstance()->register($id, $enchantment);
		$parser->register($name, static fn() : Enchantment => $enchantment);
		AvailableEnchantmentRegistry::getInstance()->register($enchantment, [ItemEnchantmentTags::FISHING_ROD], []);
		return $enchantment;
	}

	private function __construct(
		readonly public Enchantment $luck_of_the_sea,
		readonly public Enchantment $lure
	){}
}
