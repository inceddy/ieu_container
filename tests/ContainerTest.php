<?php

use ieu\Container\Container;

include __DIR__ . '/fixtures/SomeService.php';
include __DIR__ . '/fixtures/SomeFactory.php';
include __DIR__ . '/fixtures/SomeProviderWithOptions.php';
include __DIR__ . '/fixtures/SomeActionController.php';

/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class ContainerTest extends \PHPUnit_Framework_TestCase {

	public function testValueWithString()
	{
		$container = new Container('Test');
		$container->value('test', 'test');

		$this->assertEquals($container['test'], 'test');
	}

	public function testIsset()
	{
		$container = new Container('Test');
		$container->value('aValue', null);

		$this->assertTrue(isset($container['aValue']));
		$this->assertFalse(isset($container['aOtherValue']));
	}

	public function testInjectionWithDependeciesArray()
	{
		$container = new Container('Test');
		$container->value('aValue', 'Test');

		$container->factory('aFactory', ['aValue', function($aOtherValue) {
			return $aOtherValue;
		}]);

		$this->assertEquals($container['aFactory'], 'Test');
	}

	public function testInjectionWithParameterName()
	{
		$container = new Container('Test');
		$container->value('aValue', 'Test');

		$container->factory('aFactory', function($aValue) {
			return $aValue;
		});

		$this->assertEquals($container['aFactory'], 'Test');		
	}

	/**
     * @expectedException \Exception
     * @expectedExceptionMessage Provider for 'UnknownKey' not found
     */
	
	public function testDependenyNotFound()
	{
		$container = new Container('Test');
		$container['UnknownKey'];
	}

	public function testService()
	{
		$container = (new Container('Test'))
			->value('aValue', 'The value')
			->service('aService', 'SomeService');

		$this->assertEquals($container['aService'], $container['aService']);
		$this->assertTrue($container['aService'] instanceof SomeService);
		$this->assertEquals($container['aService']->injectedValue, 'The value');
	}

	public function testFactoryWithClosureAndParameter()
	{
		$value = 'The value';
		$container = (new Container('Test'))
			->value('aValue', $value)
			->factory('aFactory', function($aValue) {
				return $aValue;
			});

		$this->assertEquals($container['aFactory'], $value);
	}

	public function testFactoryWithClosureAndDepedencyArray()
	{
		$value = 'The value';
		$container = (new Container('Test'))
			->value('aValue', $value)
			->factory('aFactory', ['aValue', function($aOtherValue){
				return $aOtherValue;
			}]);

		$this->assertEquals($container['aFactory'], $value);
	}

	public function testFactoryWithCallableArrayAndParameter()
	{
		$value = 'The value';
		$factory = new SomeFactory();
		$container = (new Container('Test'))
			->value('aValue', $value)
			->factory('aFactory', [$factory, 'someMethod']);

		$this->assertEquals($container['aFactory'], $value);
	}

	public function testFactoryWithCallableArrayAndDependencyArray()
	{
		$value = 'The value';
		$factory = new SomeFactory();
		$container = (new Container('Test'))
			->value('aValue', $value)
			->factory('aFactory', ['aValue', [$factory, 'someMethod']]);

		$this->assertEquals($container['aFactory'], $value);
	}

	public function testProvider()
	{
		$container = (new Container())
			->provider('multiplier', new SomeProviderWithOptions())
			->config(['multiplierProvider', function($provider){
				$provider->setFactor(10);
			}]);

		$this->assertEquals($container['multiplier'](10), 100);
	}

	/**
	 * @expectedException \Exception
	 */
	
	public function testState()
	{
		$container = (new Container)
			->value('aValue', 1);

		$container['aValue'];

		$container->value('aOtherValue', 2);
	}

	public function testConfig()
	{
		$container = (new Container())
			->value('Test', 'Value')
			->config(['TestProvider', function($testProvider){

			}])
			->boot();
	}

	public function testLateBind()
	{
		$container = (new Container)
			// Some simple values
			->value('Dep1', 'A')
			->value('Dep2', 'B')

			// Some provider
			->provider('Controller', new SomeActionControllerProvider())

			// Some factory using a provider function as factory
			->factory('ActionOne', ['Dep1', 'Dep2', ['Controller', 'actionOne']])
			->factory('ActionTwo', ['Dep2', 'Dep1', ['Controller', 'actionTwo']]);

		$this->assertEquals($container['ActionOne'], 'A B');
		$this->assertEquals($container['ActionTwo'], 'B A');
	}

	public function testContainerMerge()
	{
		$container_1 = (new Container)
			->value('A', 1)
			->constant('C1', 1);

		$container_2 = (new Container)
			->value('B', 2)
			->constant('C2', 2);

		$container = (new Container($container_1, $container_2))
			->value('C', 3)
			->constant('C3', 3);

		$this->assertEquals($container['A'], 1);
		$this->assertEquals($container['B'], 2);
		$this->assertEquals($container['C'], 3);

		$this->assertEquals($container['C1'], 1);
		$this->assertEquals($container['C2'], 2);
		$this->assertEquals($container['C3'], 3);
	}

	public function testRingDependencies()
	{
		$container = (new Container)
			->factory('A', ['B', function($b){}])
			->factory('B', ['A', function($a){}]);

		$container['A'];
	}
}