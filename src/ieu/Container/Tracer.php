<?php

/*
 * This file is part of ieUtilities - Container.
 *
 * (c) 2016 Philipp Steingrebe <philipp@steingrebe.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ieu\Container;

class Tracer {

	private $stack = [];

	public function request($name)
	{
		array_push($this->stack, [
			'type' => 'request',
			'name' => $name
		]);
	}

	public function dependsOn(array $dependencies)
	{
		array_push($this->stack, [
			'type' => 'dependsOn',
			'dependencies' => $dependencies
		]);
	}

	public function note($message)
	{
		array_push($this->stack, [
			'type' => 'note',
			'note' => $message
		]);
	}

	public function received()
	{
		array_push($this->stack, [
			'type' => 'received'
		]);
	}

	public function __toString()
	{
		$level = 0;
		$lines = [];

		foreach ($this->stack as $action) {
			switch ($action['type']) {
				case 'request':
					$lines[] = str_repeat("\t", $level) . $action['name'] . ' requested ('; 
					$level++;
					break;
				case 'dependsOn':
					$deps = $action['dependencies'];
					$lines[] = str_repeat("\t", $level) . 'Depedencies: ' . (empty($deps) ? 'none' : '[' . implode(', ', $deps) . ']'); 
					break;
				case 'note':
					$lines[] = str_repeat("\t", $level) . 'Note: ' . $action['note'];
					break;
				case 'received':
					$level--;
					$lines[] = str_repeat("\t", $level) . ')';
					break;
			}
		}

		return implode("\n", $lines);
	}
}