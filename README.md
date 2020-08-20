# vip-go-compatibility-scanner

Find issues in selected repositories using PHPCS.

This tool is to be used to search for any (compatibility) issues in VIP Go repositories. It will scan a given repository with PHPCS, using a specified PHPCS standard, and then post GitHub issue for each directory that has any detected issues detailing what was found. It will add labels to each issue created, if specified, and print links to the labels in its output. Note that the tool needs to have the repository cloned and ready to be used when started.

This tool can be used for any GitHub repository and with any PHPCS standard. Issues can be posted on a per-file basis.

The tool will also create Zendesk tickets using the REST API if set up to do so.

The tool uses [vip-go-ci](https://github.com/automattic/vip-go-ci/) as a library, and uses some of its dependencies as well. See below.

## Installing

Included is a script to install `vip-go-compatibility-scanner`. You can use it like this:

> wget https://raw.githubusercontent.com/Automattic/vip-go-compatibility-scanner/master/install.sh && bash ./install.sh 

This will result in `vip-go-compatibility-scanner`, `vip-go-ci` and other dependencies being installed in your home directory under `vip-go-ci-tools`.

## Usage for a single repository

The tool itself is meant to be used on a per-repo bases. Here is an example of how it can be run:

```
pushd /tmp && \
git clone git@github.com:githubuser/testing123.git && \
popd && \
./compatibility-scanner.php --vipgoci-path="$HOME/vip-go-ci-tools/vip-go-ci/"  --repo-owner="mygithubuser" --repo-name="testing123" --token="xyz" --github-labels='PHP Compatibility' --github-issue-title="PHP Upgrade: Compatibility issues found in " --github-issue-body="The following issues were found when scanning branch <code>%branch_name%</code> for compatibility problems:  %error_msg% This is an automated report." --github-issue-assign="direct" --local-git-repo="/tmp/testing123" --phpcs-path="$HOME/vip-go-ci-tools/phpcs/bin/phpcs" --phpcs-standard="PHPCompatibilityWP" --phpcs-runtime-set='testVersion 7.4-' 
```

Use the `--github-issue-group-by` option to switch between posting issues on a per `file` and `folder` basis.

### Zendesk functionality

If you wish to also create Zendesk tickets to notify about the issues found, you can add parameters such as these to the command:

```
--zendesk-access-username="user@email" --zendesk-access-token="xyz" --zendesk-subdomain="myzendesksubdomain" --zendesk-ticket-subject="PHP Upgrade: Issues that need solving" --zendesk-ticket-body="Hi! %linebreak% Some issues were found. %linebreak% See issues here: %github_issues_link%" --zendesk-csv-data-path="file.csv"
```

The `--zendesk-ticket-body` parameter supports `%linebreak%` strings, which will be replaced with actual line-breaks. 

The `--zendesk-csv-data-path` parameter should point to a CSV file that is used to pair together the repository and the email address used as assignee of the Zendesk ticket. The CSV should look like this:

```
client_email,source_repo
email@email,repoowner/reponame
```

The first line should always specify columns. You can specify as many repositories and emails as needed.

## Usage for multiple repositories

Included is a script to run the `vip-go-compatibility-scanner` for multiple repositories. The script will clone each repository into a temporary directory, run `vip-go-compatibility-scanner`, and will leave a log for each execution in the temporary directory. It will remove the repositories cloned after scanning.

Here is how the script can be run:

```
./scan-multiple-repositories.sh mygithubuser testing123,testing999 mytokenxyz "Compatibility-Issue,PHP Compatibility" "PHP Upgrade: Compatibility issues found in " "The following issues were found when scanning for compatibility issues: %error_msg% Note that this is an automated report." direct PHPCompatibilityWP 'testVersion 7.4-' master
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

There are also optional parameters for Zendesk. See help message.

