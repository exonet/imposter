<?php

namespace Spoof\Tools;

use Exception;
use GuzzleHttp\Client;

/**
 * The parts of the GitHub API needed to make sure a deployable package exists before an event
 * is spoofed. A monorepo deploys from a package built by CI, so spoofing a branch that has no
 * package would send a deploy on a trip to nowhere.
 */
class GitHub
{
    /**
     * @var Client The http client.
     */
    private $client;

    /**
     * @var string The owner of the repository.
     */
    private $owner;

    /**
     * @var string The name of the repository.
     */
    private $name;

    /**
     * @param string $owner The owner of the repository.
     * @param string $name  The name of the repository.
     */
    public function __construct(string $owner, string $name)
    {
        $this->client = new Client();
        $this->owner = $owner;
        $this->name = $name;
    }

    /**
     * The commit a branch points at right now. Pinning the spoof to a commit rather than a
     * branch name is what keeps the package and the deployed code the same thing.
     *
     * @param string $branch The branch to resolve.
     *
     * @return string The commit sha.
     */
    public function headOf(string $branch): string
    {
        return $this->get(sprintf('commits/%s', rawurlencode($branch)))['sha'];
    }

    /**
     * Whether a successful run left a package for this application built from this commit.
     *
     * @param string $application The application, as CI names its artifact.
     * @param string $sha         The commit the package has to be built from.
     *
     * @return bool True when a package is waiting.
     */
    public function hasPackage(string $application, string $sha): bool
    {
        $runs = $this->get(sprintf('actions/runs?head_sha=%s&status=success&per_page=20', $sha));

        foreach ($runs['workflow_runs'] as $run) {
            $artifacts = $this->get(sprintf('actions/runs/%d/artifacts', $run['id']));
            foreach ($artifacts['artifacts'] as $artifact) {
                if ($artifact['name'] === $application && $artifact['expired'] === false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Ask CI to build and pack one application of a branch.
     *
     * @param string $application The application to build.
     * @param string $branch      The branch to build it from.
     */
    public function build(string $application, string $branch): void
    {
        $this->client->post(
            sprintf('https://api.github.com/repos/%s/%s/actions/workflows/ci.yml/dispatches', $this->owner, $this->name),
            [
                'headers' => $this->headers(),
                'json' => ['ref' => $branch, 'inputs' => ['app' => $application]],
            ]
        );
    }

    /**
     * Wait until the package is there, or until the run that should produce it has failed.
     *
     * @param string   $application The application being built.
     * @param string   $sha         The commit it is built from.
     * @param callable $tick        Called once per attempt, for progress output.
     * @param int      $attempts    How many times to look, once every 15 seconds.
     *
     * @throws Exception When the build failed or never delivered.
     */
    public function awaitPackage(string $application, string $sha, callable $tick, int $attempts = 60): void
    {
        for ($attempt = 0; $attempt < $attempts; ++$attempt) {
            sleep(15);
            $tick();

            if ($this->hasPackage($application, $sha)) {
                return;
            }

            // A failed run never produces a package, so say so now instead of waiting out the
            // clock and reporting a timeout for something that is already known to be broken.
            $runs = $this->get(sprintf('actions/runs?head_sha=%s&event=workflow_dispatch&per_page=5', $sha));
            foreach ($runs['workflow_runs'] as $run) {
                if ($run['status'] === 'completed' && $run['conclusion'] !== 'success') {
                    throw new Exception(sprintf('The build failed: %s', $run['html_url']));
                }
            }
        }

        throw new Exception(sprintf('No package for %s at %s after %d minutes.', $application, substr($sha, 0, 7), (int) ($attempts / 4)));
    }

    /**
     * Read a path of this repository from the API.
     *
     * @param string $path The path below /repos/owner/name/.
     *
     * @return array The decoded response.
     */
    private function get(string $path): array
    {
        $response = $this->client->get(
            sprintf('https://api.github.com/repos/%s/%s/%s', $this->owner, $this->name, $path),
            ['headers' => $this->headers()]
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * The headers every call carries.
     *
     * @return string[] The headers.
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/vnd.github.v3+json',
            'Content-Type' => 'application/json',
            'Authorization' => sprintf('token %s', $_ENV['GITHUB_TOKEN']),
        ];
    }
}
