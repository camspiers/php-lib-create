<?php

namespace Camspiers\PhpLibCreate\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input;
use RuntimeException;

class CreateCommand extends BaseCommand
{

    protected function configure()
    {
        $this
            ->setName('create')
            ->setDescription('Creates a php library based on answered questions.')
            ->setDefinition(array(
                new InputOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'Directory to create PHP library in', false)
            ));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        $formatter = $this->getHelperSet()->get('formatter');

        $output->writeln(array(
            '',
            $formatter->formatBlock('Welcome to PHP library creator', 'bg=blue;fg=white', true),
            ''
        ));

        $directory = $input->getOption('directory');

        if (!$directory) {
            $directory = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('What directory would you like this PHP library to be created in?', getcwd()),
                function ($input) {
                    $input = trim($input);
                    if ($input == '') {
                        throw new RuntimeException('You must enter a valid directory');
                    }

                    return $input;
                },
                3,
                getcwd()
            );
        }

        $directory = $this->processPath($directory);

        //If the directory doesn't exist, create it
        if (!file_exists($directory)) {
            $output->writeln('Creating directory');
            $this->runAndCheckProcess(new Process('mkdir ' . $directory), $output);
        } else {
            if (!is_dir($directory)) {
                throw new RuntimeException('Directory specified is not a directory');
            }
        }
        //If the directory isn't a git repository, initialize it
        if (!file_exists($directory . '/.git')) {
            $output->writeln('Creating git repository');
            $this->runAndCheckProcess(new Process('git init', $directory), $output);
        }

        //Ask to create github repo
        $createGithubRepo = $dialog->ask(
            $output,
            $dialog->getQuestion('Do you want to create a github repository?', 'yes'),
            'yes'
        );

        //if yes
        if ($createGithubRepo == 'yes') {

            $returnCode = $this->getApplication()->find('create-github-repo')->run(new Input\ArrayInput(array(
                'command' => 'create-github-repo',
                'directory' => $directory
            )), $output);

        } else {

            $addExistingOrigin = $dialog->ask(
                $output,
                $dialog->getQuestion('Do you want to add an \'origin\' to this git repo?', 'yes'),
                'yes'
            );

            if ($addExistingOrigin == 'yes') {

                $origin = $dialog->ask(
                    $output,
                    $dialog->getQuestion('What is the origin?'),
                    false
                );

                if ($origin) {
                    $this->addGitOrigin($input, $output, $origin);
                }

            }

        }

        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to namespace your code?', 'yes'),
            'yes'
        ) == 'yes') {

            $namespace = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('What is the namespace?'),
                function ($input) {
                    $input = trim($input);
                    if ($input == '') {
                        throw new RuntimeException('You must enter a valid namespace');
                    }

                    return $input;
                },
                3
            );

            $output->writeln('Creating directory');
            $this->runAndCheckProcess(new Process('mkdir -p src/' . str_replace('\\', '/', $namespace), $directory), $output);

        }

        //namespace
        //
        //php_cs
        //
        //travis
        //
        //bin
        //
        //composer
        //
        //phpunit
        //
        //README.md
        //
    }
}
