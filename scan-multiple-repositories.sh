#!/bin/bash

GITHUB_ORGANISATION="$1"
GITHUB_REPOS="$2"
GITHUB_TOKEN="$3"
GITHUB_LABELS="$4"
PHPCS_STANDARDS="$5"

if [ "" == "$GITHUB_ORGANISATION" ] || [ "" == "$GITHUB_REPOS" ] || [ "" == "$GITHUB_TOKEN" ] || [ "" == "$GITHUB_LABELS" ] || [ "" == "$PHPCS_STANDARDS"  ] ; then
	echo "Scan multiple repositories with vip-go-compatibility-scanner and note issues found"
	echo ""
	echo "Usage: $0 github-organisation github-repos github-token github-labels phpcs-standard"
	echo ""
	echo "          github-organisation: GitHub organisation repositories belong to"
	echo "          github-repos: Comma separated string of repositories to scan"
        echo "          github-token: Access token for GitHub"
 	echo "          github-labels: Labels to apply to issues created. Separate by commas for multiple"
	echo "          phpcs-standards: PHPCS standard(s) to use while scanning. Separate by commas for multiple"
	echo ""
	exit 1
fi


echo "Preparing to process multiple repositories with vip-go-compatibility-scanner:"
echo "-- GitHub organisation: $GITHUB_ORGANISATION"
echo "    --    repositories: $GITHUB_REPOS"
echo "    --    labels to apply: $GITHUB_LABELS"
echo "-- PHPCS standard(s) to use: $PHPCS_STANDARDS"
echo ""
echo -n "Do you want to continue? (Y/N) "

read TMP_CONTINUE_YN

if [ "$TMP_CONTINUE_YN" != "Y" ] ; then
	echo "$0: Not continuing."
	exit 1
fi

TEMP_DIR=`mktemp -d /tmp/vip-go-compatibility-scanner-run-XXXXXXX`

GITHUB_REPOS=`echo $GITHUB_REPOS | sed 's/,/ /g'`

for REPO_NAME in $(echo "$GITHUB_REPOS") ; do
	TEMP_LOG_FILE="$TEMP_DIR/log-$REPO_NAME.txt"

	echo "Scanning $REPO_NAME..."
	echo "Log file will be placed in: $TEMP_LOG_FILE"

	pushd "$TEMP_DIR" && \
	echo "Cloning repository from GitHub..." && \
	git clone "git@github.com:$GITHUB_ORGANISATION/$REPO_NAME.git" && \
	echo "Running scanner..." && \
	popd && \
	./compatibility-scanner.php --repo-owner="$GITHUB_ORGANISATION" --repo-name="$REPO_NAME" --token="$GITHUB_TOKEN" --local-git-repo="$TEMP_DIR/$REPO_NAME" --phpcs-path="$HOME/vip-go-ci-tools/phpcs/bin/phpcs" --phpcs-standard="$PHPCS_STANDARDS"  --vipgoci-path="$HOME/vip-go-ci-tools/vip-go-ci/" --phpcs-runtime-set='testVersion 7.4-' --github-labels="$GITHUB_LABELS" | tee "$TEMP_LOG_FILE" && \
	echo "Processing of $REPO_NAME done." && \
	rm -rf "$TEMP_DIR/$REPO_NAME" && \
	echo "Removed cloned repository from $TEMP_DIR" && \
	sleep 20
 done

echo "Scanning complete. Logs are availabe in $TEMP_LOG_FILE"

exit 0
