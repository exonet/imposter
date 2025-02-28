<?php

namespace Spoof\Commands;

use GuzzleHttp\Client;
use Spoof\Template\Parser;
use Spoof\Tools\Signer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PublishReleaseCommand extends Command
{
    /**
     * Configure this command.
     */
    protected function configure(): void
    {
        $this->setName('release')
            ->setDescription('Fake the release-published event for $tag.')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Full repository name, including the owner. Example: styxit/deployments'
            )
            ->addArgument(
                'tag',
                InputArgument::REQUIRED,
                'The tag to fake. Example: v1.0.2'
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

        $output->writeLn('<info>About to spoof a "release published" event with the following settings:</info>');
        $output->writeLn('');
        $output->writeLn(sprintf('Repository: <comment>%s/%s</comment>', $repositoryOwner, $repositoryName));
        $output->writeln(
            sprintf(
                'Release tag: <comment>%s</comment>.',
                $input->getArgument('tag')
            )
        );
        $output->writeLn('');

        // Ask confirmation.
        if ($input->isInteractive() && !$this->confirm($input, $output, 'Is this correct?')) {
            $output->writeLn('User did not confirm. Quit.');

            return 1;
        }

        // Construct payload from template.
        $payload = (new Parser('release-published'))->parse(
            [
                'repoName' => $repositoryName,
                'repoOwner' => $repositoryOwner,
                'repoFullName' => $repositoryOwner.'/'.$repositoryName,
                'tagName' => $input->getArgument('tag'),
            ]
        );

        // Get the payload signature.
        $signature = (new Signer())->sign($payload);

        $output->writeLn('Spoofing the event...');

        // Construct and execute the request.
        $client = new Client();
        $response = $client->post(
            $_ENV['DESTINATION_URL'],
            [
                'body' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Hub-Signature' => 'sha1='.$signature,
                    'X-GitHub-Event' => 'release',
                ],
            ]
        );

        if ($input->getOption('verbose')) {
            $output->writeln('Response:');
            $output->writeLn('  Status: '.$response->getStatusCode());
            $output->writeLn('  Body: '.$response->getBody()->getContents());
            $output->writeLn('');
        }

        $output->writeLn('Done.');

        return 0;
    }

    /**
     * Ask a question the user must answer with 'y' or 'n'.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $question The question to ask.
     *
     * @return bool True when the user entered 'y', False otherwise.
     */
    private function confirm(InputInterface $input, OutputInterface $output, string $question = 'Ok?'): bool
    {
        $helper = $this->getHelper('question');
        $confirmQuestion = new ConfirmationQuestion($question.' [y/N]: ', false);

        if (!$helper->ask($input, $output, $confirmQuestion)) {
            return false;
        }

        return true;
    }
}
