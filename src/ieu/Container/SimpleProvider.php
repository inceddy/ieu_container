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

/**
 * A simple provider used to wrapup values, services and factories.
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 */

class SimpleProvider extends Provider {

	/**
	 * The factory for the service of this provider.
	 * `['aDependency', 'aOtherDependency', $callableFactory]`
	 * 
	 * @var array  
	 */

	public $factory;

	public function __construct(array $factory)
	{
		$this->factory = $factory;
	}
}