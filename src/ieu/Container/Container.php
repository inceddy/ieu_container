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
 * Extracts the parameter names of the given callable or 
 * of the constructor of the given classname.
 *
 * @throws Exception if not a callable or classname without __contruct method was given.
 *
 * @param  mixed $callableOrClassname  The callable or the classname
 *
 * @return array                       The parameter names
 * 
 */
	
function extractParameter($callableOrClassname) {
	switch (true) {
		// Handle closure
		case is_object($callableOrClassname) && $callableOrClassname instanceof \Closure:
			$parameters = (new \ReflectionFunction($callableOrClassname))->getParameters();
			break;
		
		// Handle object and method array
		case is_array($callableOrClassname) && is_callable($callableOrClassname):
			$class = is_string($callableOrClassname[0]) ? $callableOrClassname[0] : get_class($callableOrClassname[0]);
			$parameters = (new \ReflectionMethod($class . '::' . $callableOrClassname[1]))->getParameters();
			break;

		// Handle class name
		case is_string($callableOrClassname) && class_exists($callableOrClassname):
			$parameters = (new \ReflectionMethod($callableOrClassname . '::__construct'))->getParameters();
			break;

		default:
			throw new \Exception("Dependencies could not be extracted");
	}

	return array_map(function(\ReflectionParameter $parameter){
		return $parameter->name;
	}, $parameters);
}


/**
 * Container class inspired by the dependency injection of the AngularJS Framework.
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 */

class Container implements \ArrayAccess {

	/**
	 * Default state after instanciation
	 */
	
	const STATE_INITIAL = 0;

	/**
	 * Config state while configurations are running
	 */
	
	const STATE_CONFIG  = 10;

	/**
	 * Completely booted
	 */
	
	const STATE_BOOTED  = 20;


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
	 * Creates a new module with name {$name} which depends on 
	 * the modules in {$modules}.
	 *
	 * @param string $name    The name of this module
	 * @param array  $modules The names of modules to load
	 */
	
	public function __construct()
	{
		// Set container to initial state
		$this->state = self::STATE_INITIAL;

		// Setup cache
		$this->providerCache = new \ArrayObject();
		$this->instanceCache = new \ArrayObject();

		// Provider injector
		$providerInjector =
		$this->providerInjector = new Injector($this->providerCache, function($name){
			$name = substr($name, 0, -8);
			throw new \Exception("Provider for '$name' not found");
		});

		// Instance injector
		$this->instanceInjector = new Injector($this->instanceCache, function($name) use ($providerInjector) {
			$provider = $providerInjector->get($name . 'Provider');
			$factory = Container::getDependencyArray($provider->factory);
			return $this->invoke($factory, $name);
		});

		// Implement container as provider
		$this->provider('Container', ['factory' => [function(){
			return $this;
		}]]);

		// Implement instance injector as provider
		$this->provider('Injector', ['factory' => [function() {
			return $this->instanceInjector;
		}]]);
	}

	/**
	 * Checks if an callable or a classname is wraped in a dependency array
	 * `['aDependency', 'aOtherDependency', $callableOrClassname]`.
	 * If not the argument will be wraped.
	 *
	 * @param  mixed $argument The argument to check
	 *
	 * @return array           The dependency array
	 * 
	 */
	
	public static function getDependencyArray($argument)
	{
		// Argument is a valid dependency array
		if (
			// DependencyArray with callable as last element
			(is_array($argument) && is_callable(end($argument))) ||
			// DependencyArray with classname as last element
			(is_array($argument) && is_string(end($argument)) && !is_callable($argument))
		) {
			return $argument;
		}

		// Extract parameter from argument and combine it to an dependency array
		$parameter = extractParameter($argument);
		array_push($parameter, $argument);

		return $parameter;
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
	
	public function provider($name, $provider)
	{
		if ($this->state === self::STATE_BOOTED) {
			throw new \Exception("The container is already booted");
		}

		if (!is_object($provider) && !is_array($provider)) {
			throw new \Exception("The Provider must be an array or an object");
		}

		if (is_array($provider)) {
			$provider = (object)$provider;
		}

		$this->providerCache[$name . 'Provider'] = $provider;
		return $this;
	}

	public function decorator($name, $decorator)
	{
		$instanceInjector = $this->instanceInjector;

		$orgProvider = $this->providerInjector->get($name . 'Provider');
		$orgFactory  = $orgProvider->factory;

		$orgProvider->factory = function() use ($instanceInjector) {
			$orgInstance = $instanceInjector->invoke($orgFactory);
			return $instanceInjector->invoke($decorator->factory, ['orgInstance' => $orgInstance]);
		};
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
		return $this->provider($name, ['factory' => ['Injector', function($injector) use ($dependenciesAndService) {
			return $injector->instantiate($dependenciesAndService);
		}]]);
	}


	/**
	 * Register a new factorx
	 *
	 * @param  string $name     The name of the factory
	 * @param  mixed  $factory  The factory
	 *
	 * @return self
	 * 
	 */
	
	public function factory($name, $factory)
	{
		$dependenciesAndFactory = self::getDependencyArray($factory);
		return $this->provider($name, ['factory' => $dependenciesAndFactory]);
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
	
	public function value($name, $value)
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
	
	public function constant($name, $value)
	{
		$this->providerCache[$name] = $value;
		$this->instanceCache[$name] = $value;

		return $this;
	}

	public function config($config)
	{
		$this->configs[] = $config;
		return $this;
	}


	/**
	 * Run all configurations and set container state to bottet
	 *
	 * @return self
	 * 
	 */
	
	public function boot()
	{
		$this->state = self::STATE_CONFIG;

		foreach ($this->configs as $config) {
			$dependenciesAndCallable = $this->getDependencyArray($config);

			$callable = array_pop($dependenciesAndCallable);
			$dependencies = array_map([$this->providerInjector, 'get'], $dependenciesAndCallable);

			call_user_func_array($callable, $dependencies);
		}
		$this->state = self::STATE_BOOTED;

		return $this;
	}

	/**
	 * Get a container dependen
	 */

	public function offsetGet($name)
	{
		if ($this->state === self::STATE_INITIAL) {
			$this->boot();
		}

		return $this->instanceInjector->get($name);
	}

	public function offsetSet($name, $value)
	{
		return $this->value($name, $value);
	}

	public function offsetExists($name)
	{
		return $this->instanceInjector->has($name) || $this->providerInjector->has($name . 'Provider');
	}

	public function offsetUnset($key)
	{
		throw new \Exception("Not implemented");		
	}
}