<?php

class SomeDecorator extends ieu\Container\Provider {

	private $factor = 2;

	public function __construct()
	{
		$this->factory = ['orgInstance', [$this, 'factory']];
	}

	public function setFactor($factor) {
		$this->factor = $factor;
	}

	public function factory($instance)
	{
		$this->orgInstance = $instance;
		return new Multiplier($this->factor);
	}
}