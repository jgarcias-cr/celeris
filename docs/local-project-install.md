# Local Project Install (Pre-Packagist)

Use this workflow to create a brand-new API or MVC project in any directory using local monorepo packages.

## One-command scaffold

From the monorepo root:

```bash
scripts/create-local-project.sh api /absolute/path/my-api
scripts/create-local-project.sh mvc /absolute/path/my-mvc
```

The script:
- Creates a new project in the target directory.
- Resolves `celeris/api`, `celeris/mvc`, and `celeris/framework` from local `packages/*`.
- Uses host `composer` when available, otherwise falls back to Docker `composer:2`.

## Run the generated app

```bash
cd /absolute/path/my-api
php -S 127.0.0.1:8080 -t public
```

For MVC, replace `my-api` with `my-mvc`.

## Notes

- This is for local development before publishing to Packagist.
- The generated project installs from local path repositories in `dev` stability mode.
