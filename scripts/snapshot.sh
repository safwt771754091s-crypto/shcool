#!/usr/bin/env bash
#
# snapshot.sh — create a dated, restorable snapshot of the repository.
#
# A snapshot is three things, all keyed by the same tag:
#   1. an annotated git tag  (snap-YYYY-MM-DD-HHMM[-<n>])  — the point to return to
#   2. a git bundle          (.snapshots/<tag>.bundle)    — a self-contained copy
#   3. a dated log entry     (.snapshots/SNAPSHOTS.md)    — the "comment" + date
#
# Usage:
#   scripts/snapshot.sh "وصف التغيير"      # commit pending work, tag, bundle, log
#   scripts/snapshot.sh --auto             # hook mode: tag + log only (no bundle)
#   scripts/snapshot.sh --push             # also push the tag to origin
#
# Restore later with:
#   git checkout <tag>                     # or:
#   git clone .snapshots/<tag>.bundle /tmp/restored && cd /tmp/restored
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT" || exit 1

SNAP_DIR=".snapshots"
LOG_FILE="$SNAP_DIR/SNAPSHOTS.md"

MODE="manual"
DESCRIPTION=""
DO_PUSH=0

for arg in "$@"; do
  case "$arg" in
    --auto)   MODE="auto" ;;
    --push)   DO_PUSH=1 ;;
    --no-bundle) MODE="auto" ;;
    *)        DESCRIPTION="$arg" ;;
  esac
done

DATE="$(date +%Y-%m-%d)"
STAMP="$(date +%Y-%m-%d-%H%M)"

# --- Tag name (append -2, -3 … if one already exists this minute) -----------
TAG="snap-$STAMP"
n=1
while git rev-parse -q --verify "refs/tags/$TAG" >/dev/null 2>&1; do
  n=$((n + 1))
  TAG="snap-$STAMP-$n"
done

# --- 1. Capture pending work as a dated commit ------------------------------
# In manual mode we commit anything uncommitted so the snapshot is complete.
# In auto mode the hook already ran after a commit, so the tree is clean.
if [ "$MODE" = "manual" ] && [ -n "$(git status --porcelain)" ]; then
  COMMIT_MSG="snapshot($DATE): ${DESCRIPTION:-حفظ تلقائي قبل التغييرات}"
  git add -A
  git commit -q -m "$COMMIT_MSG

Co-authored-by: openhands <openhands@all-hands.dev>" || true
  echo "committed: $COMMIT_MSG"
fi

SHA="$(git rev-parse --short HEAD)"
SUBJECT="$(git log -1 --pretty=%s)"

# --- 2. Annotated tag with the dated comment --------------------------------
TAG_MSG="snapshot $TAG ($DATE)
${DESCRIPTION:-$SUBJECT}
commit $SHA"
git tag -a "$TAG" -m "$TAG_MSG" && echo "tagged: $TAG -> $SHA"

# --- 3. Self-contained bundle (skipped in --auto to keep it light) ----------
mkdir -p "$SNAP_DIR"
BUNDLE_NOTE="—"
if [ "$MODE" = "manual" ]; then
  BUNDLE="$SNAP_DIR/$TAG.bundle"
  if git bundle create "$BUNDLE" --all >/dev/null 2>&1; then
    BUNDLE_NOTE="$(du -h "$BUNDLE" | cut -f1)"
    echo "bundle: $BUNDLE ($BUNDLE_NOTE)"
  else
    BUNDLE_NOTE="(failed)"
    echo "bundle: FAILED"
  fi
fi

# --- 4. Dated log entry (the human-readable "comment") ----------------------
if [ ! -f "$LOG_FILE" ]; then
  {
    echo "# Snapshots (لقطات قابلة للاستعادة)"
    echo
    echo "| التاريخ | الوسم (tag) | commit | الحجم | ملاحظة |"
    echo "|---|---|---|---|---|"
  } > "$LOG_FILE"
fi
printf '| %s | `%s` | `%s` | %s | %s |\n' \
  "$DATE" "$TAG" "$SHA" "$BUNDLE_NOTE" "${DESCRIPTION:-$SUBJECT}" >> "$LOG_FILE"

# --- 5. Optional: push the tag for offsite backup ---------------------------
if [ "$DO_PUSH" = "1" ]; then
  GIT_TERMINAL_PROMPT=0 git push -q origin "$TAG" 2>/dev/null \
    && echo "pushed: $TAG" || echo "push skipped/failed (tag kept locally)"
fi

echo "snapshot ready: $TAG ($SHA) — restore with: git checkout $TAG"
