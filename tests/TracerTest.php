<?php

use ieu\Container\Injector;
use ieu\Container\Tracer;

/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class TracerTest extends PHPUnit_Framework_TestCase {

  public function testTracer()
  {
    $tracer = new Tracer;

    $tracer->request('Test');
    $tracer->dependsOn(['A', 'B']);
    $tracer->note('Some note');
    $tracer->received('Test');

    $this->assertEquals("Test requested (\n\tDepedencies: [A, B]\n\tNote: Some note\n)", (string)$tracer);
  }

}