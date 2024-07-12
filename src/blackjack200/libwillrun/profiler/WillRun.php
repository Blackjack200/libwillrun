<?php

namespace blackjack200\libwillrun\profiler;

use Closure;
use pocketmine\Server;

class WillRun {
	public static array $profiles = [];
	private static Closure $getNextTick;
	private static float $secondPerTick = Server::TARGET_SECONDS_PER_TICK;
	private static float $rest = Server::TARGET_SECONDS_PER_TICK / 10;
	public static array $queue = [];
	private static int $maxPolls = 10;

	public static function run(Closure $c, int $depth = 0) : void {
		$id = self::getCallerId($depth);
		$start = microtime(true);
		$tickRemaining = (self::getAvailableTime() - $start);
		if (!isset(self::$profiles[$id])) {
			self::$profiles[$id] = [0, 0];
		}

		$profile = &self::$profiles[$id];
		if ($profile[1] !== 0) {
			$avgCallTime = $profile[0] / $profile[1];
			$runImmediately = $avgCallTime > self::$secondPerTick || $avgCallTime <= $tickRemaining;
		} else {
			$runImmediately = true;
		}

		if ($runImmediately) {
			self::runFunction($c, $profile);
		} else {
			self::defer($c, $id);
		}
	}

	public static function getCallerId(int $depth = 0) : string {
		$caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		if (!isset($caller[$depth])) {
			$callerId = '';
		} else {
			$callerId = $caller[$depth]['file'] . "#L" . $caller[$depth]['line'];
		}
		return $callerId;
	}

	public static function getAvailableTime() : float {
		if (!isset(self::$getNextTick)) {
			self::$getNextTick = fn() => $this->nextTick;
		}
		return self::$getNextTick->call(Server::getInstance()) + self::$secondPerTick;
	}

	public static function poll() : void {
		$availableTime = self::getAvailableTime();
		if ($availableTime <= 0) {
			return;
		}
		foreach (self::$queue as $idx => &$data) {
			if ($availableTime <= self::$rest) {
				return;
			}
			[&$c, &$id, &$polls] = $data;
			$profile = &self::$profiles[$id];
			$avgCallTime = $profile[0] / $profile[1];

			$start = microtime(true);

			$tickRemaining = $availableTime - $start;

			$run = $avgCallTime <= $tickRemaining || $polls >= self::$maxPolls;
			$polls++;
			if ($run) {
				try {
					self::runFunction($c, $profile);
				} catch (\Throwable $throwable) {
					\GlobalLogger::get()->logException($throwable);
				}
				unset(self::$queue[$idx]);
			}
			$availableTime -= microtime(true) - $start;
		}
	}

	private static function defer(Closure $c, string $id) : void {
		self::$queue[] = [$c, $id, 0];
	}

	private static function runFunction(Closure $c, array &$profile) : void {
		$start = microtime(true);
		try {
			$c();
		} finally {
			$timeUsed = microtime(true) - $start;
			$profile[0] += $timeUsed;
			$profile[1]++;
		}
	}
}