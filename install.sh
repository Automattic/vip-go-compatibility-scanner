#!/bin/bash

#
# Support function to check for utilities
#
function check_utility() {
	TMP_CMD="$1"
	$TMP_CMD --help >/dev/null 2>/dev/null

	if [ "$?" != "0" ] ; then
		echo "$0: Missing utility $TMP_CMD, needs to be installed"
		exit 1
	fi
}

#
# Exit if running as root
#
if [ "$USERNAME" == "root" ] ; then
	echo "$0: Will not run as root, exiting"
	exit 1
fi

#
# Check if $HOME is missing
#
if [ "" == "$HOME" ] ; then
	echo "$0: Missing \$HOME variable"
	exit 1
fi

#
# If needed and on macOS, install utilities.
#
if [[ "$OSTYPE" =~ "darwin" ]] ; then
	TMP_INSTALL=""

	wget --help >/dev/null 2>/dev/null

	if [ "$?" != "0" ] ; then
		TMP_INSTALL="$TMP_INSTALL wget"
	fi

	alias sha1sum='shasum -a 1'

	sha1sum --help >/dev/null 2>/dev/null

	if [ "$?" != "0" ] ; then
		TMP_INSTALL="$TMP_INSTALL md5sha1sum"
	fi

	if [ "$TMP_INSTALL" != "" ] ; then
		echo "Running on macOS, installing support tools via Homebrew ($TMP_INSTALL)"
		sleep 10
		brew install "$TMP_INSTALL"
	fi
fi

#
# Exit if we don't have all the utilities
#

check_utility "wget"
check_utility "sha1sum"
check_utility "mktemp"
check_utility "unzip"

#
# Create temporary directory for scripts
# that will be used.
#
TMP_DIR=`mktemp -d /tmp/vip-go-compatibility-scanner-install-XXXXXXX`

if [ ! -d "$TMP_DIR" ] ; then
	echo "$0: Unable to create temporary directory"
	exit 1
fi

#
# Install or update vip-go-ci-tools
#
if [ ! -d "$HOME/vip-go-ci-tools" ] ; then
	echo "$0: vip-go-ci-tools is not installed, installing"

	cd "$TMP_DIR" && \
	wget https://raw.githubusercontent.com/Automattic/vip-go-ci/main/tools-init.sh && \
	bash tools-init.sh
fi

if [ -d "$HOME/vip-go-ci-tools" ] ; then
	echo "$0: vip-go-ci-tools is installed, not re-installing"

	cd "$HOME/vip-go-ci-tools" && \
	bash tools-init.sh
fi

#
# Check if vip-go-ci is there.
#
if [ ! -d ~/vip-go-ci-tools/vip-go-ci ] ; then
	echo "0: vip-go-ci is missing, cannot continue."
	exit 1
fi

#
# Install vip-go-compatibility-scanner
#
cd "$TMP_DIR" && \
wget https://github.com/Automattic/vip-go-compatibility-scanner/archive/main.zip && \
mv main.zip vip-go-compatibility-scanner.zip && \
unzip vip-go-compatibility-scanner.zip && \
cd "$HOME/vip-go-ci-tools/" && \
rm -rf "$HOME/vip-go-ci-tools/vip-go-compatibility-scanner/" && \
mv "$TMP_DIR/vip-go-compatibility-scanner-main" "$HOME/vip-go-ci-tools/vip-go-compatibility-scanner/" && \
echo "$0: vip-go-compatibility-scanner and dependencies installed" && \
exit 0



