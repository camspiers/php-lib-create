<?php

namespace Camspiers\PhpLibCreate;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class Compiler
{
    protected $version;
    /**
     * Compiles classifier into a single phar file
     *
     * @throws \RuntimeException
     * @param  string            $pharFile The full path to the file to create
     */
    public function compile($pharFile = 'php-lib-create.phar')
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $process = new Process('git log --pretty="%H" -n1 HEAD', __DIR__);

        if ($process->run() != 0) {
            throw new \RuntimeException('Can\'t run git log. You must ensure to run compile from composer git repository clone and that git binary is available.');
        }

        $this->version = trim($process->getOutput());

        $process = new Process('git describe --tags HEAD');

        if ($process->run() == 0) {
            $this->version = trim($process->getOutput());
        }

        $phar = new \Phar($pharFile, 0, $pharFile);
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->in(__DIR__);

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->in(__DIR__ . '/../../../config');

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $vendorDir = realpath(__DIR__ . '/../../../vendor');

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->exclude('Tests')
            ->in("$vendorDir/composer/composer/src/")
            ->in("$vendorDir/justinrainbow/json-schema/src/")
            ->in("$vendorDir/knplabs/github-api/lib/")
            ->in("$vendorDir/kriswallsmith/buzz/lib/")
            ->in("$vendorDir/seld/jsonlint/src/")
            ->in("$vendorDir/symfony/");

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $this->addFile($phar, new \SplFileInfo("$vendorDir/autoload.php"));
        $this->addFile($phar, new \SplFileInfo("$vendorDir/composer/autoload_namespaces.php"));
        $this->addFile($phar, new \SplFileInfo("$vendorDir/composer/autoload_classmap.php"));
        $this->addFile($phar, new \SplFileInfo("$vendorDir/composer/autoload_real.php"));
        if (file_exists("$vendorDir/composer/include_paths.php")) {
            $this->addFile($phar, new \SplFileInfo("$vendorDir/composer/include_paths.php"));
        }
        $this->addFile($phar, new \SplFileInfo("$vendorDir/composer/ClassLoader.php"));
        $this->addBin($phar);

        // Stubs
        $phar->setStub($this->getStub());

        $phar->stopBuffering();

        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../../LICENSE'), false);

        unset($phar);

        file_put_contents('php-lib-create.phar.version', trim($this->version));
    }

    private function addFile($phar, $file)
    {
        $path = str_replace(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR, '', $file->getRealPath());

        $content = php_strip_whitespace($file);
        if (basename($path) !== 'SelfUpdateCommand.php') {
            $content = str_replace('~package_version~', $this->version, $content);
        }
        $content = str_replace(realpath(__DIR__ . '/../../../'), '.', $content);

        $phar->addFromString($path, $content);
        $phar[$path]->compress(\Phar::GZ);
    }

    private function addBin($phar)
    {
        $content = file_get_contents(__DIR__ . '/../../../bin/php-lib-create');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('bin/php-lib-create', $content);
    }

    private function getStub()
    {
        return <<<'EOF'
#!/usr/bin/env php
<?php
/**
 * This file is part of the PHP Library Creator package.
 *
 * (c) Cam Spiers <camspiers@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Phar::mapPhar('php-lib-create.phar');

require 'phar://php-lib-create.phar/bin/php-lib-create';

__HALT_COMPILER();
EOF;
    }
}