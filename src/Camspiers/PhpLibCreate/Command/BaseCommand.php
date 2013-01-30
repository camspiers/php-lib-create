<?php

namespace Camspiers\PhpLibCreate\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use RuntimeException;

abstract class BaseCommand extends Command
{
    protected function runAndCheckProcess(Process $process, OutputInterface $output)
    {
        $result = $process->run();
        if ($result !== 0) {
            throw new RuntimeException($process->getErrorOutput());
        }
        $stdout = $process->getOutput();
        if ($stdout !== '') {
            $output->writeln($stdout);
        }
    }

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

    protected function processPath($path)
    {
        return str_replace('~', getenv('HOME'), $path);
    }
}
