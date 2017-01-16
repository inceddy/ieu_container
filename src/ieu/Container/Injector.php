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

use ArrayObject;
use StdClass;
use Closure;
use InvalidArgumentException;

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


	/**
	 * Helper to resolve errors while booting the container.
	 * @var StackTracer
	 */
	
	public $tracer;


	/**
	 * Gets a unique StdClass instance
	 * 
	 * @return StdClass
	 * 
	 */
	
	public static function INITIAL()
	{
		static $initial = null;
		return $initial ?: $initial = new StdClass();
	}


	/**
	 * Constructor
	 *
	 * @param ArrayObject $cache    the cache
	 * @param Closure     $factory  the factory
	 *
	 * @return self
	 * 
	 */
	
	public function __construct(ArrayObject $cache, Closure $factory, StackTracer $tracer)
	{
		$this->cache = $cache;
		$this->factory = $factory->bindTo($this);
		$this->tracer = $tracer;
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
			if ($this->cache[$name] === self::INITIAL()) {
				$this->tracer->depends([$name . ' Ring!']);
				throw new \Exception("Ring dependency found for $name.\n" . $this->tracer);
			}
			return $this->cache[$name];
		}



		$this->cache[$name] = self::INITIAL();
		$this->cache[$name] = call_user_func($this->factory, $name);

		return $this->cache[$name];
	}


	/**
	 * Invokes the given factory with the dependencies.
	 *
	 * @param  array  $dependenciesAndFactory The dependencies and the factory
	 * @param  array  $localDependecies       The array with local depencies not coming from the injector cache (Eg. org instance for decorators)
	 *
	 * @return mixed                          The factory result
	 * 
	 */
	
	public function invoke(array $dependenciesAndFactory, $localDependencies = []) 
	{
		$factory = array_pop($dependenciesAndFactory);

		// Resolve dependencies
		$dependencies =  [];
		$this->tracer->depends($dependenciesAndFactory);
		foreach($dependenciesAndFactory as $key => $name) {
			$dependencies[$key] = array_key_exists($name, $localDependencies) ? $localDependencies[$name] : $this->get($name);
		}

		// If the factory is a class-method-array and the class does not exsit,
		// try to resolve the object in this injector.
		if (is_array($factory) && !is_callable($factory)) {
			$factory[0] = $this->get($factory[0]);
		}

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
	
	public function instantiate(array $dependenciesAndConstructor) 
	{
		$constructor = array_pop($dependenciesAndConstructor);
		
		// Resolve dependencies
		$dependencies = [];
		$this->tracer->depends($dependenciesAndConstructor);
		foreach ($dependenciesAndConstructor as $key => $name) {
			$dependencies[$key] = $this->get($name);
		};

		if (is_string($constructor) && class_exists($constructor)) {
			return new $constructor(...$dependencies);
		}

		if (!is_string($constructor)) {
			$constructor = print_r($constructor, true);
		}

		throw new InvalidArgumentException(sprintf("Instantiation of %s not possible.\n%s", $constructor, $this->tracer));
	}
}