#!/usr/bin/env bash
#
# snapshot.sh — create a dated, restorable snapshot of the repository.
#
# A snapshot is a dated, annotated git tag (snap-YYYY-MM-DD-HHMM). The tag's
# annotation is the "comment" describing the change. Manual snapshots also write
# a self-contained git bundle and refresh the human-readable index.
#
# Usage:
#   scripts/snapshot.sh "وصف التغيير"   # commit pending work, tag, bundle, index
#   scripts/snapshot.sh --auto          # hook mode: tag only (tree stays clean)
#   scripts/snapshot.sh --push          # also push the tag to origin
#   scripts/snapshot.sh --index         # regenerate .snapshots/SNAPSHOTS.md
#   scripts/snapshot.sh --list          # print the snapshot index
#
# Restore later with:
#   git checkout <tag>                  # or a full copy:
#   git clone .snapshots/<tag>.bundle /tmp/restored && cd /tmp/restored
#
set -uo pipefail

# Never let a snapshot's own commit re-trigger the post-commit hook.
export SNAPSHOT_IN_PROGRESS=1

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || exit 0
cd "$REPO_ROOT" || exit 1

SNAP_DIR=".snapshots"
LOG_FILE="$SNAP_DIR/SNAPSHOTS.md"

MODE="manual"
DESCRIPTION=""
DO_PUSH=0

for arg in "$@"; do
  case "$arg" in
    --auto)   MODE="auto" ;;
    --index)  MODE="index" ;;
    --list)   MODE="list" ;;
    --push)   DO_PUSH=1 ;;
    *)        DESCRIPTION="$arg" ;;
  esac
done

# --- Index: render the dated table straight from the tags -------------------
render_index() {
  echo "# Snapshots (لقطات قابلة للاستعادة)"
  echo
  echo "| التاريخ | الوسم (tag) | commit | ملاحظة |"
  echo "|---|---|---|---|"
  while IFS='|' read -r tag date sha subject body; do
    [ -z "$tag" ] && continue
    note="$body"
    [ -z "$note" ] && note="$subject"
    printf '| %s | `%s` | `%s` | %s |\n' "$date" "$tag" "$sha" "$note"
  done < <(git for-each-ref --sort=creatordate \
    --format='%(refname:short)|%(creatordate:short)|%(*objectname:short)|%(contents:subject)|%(contents:body)' \
    'refs/tags/snap-*')
}

rebuild_index() {
  mkdir -p "$SNAP_DIR"
  render_index > "$LOG_FILE"
}

case "$MODE" in
  index) rebuild_index; echo "index refreshed: $LOG_FILE"; exit 0 ;;
  list)  render_index; exit 0 ;;
esac

DATE="$(date +%Y-%m-%d)"

# --- Manual: capture pending work as a dated commit -------------------------
if [ "$MODE" = "manual" ] && [ -n "$(git status --porcelain)" ]; then
  COMMIT_MSG="snapshot($DATE): ${DESCRIPTION:-حفظ تلقائي قبل التغييرات}"
  git add -A
  git commit -q -m "$COMMIT_MSG

Co-authored-by: openhands <openhands@all-hands.dev>" || true
  echo "committed: $COMMIT_MSG"
fi

# --- Tag name (computed after the commit; add -2, -3 … on collision) --------
STAMP="$(date +%Y-%m-%d-%H%M)"
TAG="snap-$STAMP"
n=1
while git rev-parse -q --verify "refs/tags/$TAG" >/dev/null 2>&1; do
  n=$((n + 1))
  TAG="snap-$STAMP-$n"
done

SHA="$(git rev-parse --short HEAD)"
SUBJECT="$(git log -1 --pretty=%s)"

# --- Annotated tag: the dated comment is the snapshot's identity ------------
git tag -a "$TAG" -m "$TAG ($DATE)" -m "${DESCRIPTION:-$SUBJECT} (commit $SHA)" \
  && echo "tagged: $TAG -> $SHA"

# --- Manual only: self-contained bundle + refreshed, committed index --------
if [ "$MODE" = "manual" ]; then
  BUNDLE="$SNAP_DIR/$TAG.bundle"
  if git bundle create "$BUNDLE" --all >/dev/null 2>&1; then
    echo "bundle: $BUNDLE ($(du -h "$BUNDLE" | cut -f1))"
  else
    echo "bundle: FAILED (tag kept)"
  fi
  rebuild_index
  if [ -n "$(git status --porcelain -- "$LOG_FILE")" ]; then
    git add "$LOG_FILE"
    git commit -q -m "chore(snapshots): index for $TAG ($DATE)" || true
    echo "index committed"
  fi
fi

# --- Optional: push the tag for offsite backup ------------------------------
if [ "$DO_PUSH" = "1" ]; then
  GIT_TERMINAL_PROMPT=0 git push -q origin "$TAG" 2>/dev/null \
    && echo "pushed: $TAG" || echo "push skipped/failed (tag kept locally)"
fi

echo "snapshot ready: $TAG ($SHA) — restore with: git checkout $TAG"
