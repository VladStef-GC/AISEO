#!/usr/bin/env bash
#
# Build a clean, distribution-ready ZIP of the SEO Captain plugin.
#
# The output zip contains the FULL premium source (all Pro files plus the
# is__premium_only() guards and the @fs_premium_only header tag). Freemius'
# build server is what generates the stripped Free build and the Pro build
# from this single upload — so this script only removes development/CI cruft,
# never plugin code.
#
# Usage:  bash bin/build.sh
# Output: dist/ai-seo-captain.zip   (top-level folder: ai-seo-captain/)
#
set -euo pipefail

SLUG="ai-seo-captain"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT}/build"
STAGE_DIR="${BUILD_DIR}/${SLUG}"
DIST_DIR="${ROOT}/dist"
ZIP_PATH="${DIST_DIR}/${SLUG}.zip"

echo "==> Cleaning previous build output"
rm -rf "${BUILD_DIR}" "${DIST_DIR}"
mkdir -p "${STAGE_DIR}" "${DIST_DIR}"

echo "==> Staging plugin files (excluding dev/CI artifacts)"
# NOTE: the whole vendor/ tree is excluded here (it holds Composer dev
# dependencies that bloat the zip and are never loaded at runtime — the plugin
# uses its own includes/autoload.php). Only vendor/freemius is re-added below.
rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.gitignore' \
  --exclude='.gitattributes' \
  --exclude='build' \
  --exclude='dist' \
  --exclude='bin' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='test-steps.php' \
  --exclude='phpunit.xml' \
  --exclude='phpunit.xml.dist' \
  --exclude='.phpunit.result.cache' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='composer.phar' \
  --exclude='docs' \
  --exclude='*.md' \
  --exclude='.DS_Store' \
  --exclude='Thumbs.db' \
  "${ROOT}/" "${STAGE_DIR}/"

# Re-add only the Freemius SDK (required at runtime).
if [ -d "${ROOT}/vendor/freemius" ]; then
  echo "==> Adding vendor/freemius (required runtime SDK)"
  mkdir -p "${STAGE_DIR}/vendor"
  rsync -a "${ROOT}/vendor/freemius" "${STAGE_DIR}/vendor/"
fi

# readme.txt (WordPress.org) is a .txt file and is intentionally preserved;
# only Markdown docs (README.md, PLAN-*.md, AUDIT-REPORT.md, ...) are removed.

echo "==> Creating ${ZIP_PATH}"
( cd "${BUILD_DIR}" && zip -rq "${ZIP_PATH}" "${SLUG}" )

echo "==> Done."
echo "    $(du -h "${ZIP_PATH}" | cut -f1)  ${ZIP_PATH}"
