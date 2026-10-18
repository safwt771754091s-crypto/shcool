#!/usr/bin/env bash
#
# setup_hooks.sh — install the version-controlled git hooks.
#
# Git only runs hooks from .git/hooks by default, and that directory is not
# versioned. So we keep the real hook in scripts/hooks/ and point git at it via
# core.hooksPath. Re-running is safe.
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT" || exit 1

chmod +x scripts/hooks/* scripts/snapshot.sh 2>/dev/null || true
git config core.hooksPath scripts/hooks
echo "hooks installed: core.hooksPath -> scripts/hooks"
