#!/bin/bash

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
	wget https://raw.githubusercontent.com/Automattic/vip-go-ci/master/tools-init.sh && \
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
wget https://github.com/Automattic/vip-go-compatibility-scanner/archive/master.zip && \
mv master.zip vip-go-compatibility-scanner.zip && \
unzip vip-go-compatibility-scanner.zip && \
cd "$HOME/vip-go-ci-tools/" && \
rm -rf "$HOME/vip-go-ci-tools/vip-go-compatibility-scanner/" && \
mv "$TMP_DIR/vip-go-compatibility-scanner-master" "$HOME/vip-go-ci-tools/vip-go-compatibility-scanner/" && \
echo "$0: vip-go-compatibility-scanner and dependencies installed" && \
exit 0



