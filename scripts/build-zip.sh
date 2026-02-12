#!/usr/bin/env bash
#
# Build a clean WordPress.org submission ZIP for Gawain AI Video.
#
# Usage:  ./scripts/build-zip.sh
# Output: gawain-wordpress-plugin.zip in the repo root.
#
# The ZIP extracts into a single top-level folder:
#   gawain-wordpress-plugin/
#
# Guarantees:
#   - No hidden files (dotfiles) are included.
#   - No .git/ directory or git metadata.
#   - languages/ directory is present.
#   - Dev-only directories (scripts/, docs/, .github/) are excluded.
#
set -euo pipefail

PLUGIN_SLUG="gawain-wordpress-plugin"
ZIP_NAME="${PLUGIN_SLUG}.zip"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
STAGING_DIR="$(mktemp -d)"

trap 'rm -rf "${STAGING_DIR}"' EXIT

echo "==> Building ${ZIP_NAME}"

# --- 1. Clean old artifacts ---
rm -f "${REPO_ROOT}/${ZIP_NAME}"

# --- 2. Assemble plugin files into staging ---
# Use a subdirectory so the ZIP extracts into PLUGIN_SLUG/
DEST="${STAGING_DIR}/${PLUGIN_SLUG}"
mkdir -p "${DEST}"

# Files and directories to include in the ZIP.
# Only ship what WordPress needs — no dev/build/CI files.
INCLUDE_FILES=(
    gawain-ai-video.php
    uninstall.php
    readme.txt
    LICENSE
    README.md
)

INCLUDE_DIRS=(
    assets
    includes
    languages
)

cd "${REPO_ROOT}"

for f in "${INCLUDE_FILES[@]}"; do
    if [[ -f "${f}" ]]; then
        cp "${f}" "${DEST}/"
    else
        echo "WARNING: expected file '${f}' not found, skipping."
    fi
done

for d in "${INCLUDE_DIRS[@]}"; do
    if [[ -d "${d}" ]]; then
        # Copy directory, excluding any hidden files/dirs within it
        rsync -a --exclude='.*' "${d}/" "${DEST}/${d}/"
    else
        echo "WARNING: expected directory '${d}' not found, skipping."
    fi
done

# --- 3. Build the ZIP ---
cd "${STAGING_DIR}"
zip -rq "${REPO_ROOT}/${ZIP_NAME}" "${PLUGIN_SLUG}/"

echo "==> Created ${ZIP_NAME}"

# --- 4. Validate: no dotfiles ---
echo "==> Validating: no dotfiles in ZIP..."
DOTFILES=$(zipinfo -1 "${REPO_ROOT}/${ZIP_NAME}" | grep -E '(^|/)\.' || true)
if [[ -n "${DOTFILES}" ]]; then
    echo "FAIL: dotfiles found in ZIP:"
    echo "${DOTFILES}"
    exit 1
fi
echo "    OK — no dotfiles."

# --- 5. Validate: languages/ exists ---
echo "==> Validating: languages/ directory present..."
LANG_ENTRIES=$(zipinfo -1 "${REPO_ROOT}/${ZIP_NAME}" | grep -E "^${PLUGIN_SLUG}/languages/" || true)
if [[ -z "${LANG_ENTRIES}" ]]; then
    echo "FAIL: languages/ directory missing from ZIP."
    exit 1
fi
echo "    OK — languages/ present:"
echo "${LANG_ENTRIES}" | sed 's/^/    /'

# --- Summary ---
echo ""
echo "==> ${ZIP_NAME} is ready for WordPress.org submission."
echo "    Size: $(du -h "${REPO_ROOT}/${ZIP_NAME}" | cut -f1)"
echo "    Contents:"
zipinfo -1 "${REPO_ROOT}/${ZIP_NAME}" | sed 's/^/    /'
