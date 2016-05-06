<?php

class SomeActionController {
	public function actionOne($dep1, $dep2)
	{
		return $dep1 . ' ' . $dep2;
	}

	public function actionTwo($dep1, $dep2)
	{
		return $dep1 . ' '. $dep2;
	}
}

class SomeActionControllerProvider extends ieu\Container\Provider {

	private $factor = 2;

	public function __construct()
	{
		$this->factory = [$this, 'factory'];
	}

	public function factory()
	{
		return new SomeActionController();
	}
}