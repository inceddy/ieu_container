# ieu\Container
PHP Dependency Injection Container inspired by the AngularJS injector and Pimple\Container

# Usage
```PHP
use ieu\Container\Container;

$container = (new Container())
	->value('factor', 2)
	->service('multiplier', ['factor', 'Vendor\\Project\\Multiplier'])
	->factory('double', ['multiplier', function($theMultiplierService){
		return function($number) use ($theMultiplierService) {
			return $multiplierServcie->multiply($number);
		};
	}]);

echo $container['factor']; // 2
echo $container['double'](10); // 20
```
## Injection
Dependencies can be injected into services and factories using a *dependency array* `['dependencyA', 'dependencyB', $callableOrClassname]` where the dependecies will be given to the callable or the class constructor as arguments.

A (slower) way is using the parameter names of the callable or constructor to specify the dependencies. E.g. `function($dependencyA, $dependencyB) {...}` has the same result as `['dependencyA', 'dependencyB', function($depA, $depB)]`.


## Values
Values can be defined using the `self ieu\Container\Container::value(string $name, mixed $value)`-method.

## Factory
Factorys can be defined using the `self ieu\Container\Container::factory(string $name, mixed $factory`-method.

## Service
Services can be defined using the `self ieu\Container\Container::service(string $name, mixed $service)`-method.
The service-method expects a class name or a dependency array with the class name as last element as argument. E.g. `['dependencyA', 'dependencyB', 'Vendor\\Project\\Service']` or just (slower) `'Vendor\\Project\\Service'` where the parameter names of the constructor are used to inject the dependencies. 
