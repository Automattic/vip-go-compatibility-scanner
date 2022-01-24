# vip-go-compatibility-scanner

Find issues in selected repositories using PHPCS, report to GitHub.

This tool is to be used to search for any (compatibility) issues in VIP Go repositories. It will scan a given repository with PHPCS, using a specified PHPCS standard, and then post GitHub issue for each folder that has any detected issues detailing what was found. It will add labels to each issue created, if specified, and print links to the labels in its output. Note that the tool needs to have the repository cloned and ready to be used when started.

This tool can be used for any GitHub repository and with any PHPCS standard. Issues can be posted on a per-file basis.

The tool will also create Zendesk tickets using the REST API if set up to do so.

The tool uses [vip-go-ci](https://github.com/automattic/vip-go-ci/) as a library, and uses some of its dependencies as well. See below.

Note that the tool has two parts:
 * The `compatibility-scanner.php` script will scan using PHPCS and report to GitHub. It will also save to a local database file the information needed to create Zendesk tickets later, by the other part of this tool.
 * The `zendesk-tickets-create.php` script will create Zendesk tickets with URLs to the GitHub issues. It utilises the local database file for this purpose.

## System requirements

- PHP 7.3 or later. PHP 7.4 is preferred. 
- SQLite support is required if the Zendesk functionality is to be used. Same if PHPCSCacheDB functionality is to be used.
- Linux is a preferred OS for `vip-go-compatibility-scanner`, but it should work on other platforms as well. 
- See instructions for macOS below.


## Installing

Included is a script to install `vip-go-compatibility-scanner`. You can use it like this:

```
wget https://raw.githubusercontent.com/Automattic/vip-go-compatibility-scanner/main/install.sh && bash ./install.sh 
```

This will result in `vip-go-compatibility-scanner`, `vip-go-ci` and other dependencies being installed in your home directory under `vip-go-ci-tools`.

If installation fails due to missing system requirements, once they've been installed you'll need to delete the lock file before attempting the installation again.

```
rm -iv $HOME/.vip-go-ci-tools-init.lck
```

### Note on macOS

On macOS the following requirements also need to be installed:

- wget
- md5sha1sum

You can use the following to install using [Homebrew](https://brew.sh/):

```
brew install wget md5sha1sum
```

If not installed, the installation script will attempt to install these utilities.

## Scanning a single repository

The compatibility-scanner.php script is meant to be used on a per-repo bases. Here is an example of how it can be run:

```
pushd /tmp && \
git clone git@github.com:githubuser/testing123.git && \
pushd testing123 && \
git submodule init && \
git submodule update --recursive && \
popd && popd && \
./compatibility-scanner.php --vipgoci-path="$HOME/vip-go-ci-tools/vip-go-ci/"  --repo-owner="mygithubuser" --repo-name="testing123" --token="xyz" --github-labels='PHP Compatibility' --github-issue-title="PHP Upgrade: Compatibility issues found in " --github-issue-body="The following issues were found when scanning branch <code>%branch_name%</code> for compatibility problems:  %error_msg% This is an automated report." --github-issue-assign="direct" --local-git-repo="/tmp/testing123" --phpcs-path="$HOME/vip-go-ci-tools/phpcs/bin/phpcs" --phpcs-standard="PHPCompatibilityWP" --phpcs-runtime-set='testVersion 7.3-' 
```

Use the `--github-issue-group-by` option to switch between posting issues on a per `file` and `folder` basis.

Instead of specifying the whole of GitHub issue body on the command-line, you can place it in a file and use the `--github-issue-body-file` parameter instead. The file contents should meet the same requirements as the `--github-issue-body` parameter. For example:

```
./compatibility-scanner.php [...] --github-issue-body-file=/tmp/my-github-issue-body.txt
```

To skip reporting of certain PHPCS issues, use the `--phpcs-sniffs-exclude` parameter, like this:

```
./compatibility-scanner.php [...] --phpcs-sniffs-exclude=My.Sniff.function
```

<b>Note:</b> If you want to open up Zendesk tickets later, use the `--zendesk-db` parameter, like this:

```
./compatibility-scanner.php [...] --zendesk-db=/tmp/zendeskdb.sqlite
```

You can run many scans in a sequence and then run the Zendesk script.

You can use the `--dry-run` parameter to do a test run and see how many GitHub issues would be opened.

### Creating Zendesk tickets

If you wish to also create Zendesk tickets to notify about the GitHub issues opened, you can use the `zendesk-tickets-create.php` script. This script will determine, from a CSV file specified, with what users to open up tickets. It will attempt to open up only one ticket per user, listing all the GitHub issues opened up earlier by the scanning script.

Usage is as follow:

```
./zendesk-tickets-create.php --vipgoci-path="$HOME/vip-go-ci-tools/vip-go-ci/" --zendesk-subdomain="myzendesksubdomain" --zendesk-access-username="user@email" --zendesk-access-token="xyz"  --zendesk-ticket-subject="PHP Upgrade: Issues that need solving" --zendesk-ticket-body="Hi! %linebreak% Some issues were found. %linebreak% See issues here: %github_issues_link%" --zendesk-ticket-status=PENDING --zendesk-csv-data-path="file.csv" --zendesk-db="/tmp/zendeskdb.sqlite"
```

The `--zendesk-ticket-body` parameter supports `%linebreak%` strings, which will be replaced with actual line-breaks. You can use the `--zendesk-ticket-body-file` parameter to load the ticket body from a file instead. Line breaks will be preserved.

The `--zendesk-ticket-tags` parameter is optional and supports a comma separated list of tags to be added. 

The `--zendesk-ticket-group-id` parameter is optional as well, and expects an integer. 

The `--zendesk-csv-data-path` parameter should point to a CSV file that is used to pair together the repository and the email address used as assignee of the Zendesk ticket. The CSV should look like this:

```
client_email,source_repo
email@email,repoowner/reponame
```

The first line should always specify columns. You can specify as many repositories and emails as needed.

This script also supports the `--dry-run` parameter; this will output tickets created.

### PHPCSCacheDB support

`vip-go-compatibility-scanner` supports caching PHPCS results for individual files. With this feature enabled, any PHPCS results are cached in a database so that the PHPCS scanner does not have to be run in case a file is encountered that has been scanned before and results have been cached for it. To identify files, SHA hashing is used and stored. PHPCS options used to scan a file are stored as SHA hashes as well. Only if both SHA hash for a file and PHPCS options SHA hash match for a file, the results for the file are re-used.

PHPCS results are cached in a SQLite database and it will grow as more files are scanned. The database uses indexing. 

It is recommended to use the cached database only while scanning a set of repositories and then remove the cached database when scanning of all repositories is complete. Old databases should not be re-used, as the cached results are likely to get obsolete. 

While SHA is used for PHPCS options, the SHA will _not_ incorporate version numbers for either PHPCS or versions for PHPCS standards used. If you upgrade or change versions, the database should be removed, otherwise you may encounter obsolete results.

Note that this feature is experimental and is distinct from the built-in support for caching in PHPCS.

Usage:

```
./compatibility-scanner.php ... --phpcs-cachedb="/tmp/vip-go-compatibility-scanner-phpcs-cachedb.sqlite"
```

## Usage for multiple repositories

Included is a script to run the `vip-go-compatibility-scanner` for multiple repositories. The script will clone each repository into a temporary directory, run `vip-go-compatibility-scanner`, and will leave a log for each execution in the temporary directory. It will remove the repositories cloned after scanning.

Here is how the script can be run:

```
./scan-multiple-repositories.sh mygithubuser testing123,testing999 mytokenxyz "Compatibility-Issue,PHP Compatibility" "PHP Upgrade: Compatibility issues found in " "The following issues were found when scanning for compatibility issues: %error_msg% Note that this is an automated report." direct PHPCompatibilityWP 'testVersion 7.3-' main
```

The parameters are the following, respectively:
 * Repository owner
 * Repository name(s), comma separated
 * GitHub access token
 * Label(s) to apply to newly created GitHub issues, comma separated
 * Title prefix for each issue created
 * Body of created issue. String `%error_msg%` will be replaced by a list of problems noted, and `%branch_name%` with name of branch.
 * Type of admin collaborators to assign issues (direct, outside, all)
 * PHPCS standard to use when scanning
 * PHPCS runtime set
 * Git branch to check out

There is also optional parameter for Zendesk. See help message.

## Tests

To run the tests for `vip-go-compatibility-scanner`, you will need to install `phpunit` and any dependencies needed (this would include `xdebug`).

### PHPUnit configuration file

To run the tests, a configuration file for PHPUnit needs to be set up.

Run:
> cp phpunit.xml.dist phpunit.xml

Replace the string `PROJECT_DIR` with your local project directory. E.g.:
> <directory>PROJECT_DIR/tests/integration</directory>
will be:
> <directory>~/Projects/tests/integration</directory>


### Unit test suite

Run the unit tests using this command:

> phpunit --testsuite=unit-tests -vv

By running this command, you will run tests that do not make external calls nor call external scanners. 

### Integration test suite

First create and adjust the `unittests.ini` file:

> cp unittests.ini.dist unittests.ini

And configure any values as needed.


Run the integration tests using the following command:

> phpunit --testsuite=integration-tests -vv

These tests may create temporary files, and may depend on external scanners, external APIs and so forth. 

## Contributing

If you want to contribute to this project, please see [this file](https://github.com/Automattic/vip-go-ci/blob/main/CONTRIBUTING.md) from the [vip-go-ci project](https://github.com/Automattic/vip-go-ci/) on contributing.
