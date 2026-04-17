#!/bin/bash

# Nimbus Control Panel - Smart Installer
# Skips already-installed dependencies and jumps into Nimbus setup.
# Usage: curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/dev/install-smart.sh | sudo bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_INSTALL_SCRIPT="${SCRIPT_DIR}/install.sh"
REMOTE_INSTALL_SCRIPT="https://raw.githubusercontent.com/sudhirrajai/Nimbus/dev/install.sh"

if [ -f "$LOCAL_INSTALL_SCRIPT" ]; then
    exec bash "$LOCAL_INSTALL_SCRIPT" --skip-existing "$@"
fi

curl -fsSL "$REMOTE_INSTALL_SCRIPT" | bash -s -- --skip-existing "$@"
