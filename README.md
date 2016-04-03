# ieu\Container
PHP Dependency Injection Container.

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


## Values
Values can be injected using the `self ieu\Container\Container::value(mixed)`-method.

## Service
Services can be injected using the `self ieu\Container\Container::service(mixed)`-method
