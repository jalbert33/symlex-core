<?php

namespace Symlex\Kernel;

/**
 * @author Michael Mayer <michael@lastzero.net>
 * @license MIT
 */
class ConsoleApp extends App
{
    public function __construct($appPath, $debug = false)
    {
        parent::__construct('console', $appPath, $debug);
    }

    public function setUp()
    {
        chdir($this->getAppPath());
        set_time_limit(0);
        ini_set('memory_limit', '-1');
    }
}