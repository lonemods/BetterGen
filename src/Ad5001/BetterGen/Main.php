<?php
/**
 *  ____             __     __                    ____
 * /\  _`\          /\ \__ /\ \__                /\  _`\
 * \ \ \L\ \     __ \ \ ,_\\ \ ,_\     __   _ __ \ \ \L\_\     __     ___
 *  \ \  _ <'  /'__`\\ \ \/ \ \ \/   /'__`\/\`'__\\ \ \L_L   /'__`\ /' _ `\
 *   \ \ \L\ \/\  __/ \ \ \_ \ \ \_ /\  __/\ \ \/  \ \ \/, \/\  __/ /\ \/\ \
 *    \ \____/\ \____\ \ \__\ \ \__\\ \____\\ \_\   \ \____/\ \____\\ \_\ \_\
 *     \/___/  \/____/  \/__/  \/__/ \/____/ \/_/    \/___/  \/____/ \/_/\/_/
 * Tomorrow's pocketmine generator.
 * @author Ad5001
 * @link https://github.com/Ad5001/BetterGen
 */

namespace Ad5001\BetterGen;

use Ad5001\BetterGen\biome\BetterForest;
use Ad5001\BetterGen\generator\BetterNormal;
use Ad5001\BetterGen\loot\LootTable;
use Ad5001\BetterGen\structure\FallenTree;
use Ad5001\BetterGen\structure\Igloo;
use Ad5001\BetterGen\structure\SakuraTree;
use Ad5001\BetterGen\structure\Temple;
use Ad5001\BetterGen\structure\Well;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\object\OakTree;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\Random;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {
	const PREFIX = "§l§o§b[§r§l§2Better§aGen§o§b]§r§f ";
	const SAKURA_FOREST = 100; // Letting some place for future biomes.
	/** @var Main */
	private static $instance;


	/**
	 * Registers a biome for the normal generator. Normal means(Biome::register) doesn't allow biome to be generated
	 * @param $id int
	 * @param $biome Biome
	 * @return void
	 */
	public static function registerBiome(int $id, Biome $biome) {
		BetterNormal::registerBiome($biome);
	}

	/**
	 * Places a looting chest block and creates the corresponding tile
	 * @param Block $block
	 * @param $lootfile
	 */
	static public function placeLootChest(Block $block, $lootfile) {
		$block->getLevel()->setBlock($block, $block, true);
		$nbt = new CompoundTag("", [
			new StringTag("id", Tile::CHEST),
			new IntTag("x", (int)$block->x),
			new IntTag("y", (int)$block->y),
			new IntTag("z", (int)$block->z),
			new StringTag("generateLoot", $lootfile)
		]);
		$tile = new Chest($block->getLevel(), $nbt);
		$tile->spawnToAll();
	}

	/**
	 * Called when the plugin enables
	 */
	public function onEnable() {
		self::$instance = $this;
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		Generator::addGenerator(BetterNormal::class, "betternormal");
		if ($this->isOtherNS()) $this->getLogger()->warning("Tesseract detected. Note that Tesseract is not up to date with the generation structure and some generation features may be limited or not working");
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder() . 'addon');
		if ((($files = @scandir($this->getDataFolder() . 'addon')) && count($files) <= 2)) $this->getLogger()->alert('The loot files are missing, this means no loot will generate! You can get them here: https://aka.ms/behaviorpacktemplate or here https://github.com/dktapps/mcpe-default-addon for an optimised version');
	}

	/**
	 * Check if it's a Tesseract like namespace
	 * @return    bool
	 */
	public static function isOtherNS() {
		try {
			return @class_exists("pocketmine\\level\\generator\\normal\\object\\OakTree");
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Called when the plugin disables
	 */
	public function onDisable() {
	}

	/**
	 * Called when one of the defined commands of the plugin has been called
	 * @param $sender \pocketmine\command\CommandSender
	 * @param $cmd \pocketmine\command\Command
	 * @param $label mixed
	 * @param $args array
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args): bool {
		switch ($cmd->getName()) {
			case "createworld": // /createworld <name> [generator = betternormal] [seed = rand()] [options(json)]
				switch (count($args)) {
					case 0 :
						return false;
						break;
					case 1 : // /createworld <name>
						$name = $args[0];
						$generator = Generator::getGenerator("betternormal");
						$generatorName = "betternormal";
						$seed = $this->generateRandomSeed();
						$options = [];
						break;
					case 2 : // /createworld <name> [generator = betternormal]
						$name = $args[0];
						$generator = Generator::getGenerator($args[1]);
						if (Generator::getGeneratorName($generator) !== strtolower($args[1])) {
							$sender->sendMessage(self::PREFIX . "§4Could not find generator {$args[1]}. Are you sure it is registered?");
							return true;
						}
						$generatorName = strtolower($args[1]);
						$seed = $this->generateRandomSeed();
						$options = [];
						break;
					case 3 : // /createworld <name> [generator = betternormal] [seed = rand()]
						$name = $args[0];
						$generator = Generator::getGenerator($args[1]);
						if (Generator::getGeneratorName($generator) !== strtolower($args[1])) {
							$sender->sendMessage(self::PREFIX . "§4Could not find generator {$args[1]}. Are you sure it is registered?");
							return true;
						}
						$generatorName = strtolower($args[1]);
						if (preg_match("[^\d]", $args[2]) !== false) {
							$parts = str_split($args[2]);
							foreach ($parts as $key => $str) {
								$parts[$key] = ord($str);
							}
							$seed = implode("", $parts);
						} else {
							$seed = $args[2];
						}
						$options = [];
						break;
					default : // /createworld <name> [generator = betternormal] [seed = rand()] [options(json)]
						$name = $args[0];
						$generator = Generator::getGenerator($args[1]);
						if (Generator::getGeneratorName($generator) !== strtolower($args[1])) {
							$sender->sendMessage(self::PREFIX . "§4Could not find generator {$args[1]}. Are you sure it is registered?");
							return true;
						}
						$generatorName = strtolower($args[1]);
						if ($args[2] == "rand") $args[2] = $this->generateRandomSeed();
						if (preg_match("[^\d]", $args[2]) !== false) {
							$parts = str_split($args[2]);
							foreach ($parts as $key => $str) {
								$parts[$key] = ord($str);
							}
							$seed = implode("", $parts);
						} else {
							$seed = $args[2];
						}
						unset($args[0], $args[1], $args[2]);
						$options = json_decode($args[3], true);
						if (!is_array($options)) {
							$sender->sendMessage(Main::PREFIX . "§4Invalid JSON for options.");
							return true;
						}
						break;
				}
				$options["preset"] = json_encode($options);
				if ((int)$seed == 0/*String*/) {
					$seed = $this->generateRandomSeed();
				}
				$this->getServer()->broadcastMessage(Main::PREFIX . "§aGenerating level $name with generator $generatorName and seed $seed..");
				$this->getServer()->generateLevel($name, $seed, $generator, $options);
				$this->getServer()->loadLevel($name);
				return true;
				break;
			case "worldtp":
				if (!$sender instanceof Player) {
					$sender->sendMessage(TextFormat::RED . 'You can\'t use this command');
					return true;
				}
				/** @var Player $sender */
				if (isset($args[0])) {
					if (is_null($this->getServer()->getLevelByName($args[0]))) {
						$this->getServer()->loadLevel($args[0]);
						if (is_null($this->getServer()->getLevelByName($args[0]))) {
							$sender->sendMessage("Could not find level {$args[0]}.");
							return false;
						}
					}
					$sender->teleport(Position::fromObject($sender, $this->getServer()->getLevelByName($args[0])));
					$sender->sendMessage("§aTeleporting to {$args[0]}...");
					return true;
				} else {
					return false;
				}
				break;
			case 'structure': {
				if (!$sender instanceof Player) {
					$sender->sendMessage(TextFormat::RED . 'You can\'t use this command');
					return true;
				}
				/** @var Player $sender */
				if (isset($args[0])) {
					switch ($args[0]) {
						case 'temple': {
							$temple = new Temple();
							$temple->placeObject($sender->getLevel(), $sender->x, $sender->y, $sender->z, new Random(microtime()));
							return true;
						}
							break;
						case 'fallen': {
							$temple = new FallenTree(new OakTree());
							$temple->placeObject($sender->getLevel(), $sender->x, $sender->y, $sender->z);
							return true;
						}
							break;
						case 'igloo': {
							$temple = new Igloo();
							$temple->placeObject($sender->getLevel(), $sender->x, $sender->y, $sender->z, new Random(microtime()));
							return true;
						}
							break;
						case 'well': {
							$temple = new Well();
							$temple->placeObject($sender->getLevel(), $sender->x, $sender->y, $sender->z, new Random(microtime()));
							return true;
						}
							break;
						case 'sakura': {
							$temple = new SakuraTree();
							$temple->placeObject($sender->getLevel(), $sender->x, $sender->y, $sender->z, new Random(microtime()));
							return true;
						}
							break;
						default: {
						}
					}
				}
				$sender->sendMessage(implode(', ', ['temple', 'fallen', 'igloo', 'well', 'sakura']));
				return true;
			}
		}
		return false;
	}

	/**
	 * Generates a(semi) random seed.
	 * @return int
	 */
	public function generateRandomSeed(): int {
		return (int)round(rand(0, round(time()) / memory_get_usage(true)) * (int)str_shuffle("127469453645108") / (int)str_shuffle("12746945364"));
	}

	/**
	 * Registers a forest type.
	 * @param $name string
	 * @param $treeClass string
	 * @param array $infos
	 * @return bool
	 * @params $infos Array(temperature, rainfall)
	 */
	public function registerForest(string $name, string $treeClass, array $infos): bool {
		if (!@class_exists($treeClass))
			return false;
		if (!@is_subclass_of($treeClass, "pocketmine\\level\\generator\\normal\\object\\Tree"))
			return false;
		if (count($infos) < 2 or !is_float($infos[0]) or !is_float($infos[1]))
			return false;
		return BetterForest::registerForest($name, $treeClass, $infos);
	}

	public function onChunkLoad(ChunkLoadEvent $event) {
		if ($event->getLevel()->getProvider()->getGenerator() === "betternormal") {
			$chunk = $event->getChunk();
			for ($x = 0; $x < 16; $x++) {
				for ($z = 0; $z < 16; $z++) {
					for ($y = 0; $y <= Level::Y_MAX; $y++) {
						$id = $chunk->getBlockId($x, $y, $z);
						$tile = $chunk->getTile($x, $y, $z);
						if($id === Block::CHEST and $tile === null) {
							/** @var Chest $tile */
							$tile = Tile::createTile(Tile::CHEST, $event->getLevel(), Chest::createNBT($pos = new Vector3($chunk->getX() * 16 + $x, $y, $chunk->getZ() * 16 + $z), null)); //TODO: set face correctly
							$table = new LootTable($config = new Config(self::$instance->getDataFolder() . 'addon\\' . $tile->namedtag->generateLoot . '.json', Config::DETECT, []));
							$size = $tile->getInventory()->getSize();
							$loot = $table->getRandomLoot(null);
							$items = array_pad($loot, $size, Item::get(Item::AIR));
							shuffle($items);
							$tile->getInventory()->setContents($items);
						}
					}
				}
			}
		}
	}

	/**
	 * @return Main
	 */
	static public function getInstance() {
		return self::$instance;
	}
}
