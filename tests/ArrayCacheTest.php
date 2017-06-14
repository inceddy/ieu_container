<?php

use ieu\Container\ArrayCache;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class ArrayCacheTest extends PHPUnit_Framework_TestCase {

  public function testFailingGet()
  {
  	$cache = new ArrayCache;
  	$this->assertEmpty($cache->get('empty'));
  }


  public function testSuccessfullSet()
  {
  	$cache = new ArrayCache;
  	$this->assertTrue($cache->set('key', 'value'));
  }

  public function testSuccessfullSetMultiple()
  {
  	$cache = new ArrayCache;
  	$this->assertTrue($cache->setMultiple([
  		'key1' => 1,
  		'key2' => 2
  	]));
  }

	public function testSuccessfullGetMultiple()
	{
  	$cache = new ArrayCache;
  	$cache->setMultiple([
  		'key1' => 1,
  		'key2' => 2
  	]);

  	$this->assertEquals(['key1' => 1, 'key2' => 2], $cache->getMultiple(['key1', 'key2']));
	}


	public function testFailedGetMultiple()
	{
  	$cache = new ArrayCache;
  	$cache->setMultiple([
  		'key1' => 1,
  		'key2' => 2
  	]);

  	$this->assertEquals(['key1' => 1, 'key3' => null], $cache->getMultiple(['key1', 'key3']));
	}

  public function testFailingHas()
  {
  	$cache = new ArrayCache;
  	$this->assertFalse($cache->has('empty'));
  }


  public function testSuccessfullHas()
  {
  	$cache = new ArrayCache;
  	$cache->set('key', 'value');
  	$this->assertTrue($cache->has('key'));
  	
  }

  public function testSuccessfullGet()
  {
  	$cache = new ArrayCache;
  	$cache->set('key', 'value');
  	$this->assertEquals('value', $cache->get('key'));
  }

  public function testDelete()
  {
  	$cache = new ArrayCache;
  	$cache->set('key', 'value');
  	$this->assertEquals('value', $cache->get('key'));

  	$cache->delete('key');
  	$this->assertNull($cache->get('key'));
  }

  public function testDeleteMultiple()
  {
  	$cache = new ArrayCache;
  	$cache->setMultiple([
  		'key1' => 'value1',
  		'key2' => 'value2'
  	]);

  	$this->assertEquals('value1', $cache->get('key1'));
  	$this->assertEquals('value2', $cache->get('key2'));

  	$cache->deleteMultiple(['key1', 'key2']);
  	$this->assertNull($cache->get('key1'));
  	$this->assertNull($cache->get('key2'));
  }

  public function testClear()
  {
  	$cache = new ArrayCache;
  	$cache->setMultiple([
  		'key1' => 'value1',
  		'key2' => 'value2'
  	]);

  	$this->assertEquals('value1', $cache->get('key1'));
  	$this->assertEquals('value2', $cache->get('key2'));

  	$cache->clear();
  	$this->assertNull($cache->get('key1'));
  	$this->assertNull($cache->get('key2'));
  }

  public function testInvalidKey()
  {
  	$cache = new ArrayCache;
  	$tester = function($key) use ($cache) {
	  	try {
	  		$cache->set($key, 'value');
	  		$this->assertEquals('', $key);
	  	} 
	  	catch (Exception $e) {
	  		$this->assertInstanceOf(InvalidArgumentException::CLASS, $e);
	  	}
  	};

  	// Test invalid keys
  	// - Empty
  	// - Whitespace
  	// - Too long
  	// - Special chars
  	foreach(['', ' ', str_repeat('a', 65), '@', ''] as $key) {
  		$tester($key);
  	}

  }

}