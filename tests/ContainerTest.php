<?php

use ieu\Container\Container;

include __DIR__ . '/fixtures/SomeService.php';
include __DIR__ . '/fixtures/SomeFactory.php';
include __DIR__ . '/fixtures/SomeProviderWithOptions.php';

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
}