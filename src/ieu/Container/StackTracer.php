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

class StackTracer {

	private $stack = [];
	private $level = 0;

	public function open($name)
	{
		array_push($this->stack, ['name' => $name, 'level' => $this->level++, 'dependencies' => []]);
	}

	public function depends(array $dependencies)
	{
		end($this->stack)['dependencies'] = $dependencies;
	}

	public function note($message)
	{
		end($this->stack)['notes'][] = $message;
	}

	public function close()
	{
		$this->level--;
		array_pop($this->stack);
	}

	public function __toString()
	{
		$stack = [];

		var_dump($this->stack);

		foreach ($this->stack as $item) {
			$offset = str_repeat("\t", $item['level']);
			$stack[] =  $offset . $item['name'] . (empty($item['dependencies']) ? '' : ' depends on ' . implode(', ', $item['dependencies']));
		}

		return implode("\n", $stack);
	}
}