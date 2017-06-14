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

use Psr\SimpleCache\CacheInterface;
use Closure;
use ArrayAccess;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use Exception;
use InvalidArgumentException;
use LogicException;

/**
 * Extracts the parameter names of the given callable or 
 * of the constructor of the given classname.
 *
 * @param  mixed $callableOrClassname  The callable or the classname
 *
 * @return array<string>               The parameter names or an empty array if nothing was extrected
 * 
 */
	
function extractParameters($callableOrClassname) : array {
	switch (true) {
		// Handle closure
		case $callableOrClassname instanceof Closure:
			$parameters = (new ReflectionFunction($callableOrClassname))->getParameters();
			break;
		
		// Handle clasname-object-method-array
		case is_array($callableOrClassname) && is_callable($callableOrClassname):
			$class = is_string($callableOrClassname[0]) ? $callableOrClassname[0] : get_class($callableOrClassname[0]);
			$parameters = (new ReflectionMethod($class . '::' . $callableOrClassname[1]))->getParameters();
			break;

		// Handle callable-string classname::method or function name
		case is_string($callableOrClassname) && is_callable($callableOrClassname):
			$parameters = strpos($callableOrClassname, '::') ?
								(new ReflectionMethod($callableOrClassname))->getParameters() :
								(new ReflectionFunction($callableOrClassname))->getParameters();
			break;
		
		// Handle class name
		case is_string($callableOrClassname) && class_exists($callableOrClassname):
			$parameters = (new ReflectionMethod($callableOrClassname . '::__construct'))->getParameters();
			break;

		default:
			throw new InvalidArgumentException(
				sprintf('Cant extract parameters from given argument with type \'%s\'.', gettype($callableOrClassname))
			);
	}

	return array_map(function(ReflectionParameter $parameter){
		return $parameter->name;
	}, $parameters);
}


/**
 * Container class inspired by the dependency injection of the AngularJS Framework.
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 */

class Container implements ArrayAccess {

	/**
	 * Default state after instanciation
	 */
	
	private const STATE_INITIAL = 0;

	/**
	 * Config state while configurations are running
	 */
	
	private const STATE_CONFIG  = 10;

	/**
	 * Completely booted
	 */
	
	private const STATE_BOOTED  = 20;

	
	/**
	 * The class to be used as cache
	 * @var string
	 */
	
	public static $cacheClassName = ArrayCache::CLASS;


	/**
	 * The cache where all providers are stored
	 * @var ArrayObject
	 */
	
	private $providerCache;


	/**
	 * The cache where all instances are stored
	 * @var ArrayObject
	 */
	
	private $instanceCache;


	/**
	 * Injector used to prepare the providers
	 * @var ieu\Core\Injector
	 */
	
	private $providerInjector;


	/**
	 * Injector used to prepare the instances
	 * @var ieu\Core\Injector
	 */
	
	private $instanceInjector;


	/**
	 * List of configuration functions to setup the container
	 * @var array[]
	 */
	
	private $configs = [];


	/**
	 * The state of this container
	 * @var integer
	 */
	
	private $state;

	/**
	 * Debugger
	 * @var ieu\Container\Tracer
	 */
	
	public $tracer;


	/**
	 * Creates a new container.
	 * The provider cache of all given Container instances will be merged
	 * into this container.
	 *
	 * @param ieu\Container\Container  The containers to merge with this container
	 */
	
	public function __construct(Container $extends = null)
	{
		// Set container to initial state
		$this->state = self::STATE_INITIAL;

		// Setup cache
		if (null !== $extends) {
			$this->providerCache = $extends->getProviderCache();
			$this->instanceCache = $extends->getInstanceCache();
		}
		else {
			$this->providerCache = new static::$cacheClassName;
			$this->instanceCache = new static::$cacheClassName;
		}

		$this->buildInjectors();
	}

	/**
	 * Gets the provider cache
	 *
	 * @return Psr\SimpleCache\CacheInterface
	 *    The provider cache
	 */
	
	public function getProviderCache() : CacheInterface
	{
		return $this->providerCache;
	}


	/**
	 * Gets the instance cache
	 *
	 * @return Psr\SimpleCache\CacheInterface
	 *    The instance cache
	 */
	
	public function getInstanceCache() : CacheInterface
	{
		return $this->instanceCache;
	}


	/**
	 * Sets up the provider and instance injectors
	 *
	 * @return void
	 */
	
	private function buildInjectors()
	{
		// Debug tracer
		$this->tracer = new Tracer;

		// Build provider injector
		$providerInjector =
		$this->providerInjector = new Injector(
			// Cache
			$this->providerCache, 
			// Factory
			function($name) {
				$name = substr($name, 0, -8);
				throw new LogicException("Provider for '$name' not found\n" . $this->tracer);
			}, 
			// Debug tracer
			$this->tracer
		);

		// Build instance injector
		$this->instanceInjector = new Injector(
			// Cache
			$this->instanceCache, 
			// Factory
			function($name) use ($providerInjector) {
				$this->tracer->request($name);

				$provider = $providerInjector->get($name . 'Provider');
				// Determin factory dependencies
				$factoryAndDependencies = Container::getDependencyArray($provider->factory);
				$instance = $this->invoke($factoryAndDependencies);

				$this->tracer->received($name);

				return $instance;
			}, 
			// Debug tracer
			$this->tracer
		);

		// Implement container as provider
		$this->provider('Container', (object)['factory' => [function(){
			return $this;
		}]]);

		// Implement instance injector as provider
		$this->provider('Injector', (object)['factory' => [function() {
			return $this->instanceInjector;
		}]]);
	}


	/**
	 * Register a new provider which must implement the
	 * provider interface. 
	 *
	 * Providers can be injected using the provider name
	 * with 'Provider' suffix. Eg. ieuInjectorProvider
	 *
	 * @throws Exception if the container is already bootet.
	 *
	 * @param  string                  $name     The name of the provider
	 * @param  ieu\Core\Provider|array $provider The Provider or an Array with a 'factory' key
	 *
	 * @return self
	 * 
	 */
	
	public function provider(string $name, $provider)
	{
		if ($this->state === self::STATE_BOOTED) {
			throw new Exception("The container is already booted");
		}

		if (!is_object($provider) || !property_exists($provider, 'factory')) {
			throw new InvalidArgumentException('The provider must be an object with public property \'factory\'.');
		}

		$this->providerCache->set($name . 'Provider', $provider);

		return $this;
	}


	/**
	 * Register a new decorator which will overload
	 * the factory of the given provider with the given name.
	 *
	 * The original instance can be injected using
	 * `DecoratedInstance` as depedency.
	 *
	 * @throws Exception if the container is already bootet.
	 *
	 * @param  string $name
	 *    The name to overload
	 * @param  array|callable $decorator 
	 *     The decorator
	 *
	 * @return self
	 * 
	 */

	public function decorator($name, $decorator)
	{
		$lagacyProvider = $this->providerInjector->get($name . 'Provider');
		$lagacyFactory = $lagacyProvider->factory;

		// If decorator is provider resolve factory
		if (is_object($decorator) && property_exists($decorator, 'factory')) {
			$decorator = $decorator->factory;
		}

		$decoratorAndDependencies = self::getDependencyArray($decorator);

		// Overload old factory
		$lagacyProvider->factory = function() use ($decoratorAndDependencies, $lagacyFactory) {
			$decoratedInstance = $this->instanceInjector->invoke($lagacyFactory);
			return $this->instanceInjector->invoke($decoratorAndDependencies, ['DecoratedInstance' => $decoratedInstance]);
		};

		return $this;
	}


	/**
	 * Register a new service.
	 *
	 * @param  string $name     The name of the service
	 * @param  mixed  $service  The service
	 *
	 * @return self
	 * 
	 */
	
	public function service($name, $service)
	{
		$dependenciesAndService = self::getDependencyArray($service);
		
		return $this->provider(
			$name, 
			(object)['factory' => [
				'Injector', function($injector) use ($dependenciesAndService) {
					return $injector->instantiate($dependenciesAndService);
				}
			]]
		);
	}


	/**
	 * Register a new factory
	 *
	 * @param  string $name     The name of the factory
	 * @param  mixed  $factory  The factory
	 *
	 * @return self
	 * 
	 */
	
	public function factory(string $name, $factory)
	{
		return $this->provider($name, (object)['factory' => $factory]);
	}


	/**
	 * Register a new value
	 *
	 * @param  string $name  The name of the value
	 * @param  mixed  $value The value
	 *
	 * @return self
	 * 
	 */
	
	public function value(string $name, $value)
	{
		return $this->factory($name, [function() use ($value) {
			return $value;
		}]);
	}


	/**
	 * Register a new constant.
	 * Constants are available during configuration state.
	 *
	 * @param  string $name  The name of the constant
	 * @param  mixed  $value The constant
	 *
	 * @return self
	 * 
	 */
	
	public function constant(string $name, $value)
	{
		$this->providerCache->set($name, $value);
		$this->instanceCache->set($name, $value);

		return $this;
	}


	/**
	 * Adds a dependency-callable-array to this comfig stack.
	 * On boot all callables will be called with the given dependencies.
	 *
	 * @param  array  $config  The dependency-callable-array
	 *
	 * @return self
	 * 
	 */
	
	public function config($config)
	{
		$this->configs[] = $config;
		return $this;
	}


	/**
	 * Run all configurations and set container state to `bootet`
	 *
	 * @return self
	 * 
	 */
	
	public function boot()
	{
		$this->state = self::STATE_CONFIG;

		foreach ($this->configs as $config) {
			$dependenciesAndCallable = self::getDependencyArray($config);

			$callable = array_pop($dependenciesAndCallable);
			$dependencies = array_map([$this->providerInjector, 'get'], $dependenciesAndCallable);

			call_user_func_array($callable, $dependencies);
		}
		$this->state = self::STATE_BOOTED;

		return $this;
	}


	/**
	 * Alias for `ieu\Container\Container::offsetSet()`
	 *
	 * @param string $name
	 *    The name of the value to set
	 * @param mixed $value
	 *    The value to set
	 *
	 * @return self
	 * 
	 */
	
	public function __set(string $name, $value)
	{
		return $this->value($name, $value);
	}


	/**
	 * Alias for `ieu\Container\Container::offsetGet()`
	 *
	 * @param  string $name
	 *    The name of the dependency to get
	 *
	 * @return mixed
	 *    The dependency
	 *    
	 */
	
	public function __get(string $name)
	{
		return $this->offsetGet($name);
	}


	/**
	 * Gets a dependency from the container.
	 * If the container is not yet booted all configs will be run.
	 *
	 * Satisfies ArrayAcces interface.
	 *
	 * @param  string $name
	 *    The name of the dependency to get
	 *
	 * @return mixed
	 *    The dependency
	 * 
	 */

	public function offsetGet($name)
	{
		if ($this->state === self::STATE_INITIAL) {
			$this->boot();
		}

		return $this->instanceInjector->get($name);
	}


	/**
	 * Sets container value.
	 *
	 * Satisfies ArrayAcces interface.
	 *
	 * @param  string $name
	 *    The name of dependency value to set
	 * @param  mixed $value
	 *    The value to set
	 *
	 * @return self
	 * 
	 */
	
	public function offsetSet($name, $value)
	{
		return $this->value($name, $value);
	}


	/**
	 * Returns whether a dependency instance or 
	 * the corresponding provider exist within
	 * this container.
	 *
	 * Satisfies ArrayAcces interface.
	 *
	 * @param  string $name
	 *    The name of dependency value to check
	 *
	 * @return bool
	 * 
	 */
	
	public function offsetExists($name)
	{
		return $this->instanceInjector->has($name) || 
		       $this->providerInjector->has($name . 'Provider');
	}


	/**
	 * Must exist to satisfy the ArrayAccess interface
	 * but is not implemented.
	 *
	 * @throws Exception allways as this method is not implemented
	 *
	 * @param  string $name
	 *    The name to unset
	 *
	 * @return void
	 * 
	 */
	
	public function offsetUnset($name)
	{
		throw new Exception("Not implemented");		
	}


	/**
	 * Checks if an callable or a classname is wraped in a dependency-factory-array
	 * `['aDependency', 'aOtherDependency', $callableOrClassname]`.
	 * If not the argument will be treated as factory and the dependencys will be
	 * extracted from the function, method or constructor arguments.
	 *
	 * @see ieu\Container\
	 *
	 * @param  mixed $argument  The argument to check whether it is a valid depedency-factory-array
	 *                          or just a factory.
	 *
	 * @return array            The dependency array
	 * 
	 */
	
	public static function getDependencyArray($argument)
	{
		// Must condition: A dependency-factory-array is an array
		if (is_array($argument)) {
			// Must condition: A last element in a dependency-factory-array is
			//                 - an array: [ClassName|Object, Method]
			//                 - an callable: Closure, Invokeable, ClassName::StaticMethod-string
			//                 - an classname
			
			$factory = end($argument);

			if (is_array($factory) || is_callable($factory) || class_exists($factory)) {
				return $argument;
			}
		}

		// Try to extract parameters from argument and combine it to an dependency-factory-array
		$parameter = extractParameters($argument);
		array_push($parameter, $argument);
		return $parameter;
	}

	/**
	 * Setter for cache class name.
	 * Given class name must implement Pst\SimpleCache\CacheInterface
	 *
	 * @throws InvalidArgumentException
	 *    When $className is not a class or does not 
	 *    implement the cache interface
	 *
	 * @param void
	 */
	
	public static function setCacheClassName(string $className) : void
	{
		if (!class_exists($className) || !in_array(CacheInterface::CLASS, class_implements($className))) {
			throw new InvalidArgumentException(sprintf(
				'Class [%s] does exist or does not implement [%s].',
				$className,
				CacheInterface::CLASS
			));
		}

		static::$cacheClassName = $className;
	}
}