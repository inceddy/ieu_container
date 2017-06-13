<?php

/*
 * This file is part of ieUtilities - Container.
 *
 * (c) 2017 Philipp Steingrebe <philipp@steingrebe.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ieu\Container;

use ArrayObject;
use StdClass;
use Closure;
use InvalidArgumentException;
use LogicException;

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
	
	public function __construct(ArrayObject $cache, Closure $factory, Tracer $tracer)
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
	
	public function has(string $name) : bool
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
	
	public function get(string $name)
	{
		if (!$this->has($name)) {
			$this->cache[$name] = self::INITIAL();
			$this->cache[$name] = call_user_func($this->factory, $name);
		}

		// Test for ring dependency
		if ($this->cache[$name] === self::INITIAL()) {
			$this->tracer->note($name . ' is  Ring!');
			throw new LogicException("Ring dependency found for $name.\n" . $this->tracer);
		}


		return $this->cache[$name];
	}


	/**
	 * Invokes the given factory with the dependencies.
	 *
	 * @param  array  $dependenciesAndFactory 
	 *    The dependencies and the factory
	 * @param  array  $localDependecies
	 *    The array with local depencies not coming from 
	 *    the injector cache (E.g. org instance for decorators)
	 * @param array $arguments
	 *    The array with mandatory arguments which is filled up
	 *    with all required dependencies
	 *
	 * @return mixed
	 *    The factory result
	 *    
	 */
	
	public function invoke(array $dependenciesAndFactory, array $localDependencies = [], array $arguments = []) 
	{
		$factory = array_pop($dependenciesAndFactory);

		// Resolve dependencies
		$this->tracer->dependsOn($dependenciesAndFactory);
		foreach($dependenciesAndFactory as $key => $name) {
			$arguments[] = array_key_exists($name, $localDependencies) ? $localDependencies[$name] : $this->get($name);
		}

		// If the factory is a class-method-array and the class does not exsit,
		// try to resolve the object in this injector.
		if (is_array($factory) && !is_callable($factory)) {
			$this->tracer->note(sprintf('Try to resolve %s as factory object', $factory[0]));

			// Object and method
			if (sizeof($factory) === 2) {
				$factory[0] = $this->get($factory[0]);
			}
			// Other callable
			else if(sizeof($factory) === 1) {
				$factory = $this->get($factory[0]);
			}
			// Unsupported factory
			else {
				$this->tracer->node('Factory: ' . print_r($factory, true));
				throw new InvalidArgumentException('Can\'t handle factory' . (string) $tracer);
			}
		}

		return call_user_func_array($factory, $arguments);
	}


	/**
	 * Instantiates the given class with the dependencies.
	 *
	 * @param  array  $dependenciesAndConstructor 
	 *    The dependencies and the class name
	 * @param  array  $localDependecies
	 *    The array with local depencies not coming from 
	 *    the injector cache (E.g. org instance for decorators)
	 * @param array $arguments
	 *    The array with mandatory arguments which is filled up
	 *    with all required dependencies
	 *
	 * @return mixed 
	 *    The new instance of the given class
	 * 
	 */
	
	public function instantiate(array $dependenciesAndConstructor, array $localDependencies = [], array $arguments = []) 
	{
		$constructor = array_pop($dependenciesAndConstructor);
		
		// Resolve dependencies
		$this->tracer->dependsOn($dependenciesAndConstructor);
		foreach ($dependenciesAndConstructor as $key => $name) {
			$arguments[] = array_key_exists($name, $localDependencies) ? $localDependencies[$name] : $this->get($name);
		};

		// Constructor is class name
		if (is_string($constructor) && class_exists($constructor)) {
			return new $constructor(...$arguments);
		}

		if (!is_string($constructor)) {
			$constructor = print_r($constructor, true);
		}

		throw new InvalidArgumentException(sprintf("Instantiation of %s not possible.\n%s", $constructor, $this->tracer));
	}
}