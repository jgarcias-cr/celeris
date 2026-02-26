#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/create-local-project.sh <api|mvc> <target-directory>

Examples:
  scripts/create-local-project.sh api /home/user/dev/my-api
  scripts/create-local-project.sh mvc ../my-mvc
USAGE
}

if [[ $# -ne 2 ]]; then
  usage
  exit 1
fi

project_type="$1"
target_dir="$2"

case "$project_type" in
  api) package_name="celeris/api" ;;
  mvc) package_name="celeris/mvc" ;;
  *)
    echo "Invalid project type: $project_type"
    usage
    exit 1
    ;;
esac

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$target_dir" != /* ]]; then
  target_dir="$(cd "$(pwd)" && pwd)/$target_dir"
fi

if [[ -e "$target_dir" ]]; then
  echo "Target already exists: $target_dir"
  exit 1
fi

target_parent="$(dirname "$target_dir")"
target_name="$(basename "$target_dir")"
mkdir -p "$target_parent"

repo_json_host="$(printf '{"type":"path","url":"%s/packages/*","options":{"symlink":false}}' "$repo_root")"
repo_json_container='{"type":"path","url":"/repo/packages/*","options":{"symlink":false}}'

run_with_host_composer() {
  composer create-project "$package_name" "$target_dir" \
    --stability=dev \
    --no-interaction \
    --repository="$repo_json_host" \
    --add-repository \
    --no-install

  (
    cd "$target_dir"
    composer config minimum-stability dev
    composer config prefer-stable true
    composer install --no-interaction
  )
}

run_with_docker_composer() {
  if ! command -v docker >/dev/null 2>&1; then
    echo "Neither composer nor docker is available on PATH."
    exit 1
  fi

  docker run --rm \
    -v "$repo_root":/repo \
    -v "$target_parent":/out \
    -w /repo \
    composer:2 \
    sh -lc "git config --global --add safe.directory /repo && composer create-project $package_name /out/$target_name --stability=dev --no-interaction --repository='$repo_json_container' --add-repository --no-install && cd /out/$target_name && composer config minimum-stability dev && composer config prefer-stable true && composer install --no-interaction"
}

if command -v composer >/dev/null 2>&1; then
  run_with_host_composer
else
  run_with_docker_composer
fi

cat <<EOF
Created $project_type project at:
  $target_dir

Next:
  cd $target_dir
  php -S 127.0.0.1:8080 -t public
EOF
