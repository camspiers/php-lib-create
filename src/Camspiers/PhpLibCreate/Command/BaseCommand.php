<?php

namespace Camspiers\PhpLibCreate\Command;

use Composer\Console\Application as ComposerApplication;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class BaseCommand
 * @package Camspiers\PhpLibCreate\Command
 */
abstract class BaseCommand extends Command
{
    /**
     * @param Process         $process
     * @param OutputInterface $output
     * @param null            $callback
     * @throws \RuntimeException
     */
    protected function runAndCheckProcess(Process $process, OutputInterface $output, $callback = null)
    {
        $result = $process->run($callback);
        if ($result !== 0) {
            throw new RuntimeException($process->getErrorOutput());
        }
        $stdout = $process->getOutput();
        if ($stdout !== '') {
            $output->writeln($stdout);
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param                 $origin
     */
    protected function addGitOrigin(InputInterface $input, OutputInterface $output, $origin)
    {
        $output->writeln('Adding git origin');
        $this->runAndCheckProcess(
            new Process(
                sprintf(
                    'git remote add origin %s',
                    $origin
                ),
                $input->getArgument('directory')
            ),
            $output
        );
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function processPath($path)
    {
        return str_replace('~', getenv('HOME'), $path);
    }
    /**
     * @return ComposerApplication
     */
    protected function getComposerApplication()
    {
        return new ComposerApplication();
    }
}
