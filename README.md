# imposter
Tool to fake GitHub webhook events.

## Setup
- Install dependencies with `composer install`.
- Duplicate the `.env.example` file to `.env`
- Adjust settings in `.env`

## Usage
Use `php spoof --help` to get more info about the commands.

### Pull request merge
To spoof a pull request merge event, use:
```
php spoof merge styxit/imposter myBranch develop
```

This will fake an event as if branch "myBranch" was merged into branch "develop", for repository "styxit/imposter".

#### Monorepos: deploying a branch that has no package
A repository holding more than one application deploys from a package CI builds, and that package
belongs to a commit rather than to a branch. Name the application and imposter takes care of it:

```
php spoof merge exonet/frontend myBranch dev --app=admin
```

This resolves the branch to its current commit, checks whether CI already left a package for that
commit, asks CI to build one when it did not, waits for it, and only then spoofs the event, with
the commit in it instead of the branch name. Without `--app` the command behaves exactly as it
always has.

### Merge event based on the latest tag
To spoof a pull requeste merge event based on the latest tag, use:
```
php spoof latest styxit/imposter develop
```

This will fake an event as if the latest tag (for example v1.1.0) was merged into branch "develop", for repository "styxit/imposter".

### Release published
To spoof a "release published" event, use:
```
php spoof release styxit/imposter v1.0.2
```

This will fake an event as if tag "v1.0.2" has been published for repository "styxit/imposter".

## Config
In the `.env` the following options can be specified:

##### DESTINATION_URL
This is the url to which the event is POST-ed. Usually your own webhook.

##### GITHUB_SECRET
The secret string you provided when setting up the webhook at GitHub. This used to sign your request.

##### GITHUB_TOKEN
The auth token used to fetch the latest release for a given repository from the Github API, and,
with `--app`, to look up packages and start a build. That last one needs write access to Actions;
reading is enough without it.
