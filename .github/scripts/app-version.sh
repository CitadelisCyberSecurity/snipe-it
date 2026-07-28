#!/usr/bin/env bash
#
# Derive the upstream Snipe-IT version this fork is built from and export it to
# the workflow environment as APP_VERSION (8.6.3) and APP_VERSION_MINOR (8.6).
#
# config/version.php is upstream's own version file, carried in by the upstream
# sync, so it is the source of truth for "which Snipe-IT release is this?". The
# container tags are derived from it rather than from a fork git tag: the fork
# adds commits on top of an upstream release, so several fork builds legitimately
# share one upstream version, and there is no fork tag to name them after.
#
# Used by the build job (to label the image) and the merge job (to tag it), in
# both docker-ubuntu.yml and docker-alpine.yml.
set -euo pipefail

VERSION_FILE="${1:-config/version.php}"

if [[ ! -f "${VERSION_FILE}" ]]; then
  echo "::error::${VERSION_FILE} not found" >&2
  exit 1
fi

# Matches:  'app_version' => 'v8.6.3',   (the leading v is optional)
app_version="$(
  sed -n "s/^[[:space:]]*'app_version'[[:space:]]*=>[[:space:]]*'v\{0,1\}\([0-9]\+\.[0-9]\+\.[0-9]\+\)'.*/\1/p" \
    "${VERSION_FILE}" | head -n1
)"

# Fail loudly rather than pushing an image tagged with an empty string: a
# malformed or reformatted version file must break the build, not silently
# publish a mislabelled image.
if [[ -z "${app_version}" ]]; then
  echo "::error::Could not parse a X.Y.Z app_version from ${VERSION_FILE}" >&2
  sed -n '1,15p' "${VERSION_FILE}" >&2
  exit 1
fi

echo "Upstream app version: ${app_version} (minor series ${app_version%.*})"

# GITHUB_ENV is absent when running this locally to check the parse.
if [[ -n "${GITHUB_ENV:-}" ]]; then
  {
    echo "APP_VERSION=${app_version}"
    echo "APP_VERSION_MINOR=${app_version%.*}"
  } >> "${GITHUB_ENV}"
fi
