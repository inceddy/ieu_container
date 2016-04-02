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
 * Injector class inspired by the dependency injection of the AngularJS Framework.
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 */

class Injector {

	/**
	 * The cache where instances this injector creates are stored.
	 * Name => instance pairs.
	 * @var ArrayObject
	 */
	
	private $cache;


	/**
	 * The factory this injector uses to create new instances.
	 * @var Closure
	 */

	private $factory;

	public function __construct($cache, \Closure $factory)
	{
		$this->cache = $cache;
		$this->factory = $factory->bindTo($this);
	}


	/**
	 * Where the name is set in the cache or not.
	 *
	 * @param  string  $name The name to check
	 *
	 * @return boolean
	 * 
	 */
	
	public function has($name) 
	{
		return isset($this->cache[$name]);
	}


	/**
	 * Gets an element from the instance cache.
	 * If the name is not set in the cache the injector
	 * factory trys to fetch it.
	 *
	 * @param  string $name  The name of the dependency
	 *
	 * @return mixed         The dependency
	 * 
	 */
	
	public function get($name)
	{
		if ($this->has($name)) {
			return $this->cache[$name];
		}

		$this->cache[$name] = call_user_func($this->factory, $name);

		return $this->cache[$name];
	}


	/**
	 * Invokes the given factory with the dependencies.
	 *
	 * @param  array  $dependenciesAndFactory The dependencies and the factory
	 *
	 * @return mixed                          The factory result
	 * 
	 */
	
	public function invoke(array $dependenciesAndFactory) {
		$factory = array_pop($dependenciesAndFactory);
		$dependencies = array_map([$this, 'get'], $dependenciesAndFactory);

		return call_user_func_array($factory, $dependencies);
	}


	/**
	 * Instantiates the given class with the dependencies.
	 *
	 * @param  array  $dependenciesAndConstructor The dependencies and the class name
	 *
	 * @return mixed                              The new instance of the given class
	 * 
	 */
	
	public function instantiate(array $dependenciesAndConstructor) {
		$constructor = array_pop($dependenciesAndConstructor);
		$dependencies = array_map([$this, 'get'], $dependenciesAndConstructor);

		if (is_string($constructor) && class_exists($constructor)) {
			return new $constructor(...$dependencies);
		}

		throw new \Exception(sprintf("Instantiation of % not possible.", end($dependenciesAndConstructor)));
	}
}