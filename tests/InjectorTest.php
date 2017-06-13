<?php

use ieu\Container\Injector;
use ieu\Container\Tracer;

require_once __DIR__ .'/fixtures/Foo.php';


/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class InjectorTest extends PHPUnit_Framework_TestCase {

  public function setUp()
  {
    
  }

  public function testHas()
  {
    $cache = new ArrayObject(['key' => 'value']);
    $injector = new Injector($cache, function(){}, new Tracer);

    $this->assertTrue($injector->has('key'));
    $this->assertFalse($injector->has('unkownKey'));
  }

  public function testGet()
  {
    $cache = new ArrayObject(['key' => 'value']);
    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    $this->assertTrue($injector->has('key'));
    $this->assertFalse($injector->has('unkownKey'));
  }

  public function testInvoke()
  {
    // Depedencies
    $cache = new ArrayObject([
      'key' => 'value'
    ]);

    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    // Usual case
    $injector->invoke(['key', function($value){
      $this->assertEquals('value', $value);
    }]);

    // With local dependencies
    $injector->invoke(['key', 'local', function($value, $local){
      $this->assertEquals('value', $value);
      $this->assertEquals('value2', $local);
    }], ['local' => 'value2']);

    // With local and mandatory dependencies
    $injector->invoke(['key', 'local', function($mand, $value, $local){
      $this->assertEquals('value', $value);
      $this->assertEquals('value2', $local);
      $this->assertEquals('value3', $mand);
    }], ['local' => 'value2'], ['value3']);
  }

  public function testInstantiate()
  {
    // Depedencies
    $cache = new ArrayObject([
      'A' => 1,
      'B' => 2,
      'C' => 3
    ]);

    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    // Usual case
    $foo = $injector->instantiate(['A', 'B', 'C', Foo::CLASS]);
    $this->assertEquals(1, $foo->A);
    $this->assertEquals(2, $foo->B);
    $this->assertEquals(3, $foo->C);

    // With local dependencies
    $foo = $injector->instantiate(['A', 'B', 'C', Foo::CLASS], ['B' => 4]);
    $this->assertEquals(1, $foo->A);
    $this->assertEquals(4, $foo->B);
    $this->assertEquals(3, $foo->C);

    // With local and mandatory dependencies
    $foo = $injector->instantiate(['A', 'B', 'C', Foo::CLASS], ['B' => 4], [5]);
    $this->assertEquals(5, $foo->A);
    $this->assertEquals(1, $foo->B);
    $this->assertEquals(4, $foo->C);
  }

  /**
   * @expectedException InvalidArgumentException
   * @expectedExceptionMessage Instantiation of UnkownClassName not possible.
   */
  
  public function testInstantiateWithUnkownConstructor()
  {
    // Depedencies
    $cache = new ArrayObject([
      'A' => 1,
      'B' => 2,
      'C' => 3
    ]);

    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    $foo = $injector->instantiate(['A', 'B', 'C', 'UnkownClassName']);
  }

  /**
   * @expectedException InvalidArgumentException
   * @expectedExceptionMessage Instantiation of
   */
  
  public function testInstantiateWithInvalidConstructor()
  {
    // Depedencies
    $cache = new ArrayObject([
      'A' => 1,
      'B' => 2,
      'C' => 3
    ]);

    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    $foo = $injector->instantiate(['A', 'B', 'C', ['Array is not constructable']]);
  }
}