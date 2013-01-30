<?php

namespace Camspiers\PhpLibCreate\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use RuntimeException;
use Github\Client;

class CreateGithubCommand extends BaseCommand
{
    protected $githubClient;

    public function __construct(Client $githubClient)
    {
        $this->githubClient = $githubClient;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('create-github-repo')
            ->setDescription('Creates a git hub repository.')
            ->setDefinition(array(
                new InputArgument('directory', InputArgument::OPTIONAL, 'Directory to add the github origin to', getcwd())
            ));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        $formatter = $this->getHelperSet()->get('formatter');

        //Use while to allow for failed attempts
        while (true) {

            //Ask for github username
            $username = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('What is your github username?'),
                function ($input) {
                    $input = trim($input);
                    if ($input == '') {
                        throw new RuntimeException('You must enter a valid username');
                    }

                    return $input;
                },
                3
            );

            //Ask for their password, hide the response
            $password = $dialog->askHiddenResponseAndValidate(
                $output,
                $dialog->getQuestion('What is your github password?'),
                function ($input) {
                    $input = trim($input);
                    if ($input == '') {
                        throw new RuntimeException('You must enter a valid password');
                    }

                    return $input;
                },
                3
            );

            //Set up auth with the githubClient
            $this->githubClient->authenticate($username, $password, Client::AUTH_HTTP_PASSWORD);

            //Retrive the repo api from the client
            $repoApi = $this->githubClient->api('repo');

            //Get the name of the repo they want to create
            $name = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('What do you want to call this github repo?'),
                function ($input) {
                    $input = trim($input);
                    if ($input == '') {
                        throw new RuntimeException('You must enter a valid name');
                    }

                    return $input;
                },
                3
            );

            //Get the description if any
            $description = $dialog->ask(
                $output,
                $dialog->getQuestion('Would you like to add a description, if so what?', 'no'),
                ''
            );

            if ($description == 'no') {
                $description = '';
            }

            $homepage = $dialog->ask(
                $output,
                $dialog->getQuestion('Would you like to add a homepage, if so what?', 'no'),
                'no'
            );

            if ($homepage == 'no') {
                $homepage = '';
            }

            $public = $dialog->ask(
                $output,
                $dialog->getQuestion('Is this a private repository?', 'no'),
                'no'
            ) !== 'no' ? false : true;

            $organization = $dialog->ask(
                $output,
                $dialog->getQuestion('Is this for an organization, if so what is its name?', 'no'),
                'no'
            );

            if ($organization == 'no') {
                $organization = null;
            }

            $output->writeln(array(
                '',
                $formatter->formatBlock('Your repo will be created using the following information', 'bg=blue;fg=white', true),
                ''
            ));

            $output->writeln(array(
                'Username: ' . $username,
                'Name: ' . $name,
                'Description: '. $description,
                'Homepage: '. $homepage,
                'Public: ' . ($public ? 'true' : 'false'),
                'Organization: '. $organization ?: ''
            ));

            if ($dialog->ask(
                $output,
                $dialog->getQuestion('Is the information provided correct?', 'yes'),
                'yes'
            ) == 'yes') {

                $createGithubRepo = false;
                try {
                    $repoApi->create($name, $description, $homepage, $public, $organization);
                } catch (\Github\Exception\RuntimeException $e) {
                    $output->writeln(array(
                        $formatter->formatBlock($e->getMessage(), 'bg=red;fg=white', true)
                    ));
                    continue;
                }

                $info = $repoApi->show($username, $name);

                if (isset($info['clone_url'])) {
                    $this->addGitOrigin($input, $output, $info['clone_url']);
                }

            } else {

                if ($dialog->ask(
                    $output,
                    $dialog->getQuestion('Do you want to create a github repository?', 'yes'),
                    'yes'
                ) !== 'yes') {
                    break;
                }

            }

        }

    }

}
