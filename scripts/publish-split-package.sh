#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/publish-split-package.sh <prefix> <target-repository> [source-ref]

Environment:
  SPLIT_PUSH_TOKEN  Required. Token with push access to the target repository.
  TARGET_BRANCH     Optional. Branch to update in the target repository.
  TARGET_TAG        Optional. Tag to create or update in the target repository.

Examples:
  SPLIT_PUSH_TOKEN=... TARGET_BRANCH=main \
    scripts/publish-split-package.sh packages/framework celeris/framework

  SPLIT_PUSH_TOKEN=... TARGET_TAG=v1.0.0 \
    scripts/publish-split-package.sh packages/api-stub celeris/api 0123abcd
USAGE
}

if [[ $# -lt 2 || $# -gt 3 ]]; then
  usage
  exit 1
fi

prefix="$1"
target_repository="$2"
source_ref="${3:-HEAD}"
target_branch="${TARGET_BRANCH:-}"
target_tag="${TARGET_TAG:-}"
split_push_token="${SPLIT_PUSH_TOKEN:-}"

if [[ -z "$split_push_token" ]]; then
  echo "SPLIT_PUSH_TOKEN is required." >&2
  exit 1
fi

if [[ -z "$target_branch" && -z "$target_tag" ]]; then
  echo "Set TARGET_BRANCH, TARGET_TAG, or both." >&2
  exit 1
fi

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

if [[ ! -d "$prefix" ]]; then
  echo "Package prefix does not exist: $prefix" >&2
  exit 1
fi

remote_name="split-${target_repository//[^[:alnum:]]/-}"
remote_url="https://x-access-token:${split_push_token}@github.com/${target_repository}.git"

cleanup() {
  git remote remove "$remote_name" >/dev/null 2>&1 || true
}

trap cleanup EXIT

git remote add "$remote_name" "$remote_url"

split_commit="$(git subtree split --prefix="$prefix" "$source_ref")"

if [[ -n "$target_branch" ]]; then
  echo "Pushing ${prefix} to ${target_repository}@${target_branch} from ${source_ref}"
  git push --force "$remote_name" "${split_commit}:refs/heads/${target_branch}"
fi

if [[ -n "$target_tag" ]]; then
  echo "Pushing ${prefix} tag ${target_tag} to ${target_repository} from ${source_ref}"
  git push --force "$remote_name" "${split_commit}:refs/tags/${target_tag}"
fi
