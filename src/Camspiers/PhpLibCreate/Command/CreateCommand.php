<?php

namespace Camspiers\PhpLibCreate\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input;
use RuntimeException;
use Composer\Json\JsonFile;

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

        $projectName = $dialog->ask(
            $output,
            $dialog->getQuestion('What is your projects name?'),
            ''
        );

        //Ask to create github repo
        $createGithubRepo = $dialog->ask(
            $output,
            $dialog->getQuestion('Do you want to create a github repository?', 'yes'),
            'yes'
        );

        //if yes
        if ($createGithubRepo == 'yes') {

            $this->getApplication()->find('create-github-repo')->run(new Input\ArrayInput(array(
                'command' => 'create-github-repo',
                'directory' => $directory,
                '--name' => str_replace(' ', '-', strtolower($projectName))
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

            $namespaceDir = str_replace('\\', '/', $namespace);

            $srcDirectory = 'src/' . $namespaceDir;
            $output->writeln('Creating directory ' . $srcDirectory);

            $this->runAndCheckProcess(new Process(sprintf('mkdir -p %s', $srcDirectory), $directory), $output);

        }

        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to add a php-cs-fixer file?', 'yes'),
            'yes'
        ) == 'yes') {

            $output->writeln('Creating file ' . $directory . '/.php_cs');

            file_put_contents(
                $directory . '/.php_cs',
                <<<PHPCS
<?php

\$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->name('*.php')
    ->exclude(array(
        'vendor'
    ))
    ->in(__DIR__);

return Symfony\CS\Config\Config::create()
    ->finder(\$finder);
PHPCS
            );

        }

        if ($phpunit = $dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to setup phpunit?', 'yes'),
            'yes'
        ) == 'yes') {

            $output->writeln('Creating file ' . $directory . '/phpunit.xml.dist');

            file_put_contents(
                $directory . '/phpunit.xml.dist',
                <<<PHPUNIT
<?xml version="1.0" encoding="UTF-8"?>

<phpunit backupGlobals="false"
         backupStaticAttributes="false"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true"
         processIsolation="false"
         stopOnFailure="false"
         syntaxCheck="false"
         bootstrap="tests/bootstrap.php"
>
    <testsuites>
        <testsuite name="$projectName">
            <directory>./tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
PHPUNIT
            );

            $testDirectory = $directory . '/tests/' . (isset($namespace) ? $namespace : '');

            $output->writeln('Creating directory ' . $testDirectory);

            $this->runAndCheckProcess(new Process(sprintf('mkdir -p %s', $testDirectory), $directory), $output);

            $output->writeln('Creating file ' . $directory . '/tests/bootstrap.php');

            file_put_contents(
                $directory . '/tests/bootstrap.php',
                <<<BOOTSTRAP
<?php

\$filename = __DIR__ . '/../vendor/autoload.php';

if (!file_exists(\$filename)) {
    echo 'You must first install the vendors using composer.' . PHP_EOL;
    exit(1);
}

require_once \$filename;
BOOTSTRAP
            );

        }

        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to setup travis?', 'yes'),
            'yes'
        ) == 'yes') {

            $output->writeln('Creating file ' . $directory . '/.travis.yml');

            file_put_contents(
                $directory . '/.travis.yml',
                <<<TRAVIS
language: php

php:
  - 5.3
  - 5.4

before_script:
  - composer self-update
  - composer install --dev
TRAVIS
            );

        }

        chdir($directory);

        $this->getApplication()->find('init')->run(new Input\ArrayInput(array(
            'command' => 'init'
        )), $output);

        //Read result of composer.json, add autoloading stuff, add phpunit if not there, then run composer install

        $composerFile = new JsonFile($directory . '/composer.json');

        $composer = $composerFile->read();

        if (count($composer['require']) === 0) {
            unset($composer['require']);
        }

        $composerFile->write(array_merge(
            $composer,
            array(
                'autoload' => array(
                    'psr-0' => array(
                        $namespace => 'src/'
                    )
                )
            )
        ));

        $output->writeln('Running composer install');

        $this->runAndCheckProcess(
            new Process(
                __DIR__ . str_repeat('/..', 4) . '/vendor/bin/composer install',
                $directory
            ),
            $output
        );

        $output->writeln('Creating file ' . $directory . '/README.md');

        file_put_contents(
            $directory . '/README.md',
            <<<README
# $projectName
README
        );
    }
}
