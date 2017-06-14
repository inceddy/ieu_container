<?php

namespace ieu\Container;
use InvalidArgumentException as PhpInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

class CacheInvalidArgumentException extends PhpInvalidArgumentException implements PsrInvalidArgumentException {}