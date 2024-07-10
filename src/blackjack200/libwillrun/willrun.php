<?php

namespace libwillrun {

	use blackjack200\libwillrun\profiler\WillRun;

	function run(callable $call) : void {
		WillRun::run($call, 2);
	}
}