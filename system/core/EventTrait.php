<?php

/**
 * TODO [LRM]: update to global events.
 *
 * Trait EventTrait
 */
trait EventTrait {
	/**
	 * @var array
	 */
	private $eventList = [];

	/**
	 * @param string $event
	 * @param callable $callback
	 */
	protected function registerEventListener($event, callable $callback) {
		$this->eventList[$event][] = $callback;
	}

	/**
	 * @param string $event
	 * @param array $arg
	 * @return bool
	 */
	protected function fireEvent($event, array $arg = []) {
		if (isset($this->eventList[$event])) {
			foreach ($this->eventList[$event] as $callable) {
				$result = call_user_func($callable, $arg);
				if (false === $result) {
					return false;
				}
			}
		}

		return true;
	}
}
