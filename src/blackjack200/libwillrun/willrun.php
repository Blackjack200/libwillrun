<?php

namespace libwillrun {

	use blackjack200\libwillrun\profiler\WillRun;

	function run(callable $call, ?string $caller = null) : void {
		WillRun::run($call, 2, $caller);
	}

	function will_run(callable $call, ?string $caller = null) : void {
		WillRun::run($call, 2, $caller);
	}
}