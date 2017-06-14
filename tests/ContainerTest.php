<?php

use ieu\Container\Container;
use ieu\Container\Injector;
use ieu\Container\ArrayCache;

require_once __DIR__ . '/fixtures/SomeDecorator.php';
require_once __DIR__ . '/fixtures/SomeService.php';
require_once __DIR__ . '/fixtures/SomeFactory.php';
require_once __DIR__ . '/fixtures/SomeProviderWithOptions.php';
require_once __DIR__ . '/fixtures/SomeActionController.php';
require_once __DIR__ . '/fixtures/Foo.php';

/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class ContainerTest extends PHPUnit_Framework_TestCase {

  public function testConstant()
  {
    $gotCalled = false;

    $container = (new Container)->constant('c', 1);

    $container->config(['c', function($c) use (&$gotCalled) {
      $this->assertEquals(1, $c);
      $gotCalled = true;
    }]);

    $this->assertEquals(1, $container['c']);
    $this->assertTrue($gotCalled);
  }

  public function testValueWithString()
  {
    $container = (new Container)->value('test', 'test');

    $this->assertEquals($container['test'], 'test');
  }

  public function testIsset()
  {
    $container = (new Container)->value('aValue', null);

    $this->assertTrue(isset($container['aValue']));
    $this->assertFalse(isset($container['aOtherValue']));
  }

  public function testInjectionWithDependeciesArray()
  {
    $container = (new Container)->value('aValue', 'Test');

    $container->factory('aFactory', ['aValue', function($aOtherValue) {
      return $aOtherValue;
    }]);

    $this->assertEquals($container['aFactory'], 'Test');
  }

  public function testInjectionWithParameterName()
  {
    $container = (new Container)->value('aValue', 'Test');

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
    $container = new Container;
    $container['UnknownKey'];
  }

  public function testService()
  {
    $container = (new Container)
      ->value('aValue', 'The value')
      ->service('aService', 'SomeService');

    $this->assertEquals($container['aService'], $container['aService']);
    $this->assertTrue($container['aService'] instanceof SomeService);
    $this->assertEquals($container['aService']->injectedValue, 'The value');
  }

  public function testFactoryWithClosureAndParameter()
  {
    $value = 'The value';
    $container = (new Container)
      ->value('aValue', $value)
      ->factory('aFactory', function($aValue) {
        return $aValue;
      });

    $this->assertEquals($container['aFactory'], $value);
  }

  public function testFactoryWithClosureAndDepedencyArray()
  {
    $value = 'The value';
    $container = (new Container)
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
    $container = (new Container)
      ->value('aValue', $value)
      ->factory('aFactory', [$factory, 'someMethod']);

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
   * @expectedException InvalidArgumentException
   */
  
  public function testProviderWithNoneObjectOrArray()
  {
    $container = (new Container)
      ->provider('Test', 'invalid-argument');
  }

  /**
   * @expectedException InvalidArgumentException
   */
  
  public function testProviderWithMissinfFactory()
  {
    $container = (new Container)
      ->provider('Test', ['factorrrry' => ['Test']]);
  }

  public function testDecoratorWithFactory()
  {
    $container = (new Container)
      ->value('SomeValue', 'Hello')
      ->decorator('SomeValue', ['DecoratedInstance', function($instance) {
        return $instance . 'World';
      }]);

    $this->assertEquals('HelloWorld', $container['SomeValue']);
  }

   public function testDecoratorWithProvider()
  {
    $container = (new Container)
      ->value('SomeValue', 'Hello')
      ->decorator('SomeValue', new SomeDecorator);

    $this->assertEquals('HelloWorld', $container['SomeValue']);
  } 

  /**
   * @expectedException Exception
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
    $container = (new Container)
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

      // Some factory
      ->factory('ControllerFactory', [function() {
        return function ($a, $b) {
          return "$a $b";
        };
      }])

      // Some value
      ->value('ControllerValue', function ($a, $b) {
        return "$b $a";
      })

      // Some factory using a provider function as factory
      ->factory('Action1', ['Dep1', 'Dep2', ['Controller', 'actionOne']])
      ->factory('Action2', ['Dep2', 'Dep1', ['Controller', 'actionTwo']])
      ->factory('Action3', ['Dep1', 'Dep2', ['ControllerFactory']])
      ->factory('Action4', ['Dep1', 'Dep2', ['ControllerValue']]);

    $this->assertEquals($container['Action1'], 'A B');
    $this->assertEquals($container['Action2'], 'B A');
    $this->assertEquals($container['Action3'], 'A B');
    $this->assertEquals($container['Action4'], 'B A');
  }

  /**
   * @expectedException InvalidArgumentException
   */
  
  public function testInvalidLateBind()
  {
    $container = (new Container)
      // Some simple values
      ->value('Dep1', 'A')
      ->value('Dep2', 'B')
      ->value('ControllerValue', 'no-callable')

      ->factory('Action', ['Dep1', 'Dep2', ['ControllerValue']]);

    $container['Action'];
  }

  public function testContainerMerge()
  {
    $container_1 = (new Container)
      ->value('A', 1)
      ->constant('C1', 1);

    $container_2 = (new Container($container_1))
      ->value('B', 2)
      ->constant('C2', 2);

    $container = (new Container($container_2))
      ->value('C', 3)
      ->constant('C3', 3);


    $this->assertEquals($container['A'], 1);
    $this->assertEquals($container['B'], 2);
    $this->assertEquals($container['C'], 3);

    $this->assertEquals($container['C1'], 1);
    $this->assertEquals($container['C2'], 2);
    $this->assertEquals($container['C3'], 3);
  }

  /**
   * @expectedException LogicException
   */
  
  public function testRingDependencies()
  {
    $container = (new Container)
      ->factory('A', ['B', function($b){}])
      ->factory('B', ['A', function($a){}]);


    $container['A'];
  }

  public function testParameterExtraction() {
    $foo = new Foo;
    $tests = [
      'Closure'           => function($A, $B, $C) {},
      'ClassName'         => Foo::CLASS,
      'ClassName::method' => Foo::CLASS . '::bar',
      'ClassName, method' => [Foo::CLASS, 'bar'],
      'Instance, method'  => [$foo, 'bar']
    ];

    foreach ($tests as $name => $scenario) {
      $this->assertEquals(['A', 'B', 'C', $scenario], Container::getDependencyArray($scenario));
    }
  }

  /**
   * @expectedException InvalidArgumentException
   */
  
  public function testParameterExtractionFailure()
  {
    Container::getDependencyArray('string-no-argument');
  }

  public function testInjectorAndContainerAreInjectable()
  {
    $gotCalled = false;

    $container = (new Container)
      ->factory('Test', ['Injector', 'Container', function($injector, $container) use (&$gotCalled) {
        $this->assertInstanceOf(Container::CLASS, $container);
        $this->assertInstanceOf(Injector::CLASS, $injector);

        $gotCalled = true;
      }]);

    $container['Test'];

    $this->assertTrue($gotCalled);
  }

  public function testOffsetSet()
  {
    $container = new Container;
    $container['Test'] = 'Value';

    $this->assertEquals('Value', $container['Test']);
  }

  public function testMagicSetterAndGetter()
  {
    $container = new Container;
    $container->Test = 'Value';

    $this->assertEquals('Value', $container->Test);
  }

  /**
   * @expectedException Exception
   */
  
  public function testOffsetUnsetIsNotImplementes()
  {
    $container = new Container;
    unset($container['Test']);
  }


  /**
   * @expectedException InvalidArgumentException
   */
  
  public function testInvalidCacheClassName()
  {
    Container::setCacheClassName(Foo::CLASS);
  }

  public function testCacheClassName()
  {
    Container::setCacheClassName(ArrayCache::CLASS);
  }
}