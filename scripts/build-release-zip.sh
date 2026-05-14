#!/usr/bin/env bash
# Build a distributable ZIP from the current git tree with no dotfiles (e.g. .gitignore, .DS_Store).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SLUG="staging-safe-mode-for-memberpress"
VERSION="$(grep -m1 'Version:' "${SLUG}.php" | awk '{print $3}' | tr -d '\r')"
if [[ -z "${VERSION}" ]]; then
  echo "Could not read Version from ${SLUG}.php" >&2
  exit 1
fi

TMP="$(mktemp -d)"
cleanup() { rm -rf "${TMP}"; }
trap cleanup EXIT

# Exclude maintainer-only paths from the distributable tree.
git archive --format=tar --prefix="${SLUG}/" HEAD -- . ':!scripts' | tar -xC "${TMP}"

# Remove hidden files and directories from the staged tree (not the repo).
find "${TMP}/${SLUG}" -type f -name '.*' -delete
find "${TMP}/${SLUG}" -depth -type d -name '.*' -exec rm -rf {} + 2>/dev/null || true

mkdir -p "${ROOT}/release"
OUT="${ROOT}/release/${SLUG}-${VERSION}.zip"
rm -f "${OUT}"
( cd "${TMP}" && zip -rq "${OUT}" "${SLUG}" )

echo "Wrote ${OUT}"
unzip -l "${OUT}"
