#!/bin/bash

GITHUB_ORGANISATION="$1"
GITHUB_REPOS="$2"
GITHUB_TOKEN="$3"
GITHUB_LABELS="$4"
GITHUB_ISSUE_TITLE="$5"
GITHUB_ISSUE_BODY="$6"
GITHUB_ISSUE_ASSIGN="$7"
PHPCS_STANDARDS="$8"
PHPCS_RUNTIME_SET="$9"
GIT_BRANCH="${10}"
ZENDESK_SUBDOMAIN="${11}"
ZENDESK_ACCESS_USERNAME="${12}"
ZENDESK_ACCESS_TOKEN="${13}"
ZENDESK_TICKET_SUBJECT="${14}"
ZENDESK_TICKET_BODY="${15}"
ZENDESK_TICKET_TAGS="${16}"
ZENDESK_CSV_DATA_PATH="${17}"

if [ "" == "$GITHUB_ORGANISATION" ] || [ "" == "$GITHUB_REPOS" ] || [ "" == "$GITHUB_TOKEN" ] || [ "" == "$GITHUB_LABELS" ] || [ "" == "$GITHUB_ISSUE_TITLE" ]|| [ "" == "$GITHUB_ISSUE_BODY" ]|| [ "" == "$GITHUB_ISSUE_ASSIGN" ]  || [ "" == "$PHPCS_STANDARDS" ] || [ "" == "$PHPCS_RUNTIME_SET" ] ; then
	echo "Scan multiple repositories with vip-go-compatibility-scanner and note issues found"
	echo ""
	echo "Usage: $0 github-organisation github-repos github-token github-labels github-issue-title github-issue-body github-issue-assign phpcs-standard phpcs-runtime-set git-branch zendesk-subdomain zendesk-access-username zendesk-access-token zendesk-ticket-subject zendesk-ticket-body zendesk-ticket-tags zendesk-csv-data-path"
	echo ""
	echo "          github-organisation: GitHub organisation repositories belong to"
	echo "          github-repos: Comma separated string of repositories to scan"
	echo "          github-token: Access token for GitHub"
 	echo "          github-labels: Labels to apply to issues created. Separate by commas for multiple"
	echo "          github-issue-title: Title to use for created issues"
	echo "          github-issue-body: Explanatory text for each GitHub issue created, include %error_msg% for list of problems"
	echo "          github-issue-assign: Assign to type of admins: all, direct or outside"
	echo "          phpcs-standards: PHPCS standard(s) to use while scanning. Separate by commas for multiple"
	echo "          phpcs-runtime-set: Runtime parameter to set, for example: \"testVersion 7.2-\""
	echo "          git-branch: Git branch to check out. 'master' is default."
	echo "          zendesk-subdomain: Subdomain for Zendesk API."
	echo "          zendesk-access-username: Username for Zendesk REST API authentication."
	echo "          zendesk-access-token: Access token to use with Zendesk REST API."
	echo "          zendesk-ticket-subject: Subject to use for new Zendesk tickets."
	echo "          zendesk-ticket-body: Body to use for new Zendesk tickets."
	echo "          zendesk-ticket-tags: Tags to use for new Zendesk tickets."
	echo "          zendesk-csv-data-path: Path to CSV data file."
	echo ""
	exit 1
fi

if [ "" == "$GIT_BRANCH" ] ; then
	GIT_BRANCH="master"
fi

echo "Preparing to process multiple repositories with vip-go-compatibility-scanner:"
echo "-- GitHub organisation: $GITHUB_ORGANISATION"
echo "    --    repositories: $GITHUB_REPOS"
echo "    --    labels to apply: $GITHUB_LABELS"
echo "    --    issue title to use: $GITHUB_ISSUE_TITLE"
echo "    --    issue body: $GITHUB_ISSUE_BODY"
echo "    --    issue assign to admins: $GITHUB_ISSUE_ASSIGN"
echo "-- PHPCS standard(s) to use: $PHPCS_STANDARDS"
echo "    --   runtime set: $PHPCS_RUNTIME_SET"
echo "-- Git branch: $GIT_BRANCH"
echo "-- Zendesk subdomain: $ZENDESK_SUBDOMAIN"
echo "     --    access username: $ZENDESK_ACCESS_USERNAME"
echo "     --    ticket subject: $ZENDESK_TICKET_SUBJECT"
echo "     --    ticket body: $ZENDESK_TICKET_BODY"
echo "     --    ticket tags: $ZENDESK_TICKET_TAGS"
echo "     --    CSV data file: $ZENDESK_CSV_DATA_PATH"
echo ""
echo -n "Do you want to continue? (Y/N) "

read TMP_CONTINUE_YN

if [ "$TMP_CONTINUE_YN" != "Y" ] ; then
	echo "$0: Not continuing."
	exit 1
fi

TEMP_DIR=`mktemp -d /tmp/vip-go-compatibility-scanner-run-XXXXXXX`

GITHUB_REPOS=`echo $GITHUB_REPOS | sed 's/,/ /g'`

if [ "" != "$ZENDESK_SUBDOMAIN" ] ; then
	ZENDESK_SUBDOMAIN="--zendesk-subdomain=$ZENDESK_SUBDOMAIN"
	ZENDESK_ACCESS_USERNAME="--zendesk-access-username=$ZENDESK_ACCESS_USERNAME"
	ZENDESK_ACCESS_TOKEN="--zendesk-access-token=$ZENDESK_ACCESS_TOKEN"
	ZENDESK_TICKET_SUBJECT="--zendesk-ticket-subject=$ZENDESK_TICKET_SUBJECT"
	ZENDESK_TICKET_BODY="--zendesk-ticket-body=$ZENDESK_TICKET_BODY"
	ZENDESK_TICKET_TAGS="--zendesk-ticket-tags=$ZENDESK_TICKET_TAGS"
	ZENDESK_CSV_DATA_PATH="--zendesk-csv-data-path=$ZENDESK_CSV_DATA_PATH"
fi

for REPO_NAME in $(echo "$GITHUB_REPOS") ; do
	TIMESTAMP=`date +%s`
	TEMP_LOG_FILE="$TEMP_DIR/log-$REPO_NAME-$TIMESTAMP.txt"

	echo "Scanning $REPO_NAME..."
	echo "Log file will be placed in: $TEMP_LOG_FILE"

	pushd "$TEMP_DIR" && \
	echo "Cloning repository from GitHub..." && \
	git clone "git@github.com:$GITHUB_ORGANISATION/$REPO_NAME.git" && \
	cd $REPO_NAME && \
	git checkout "$GIT_BRANCH" && \
	cd .. && \
	echo "Running scanner..." && \
	popd && \
	./compatibility-scanner.php --repo-owner="$GITHUB_ORGANISATION" --repo-name="$REPO_NAME" --token="$GITHUB_TOKEN" --github-issue-title="$GITHUB_ISSUE_TITLE" --github-issue-body="$GITHUB_ISSUE_BODY" --github-issue-assign="$GITHUB_ISSUE_ASSIGN" --local-git-repo="$TEMP_DIR/$REPO_NAME" --phpcs-path="$HOME/vip-go-ci-tools/phpcs/bin/phpcs" --phpcs-standard="$PHPCS_STANDARDS"  --vipgoci-path="$HOME/vip-go-ci-tools/vip-go-ci/" --phpcs-runtime-set="$PHPCS_RUNTIME_SET" --github-labels="$GITHUB_LABELS" "${ZENDESK_SUBDOMAIN[@]}" "${ZENDESK_ACCESS_USERNAME[@]}" "${ZENDESK_ACCESS_TOKEN[@]}" "${ZENDESK_TICKET_SUBJECT[@]}" "${ZENDESK_TICKET_BODY[@]}" "${ZENDESK_TICKET_TAGS[@]}" "${ZENDESK_CSV_DATA_PATH[@]}" | tee "$TEMP_LOG_FILE" && \
	echo "Processing of $REPO_NAME done." && \
	rm -rf "$TEMP_DIR/$REPO_NAME" && \
	echo "Removed cloned repository from $TEMP_DIR" && \
	sleep 20
 done

echo "Scanning complete. Logs are availabe in $TEMP_LOG_FILE"

exit 0
