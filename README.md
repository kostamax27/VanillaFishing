# VanillaFishing

Vanilla fishing for PocketMine-MP. Cast with a fishing rod, wait for the bobber to dip, use the rod again to reel in.
Hooks stick to mobs and pull them on reel.

Drops are rolled from the vanilla `loot_tables/gameplay/fishing.json` (jungle biomes use `jungle_fishing.json`)
with [LootTable](https://github.com/kostamax27/LootTable). The tables are saved to the plugin's data folder on first run
and can be edited there.

## Enchantments

`luck_of_the_sea` and `lure` are registered on enable unless another plugin already provides them. `luck_of_the_sea`
raises the roll's luck, `lure` shortens the wait by 100 ticks per level.

## Events

| Event                          | When                         |
|--------------------------------|------------------------------|
| `PlayerFishingCastEvent`       | before the hook spawns       |
| `PlayerFishingHookEntityEvent` | hook reaches a living entity |
| `PlayerFishingBiteEvent`       | fish reaches the hook        |
| `PlayerFishingCatchEvent`      | reeling in a bite            |
| `PlayerFishingPullEvent`       | reeling in a hooked entity   |

```php
$this->getServer()->getPluginManager()->registerEvent(PlayerFishingCatchEvent::class, static function(PlayerFishingCatchEvent $event) : void{
	if($event->getPlayer()->hasPermission("myplugin.fishing.double")){
		$event->drops = [...$event->drops, ...$event->table->generate($event->context)];
	}
}, EventPriority::NORMAL, $this);
```
