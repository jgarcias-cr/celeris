# Monorepo Split Publishing

This repository can stay the development monorepo while publishing these standalone Composer packages from GitHub:

- `packages/framework` -> `celeris/framework`
- `packages/api-stub` -> `celeris/api`
- `packages/mvc-stub` -> `celeris/mvc`

The workflow for that lives at [.github/workflows/split-packages.yml](/media/Data/dev/celeris/.github/workflows/split-packages.yml:1) and uses `git subtree split` to push each package directory into its own repository root.

## GitHub Setup

Create these target repositories before enabling the workflow:

- `celeris/framework`
- `celeris/api`
- `celeris/mvc`

Add this repository secret:

- `MONOREPO_SPLIT_TOKEN`

The token should belong to a bot or service account with `contents:write` access to each split repository.

Add these repository variables:

- `CELERIS_FRAMEWORK_REPO=celeris/framework`
- `CELERIS_API_REPO=celeris/api`
- `CELERIS_MVC_REPO=celeris/mvc`

If you use a different GitHub owner, change only the variable values. The workflow does not need to change.

## Branch Sync

When code is pushed to `main`, the workflow force-pushes fresh split histories to the `main` branch of each target repository.

This keeps the split repos aligned with the monorepo and avoids merge commits or manual cherry-picks in the published repos.

## Release Tags

Package releases are driven by package-specific tags pushed in the monorepo:

- `framework-v1.0.0` publishes tag `v1.0.0` to `celeris/framework`
- `api-v1.0.0` publishes tag `v1.0.0` to `celeris/api`
- `mvc-v1.0.0` publishes tag `v1.0.0` to `celeris/mvc`

The workflow strips the package prefix and pushes the remaining tag name into the split repository.

## Packagist Setup

Submit each split repository to Packagist as its own package:

- `https://github.com/<owner>/framework`
- `https://github.com/<owner>/api`
- `https://github.com/<owner>/mvc`

After submission:

1. Ensure the split repositories contain the package `composer.json` at their repository root.
2. Enable Packagist auto-update hooks for each split repository.
3. Create stable tags in the monorepo using the package-prefixed tag scheme above.

With the current package manifests, stable `v1.x.y` tags in `celeris/framework`, `celeris/api`, and `celeris/mvc` are what make these commands work without dev flags:

```bash
composer create-project celeris/mvc hr
composer create-project celeris/api services/hr
```

## Manual Publishing

The workflow also supports manual runs through `workflow_dispatch`.

Use that when you want to:

- republish only one package
- split from a specific commit SHA
- create a split-repo tag during a manual run
