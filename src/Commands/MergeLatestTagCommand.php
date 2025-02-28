<?php

namespace Spoof\Commands;

use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MergeLatestTagCommand extends PrMergeCommand
{
    /**
     * Configure this command.
     */
    protected function configure(): void
    {
        $this->setName('latest')
            ->setDescription('Fake a pull request merge event from the latest release into $target.')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Full repository name, including the owner. Example: styxit/deployments'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'The branch name that is the target of the pr merge event'
            );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int The exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeLn('');

        list($repositoryOwner, $repositoryName) = explode('/', $input->getArgument('repository'));

        // Get the latest tag.
        $client = new Client();
        $response = $client->request(
            'GET',
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', $repositoryOwner, $repositoryName),
            [
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                    'Content-Type' => 'application/json',
                    'Authorization' => sprintf('token %s', $_ENV['GITHUB_TOKEN']),
                ],
            ]
        );

        if ($input->getOption('verbose')) {
            $output->writeln('Response:');
            $output->writeLn('  Status: '.$response->getStatusCode());
            $output->writeLn('  Body: '.$response->getBody()->getContents());
            $output->writeLn('');
        }

        $release = json_decode($response->getBody(), true);

        return $this->call($input, $output, [
            'owner' => $repositoryOwner,
            'name' => $repositoryName,
            'from' => $release['tag_name'],
            'target' => $input->getArgument('target'),
        ]);
    }
}
