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
 * Provider interface
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 */


abstract class Provider {

	/**
	 * Returns a callable wrapped in a dependency array
	 *
	 * @return array
	 * 
	 */
	
	public $factory;
}