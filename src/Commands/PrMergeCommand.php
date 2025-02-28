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

class PrMergeCommand extends Command
{
    /**
     * Configure this command.
     */
    protected function configure(): void
    {
        $this->setName('merge')
            ->setDescription('Fake a pull request merge event from $from into $target.')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Full repository name, including the owner. Example: styxit/deployments'
            )
            ->addArgument(
                'from',
                InputArgument::REQUIRED,
                'The branch name that is merged into the target'
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

        return $this->call($input, $output, [
            'owner' => $repositoryOwner,
            'name' => $repositoryName,
            'from' => $input->getArgument('from'),
            'target' => $input->getArgument('target'),
        ]);
    }

    /**
     * Call the destination with the merge event.
     *
     * @param InputInterface  $input    The input class.
     * @param OutputInterface $output   The output class.
     * @param array           $settings The settings to include in the call.
     *
     * @return int The exit code.
     */
    protected function call(InputInterface $input, OutputInterface $output, array $settings): int
    {
        $output->writeLn('<info>About to spoof a pull request merge event with the following settings:</info>');
        $output->writeLn('');
        $output->writeLn(sprintf('Repository: <comment>%s/%s</comment>', $settings['owner'], $settings['name']));
        $output->writeln(sprintf('Merge <comment>%s</comment> into <comment>%s</comment>.', $settings['from'], $settings['target']));
        $output->writeLn('');

        // Ask confirmation.
        if ($input->isInteractive() && !$this->confirm($input, $output, 'Is this correct?')) {
            $output->writeLn('User did not confirm. Quit.');

            return 1;
        }

        // Construct payload from template.
        $payload = (new Parser('pr-merge'))->parse(
            [
                'repoName' => $settings['name'],
                'repoOwner' => $settings['owner'],
                'repoFullName' => $settings['owner'].'/'.$settings['name'],
                'from' => $settings['from'],
                'target' => $settings['target'],
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
                    'X-GitHub-Event' => 'merge_pull_request',
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
