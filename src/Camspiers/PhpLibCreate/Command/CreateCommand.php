<?php

namespace Camspiers\PhpLibCreate\Command;

use Composer\Json\JsonFile;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class CreateCommand
 * @package Camspiers\PhpLibCreate\Command
 */
class CreateCommand extends BaseCommand
{

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('create')
            ->setDescription('Creates a php library based on answered questions.')
            ->setDefinition(
                array(
                    new InputOption(
                        'directory',
                        'd',
                        InputOption::VALUE_OPTIONAL,
                        'Directory to create PHP library in',
                        false
                    )
                )
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \RuntimeException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        $formatter = $this->getHelperSet()->get('formatter');

        $output->writeln(
            array(
                '',
                $formatter->formatBlock('Welcome to PHP library creator', 'bg=blue;fg=white', true),
                ''
            )
        );

        $directory = $this->processDirectory($input, $output, $dialog);

        $this->processGit($output, $directory);

        $projectName = $this->processName($output, $dialog);

        $this->processGitRepo($input, $output, $dialog, $directory, $projectName);

        list($namespace, $namespaceDir) = $this->processNamespace($output, $dialog, $directory);

        $this->processCsFixer($output, $dialog, $directory);

        $phpunit = $this->processPhpUnit($output, $dialog, $directory, $projectName, $namespaceDir);

        $this->processTravis($output, $dialog, $directory);

        chdir($directory);

        $this->processReadme($output, $dialog, $directory, $projectName);

        $this->processComposer($output, $dialog, $directory, $namespace, $phpunit);
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     * @param                 $projectName
     */
    protected function processReadme(OutputInterface $output, $dialog, $directory, $projectName)
    {
        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to create a readme file?', 'yes'),
            'yes'
        ) == 'yes'
        ) {

            $output->writeln('Creating file ' . $directory . '/README.md');

            file_put_contents(
                $directory . '/README.md',
                <<<README
# $projectName

## Installation (with composer)

## Usage

## Unit testing
    $ composer install --dev
    $ vendor/bin/phpunit
README
            );
        }
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     * @param                 $namespace
     * @param                 $phpunit
     */
    protected function processComposer(OutputInterface $output, $dialog, $directory, $namespace, $phpunit)
    {
        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to set up a composer file?', 'yes'),
            'yes'
        ) == 'yes'
        ) {

            $this->getComposerApplication()->find('init')->run(
                new Input\ArrayInput(
                    array(
                        'command' => 'init'
                    )
                ),
                $output
            );

        }

        if (file_exists($directory . '/composer.json')) {

            $composerFile = new JsonFile($directory . '/composer.json');

            $composer = $composerFile->read();

            if (count($composer['require']) === 0) {
                unset($composer['require']);
            }

            if (isset($namespace) && $namespace) {

                $composer = array_merge(
                    $composer,
                    array(
                        'autoload' => array(
                            'psr-0' => array(
                                $namespace => 'src/'
                            )
                        )
                    )
                );

            }

            if (isset($phpunit) && $phpunit) {

                $phpunit = array(
                    'phpunit/phpunit' => '~3.7'
                );

                if (isset($composer['require-dev'])) {
                    $composer['require-dev'] = array_merge($composer['require-dev'], $phpunit);
                } else {
                    $composer['require-dev'] = $phpunit;
                }

            }

            $composerFile->write($composer);

            if ($dialog->ask(
                $output,
                $dialog->getQuestion('Would you like to run "composer install"?', 'yes'),
                'yes'
            ) == 'yes'
            ) {

                if ($dialog->ask(
                    $output,
                    $dialog->getQuestion('Would you like to install dev dependencies?', 'yes'),
                    'yes'
                ) == 'yes') {
                    $requireDev = true;
                } else {
                    $requireDev = false;
                }

                $output->writeln('Running composer install');

                $this->getComposerApplication()->run(
                    new Input\ArrayInput(
                        array(
                            'command' => 'install',
                            '--dev' => $requireDev
                        )
                    )
                );

            }

        }
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     */
    protected function processTravis(OutputInterface $output, $dialog, $directory)
    {
        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to setup travis?', 'yes'),
            'yes'
        ) == 'yes'
        ) {

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
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     * @param                 $projectName
     * @param                 $namespaceDir
     * @return bool
     */
    protected function processPhpUnit(OutputInterface $output, $dialog, $directory, $projectName, $namespaceDir)
    {
        $phpunit = $dialog->ask(
            $output,
            $dialog->getQuestion(
                'Would you like to setup phpunit?',
                'yes'
            ),
            'yes'
        );

        $phpunit = $phpunit == 'yes';

        if ($phpunit) {

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

            $testDirectory = $directory . '/tests/' . (isset($namespaceDir) ? $namespaceDir : '');

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

        return $phpunit;
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     */
    protected function processCsFixer(OutputInterface $output, $dialog, $directory)
    {
        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to add a php-cs-fixer file?', 'yes'),
            'yes'
        ) == 'yes'
        ) {

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
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     * @return array
     * @throws \RuntimeException
     */
    protected function processNamespace(OutputInterface $output, $dialog, $directory)
    {
        $namespace = false;
        $namespaceDir = false;
        if ($dialog->ask(
            $output,
            $dialog->getQuestion('Would you like to namespace your code?', 'yes'),
            'yes'
        ) == 'yes'
        ) {

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
        return array($namespace, $namespaceDir);
    }
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param                 $dialog
     * @param                 $directory
     * @param                 $projectName
     */
    protected function processGitRepo(InputInterface $input, OutputInterface $output, $dialog, $directory, $projectName)
    {
        //Ask to create github repo
        $createGithubRepo = $dialog->ask(
            $output,
            $dialog->getQuestion('Do you want to create a github repository?', 'yes'),
            'yes'
        );

        //if yes
        if ($createGithubRepo == 'yes') {

            $this->getApplication()->find('create-github-repo')->run(
                new Input\ArrayInput(
                    array(
                        'command'   => 'create-github-repo',
                        'directory' => $directory,
                        '--name'    => str_replace(' ', '-', strtolower($projectName))
                    )
                ),
                $output
            );

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
    }
    /**
     * @param OutputInterface $output
     * @param                 $dialog
     * @return mixed
     */
    protected function processName(OutputInterface $output, $dialog)
    {
        $projectName = $dialog->ask(
            $output,
            $dialog->getQuestion('What is your projects name?'),
            ''
        );

        return $projectName;
    }
    /**
     * @param OutputInterface $output
     * @param                 $directory
     */
    protected function processGit(OutputInterface $output, $directory)
    {
        //If the directory isn't a git repository, initialize it
        if (!file_exists($directory . '/.git')) {
            $output->writeln('Creating git repository');
            $this->runAndCheckProcess(new Process('git init', $directory), $output);
        }
    }
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param                 $dialog
     * @return mixed
     * @throws \RuntimeException
     */
    protected function processDirectory(InputInterface $input, OutputInterface $output, $dialog)
    {
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
            $output->writeln('Creating directory ' . $directory);
            $this->runAndCheckProcess(new Process('mkdir ' . $directory), $output);

            return $directory;
        } else {
            if (!is_dir($directory)) {
                throw new RuntimeException('Directory specified is not a directory');
            }

            return $directory;
        }
    }
}
