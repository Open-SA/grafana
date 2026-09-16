#!/usr/bin/env bash

# Build a local test release zip, replicating the steps from
# .github/workflows/github_release.yml (checkout -> composer install --no-dev ->
# package into a "grafana/" folder -> zip), without touching the working tree.
# This is a dev-only convenience script, not tied to git tags or the real
# release process — the name is whatever you want for the local build.
#
# Usage: tools/build-release.sh <name> [git-ref]
#   name     Suffix for the zip name, e.g. "glpi10-test" (required)
#   git-ref  Commit/branch/tag to build from (default: HEAD)
#
# Output: ../../releases/grafana-<name>.zip (i.e. plugins/releases/ next to this plugin)

set -euo pipefail

PLUGIN_NAME="grafana"
BUILD_NAME="${1:?Usage: $0 <name> [git-ref]   e.g. $0 glpi10-test}"
REF="${2:-HEAD}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
RELEASES_DIR="$(cd "${REPO_DIR}/.." && pwd)/releases"

if [[ -n "$(git -C "${REPO_DIR}" status --porcelain)" ]]; then
    echo "WARNING: uncommitted changes detected in ${REPO_DIR}." >&2
    echo "         They will NOT be included — this build only packages what's" >&2
    echo "         committed at ${REF}, same as the real CI checkout." >&2
fi

BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "${BUILD_DIR}"' EXIT

BRANCH="$(git -C "${REPO_DIR}" branch --show-current 2>/dev/null || echo "detached")"
SHA="$(git -C "${REPO_DIR}" rev-parse --short "${REF}")"
echo "==> Exporting ${REF} (${SHA}) from branch '${BRANCH}'"
git -C "${REPO_DIR}" archive "${REF}" | tar -x -C "${BUILD_DIR}"

echo "==> Installing production Composer dependencies"
(cd "${BUILD_DIR}" && composer install --no-dev --optimize-autoloader --quiet)

ZIP_NAME="${PLUGIN_NAME}-${BUILD_NAME}.zip"
echo "==> Packaging ${ZIP_NAME}"
(
    cd "${BUILD_DIR}"
    mkdir "${PLUGIN_NAME}"
    for item in $(ls -A); do
        case "${item}" in
            "${PLUGIN_NAME}"|".git"|".git-hooks"|".gitlab-ci.yml"|".github"|"tests"|".gitignore"|".php-cs-fixer.php"|"phpstan.neon"|"phpunit.xml"|"sonar-project.properties"|"psalm.xml"|"stubs")
                ;;
            *)
                mv "${item}" "${PLUGIN_NAME}/"
                ;;
        esac
    done
    zip -rq "${ZIP_NAME}" "${PLUGIN_NAME}"
)

mkdir -p "${RELEASES_DIR}"
mv "${BUILD_DIR}/${ZIP_NAME}" "${RELEASES_DIR}/"

echo "==> Done: ${RELEASES_DIR}/${ZIP_NAME}"
