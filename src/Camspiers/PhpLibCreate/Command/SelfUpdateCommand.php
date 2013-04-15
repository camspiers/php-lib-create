<?php


namespace Camspiers\PhpLibCreate\Command;

use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Console\Command\Command;

class SelfUpdateCommand extends Command
{
    /**
     * Configure the commands options
     * @return null
     */
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Update php-lib-create');
    }
    /**
     * @param Input\InputInterface   $input
     * @param Output\OutputInterface $output
     * @return int|null|void
     * @throws \RuntimeException
     */
    protected function execute(Input\InputInterface $input, Output\OutputInterface $output)
    {
        $url = "http://camspiers.github.io/php-lib-create/php-lib-create.phar";
        $version = trim($this->getApplication()->getVersion());

        if ($version === '~package_version~') {
            throw new \RuntimeException("This command is only available for compiled phar files which you can obtain at $url");
        }

        $latest = @file_get_contents("$url.version");

        if (false === $latest) {
            throw new \RuntimeException(sprintf('Could not fetch latest version. Please try again later.'));
        }

        if ($version !== trim($latest)) {

            $output->writeln(
                sprintf(
                    'Updating from <info>%s</info> to <info>%s</info>',
                    $version,
                    $latest
                )
            );

            $tmpFile = tempnam(sys_get_temp_dir(), 'php-lib-create').'.phar';

            if (false === @copy($url, $tmpFile)) {
                throw new \RuntimeException(sprintf('Could not download new version'));
            }

            $phar = new \Phar($tmpFile);

            unset($phar);

            if (false === @rename($tmpFile, $_SERVER['argv'][0])) {
                throw new \RuntimeException(sprintf('Could not deploy new file to "%s".', $_SERVER['argv'][0]));
            }

            $output->writeln('php-lib-create updated.');

        } else {
            $output->writeln('You are already using the latest version.');
        }
    }
}