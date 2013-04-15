<?php

namespace Camspiers\PhpLibCreate\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Helper\HelperInterface;

class Application extends BaseApplication
{
    const VERSION = '~package_version~';

    public function __construct()
    {
        parent::__construct('PHP Library Create', self::VERSION);
    }

    public function addHelper(HelperInterface $helper)
    {
        $this->getHelperSet()->set($helper);
    }
}
