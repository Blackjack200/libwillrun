<?php

declare(strict_types=1);

namespace blackjack200\libwillrun;

use blackjack200\libwillrun\profiler\WillRun;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Filesystem\Path;

class Main extends PluginBase {
	private function checkPocketMineAvailability() : bool {
		$memoryManager = Server::getInstance()->getMemoryManager();
		try {
			$propertyNextTick = new ReflectionProperty(Server::getInstance(), 'nextTick');
			$propertyPeriod = new ReflectionProperty($memoryManager, 'garbageCollectionPeriod');
		} catch (ReflectionException $e) {
			$logger = $this->getLogger();
			$logger->error('Error occurred when checking PocketMine-MP availability.');
			$logger->logException($e);
			return false;
		}
		$typ = $propertyNextTick->getType();
		if ($typ === null) {
			return false;
		}
		if ($typ->getName() !== 'float') {
			return false;
		}
		$typ = $propertyPeriod->getType();
		if ($typ === null) {
			return false;
		}
		if ($typ->getName() !== 'int') {
			return false;
		}
		return true;
	}

	protected function onLoad() : void {
		if (!$this->checkPocketMineAvailability()) {
			$this->getLogger()->info('libwillrun is not compatible with your PocketMine-MP.');
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		require_once __DIR__ . '/willrun.php';
		$profilePath = Path::join($this->getDataFolder(), 'profiles.json');
		if (file_exists($profilePath)) {
			WillRun::$profiles = json_decode(Filesystem::fileGetContents($profilePath), true, 512, JSON_THROW_ON_ERROR);
		}
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => WillRun::poll()), 1);
	}

	protected function onDisable() : void {
		Filesystem::safeFilePutContents(Path::join($this->getDataFolder(), 'profiles.json'), json_encode(WillRun::$profiles, JSON_THROW_ON_ERROR));
		while (!empty(WillRun::$queue)) {
			WillRun::poll();
		}
	}
}

