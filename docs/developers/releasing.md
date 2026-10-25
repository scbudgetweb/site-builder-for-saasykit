# Publishing a release

The package lives in the Hall Booker repository at `packages/site-builder-for-saasykit/`. You keep working on it there, and push a copy of just that folder, with its history, to its own GitHub repository whenever you release.

All commands run from the app's folder:

```bash
cd ~/Herd/hall-booker
```

## First release

### 1. Keep package commits separate where you can

The package repository's history shows the message of every commit that touched the package folder. Commit package changes on their own, with messages that make sense outside Hall Booker, whenever possible.

### 2. Create an empty repository on GitHub

1. Go to <https://github.com/new>.
2. **Owner:** `scbudgetweb`. **Repository name:** `site-builder-for-saasykit`.
3. **Description:** `Branding, a plain theme, pages built from blocks, a block library, navigation and legal pages for SaaSykit apps, managed in Filament.`
4. Choose **Private** or **Public**. SaaSykit is a paid product, so think about who should see a package built for it.
5. Leave **Add a README**, **.gitignore** and **licence** unticked. The package already has them, and an empty repository avoids a clash on the first push.
6. Click **Create repository**.

### 3. Add GitHub as a second remote (once)

```bash
git remote add site-builder https://github.com/scbudgetweb/site-builder-for-saasykit.git
```

(With SSH keys, use `git@github.com:scbudgetweb/site-builder-for-saasykit.git`.)

### 4. Split the package out and push it

```bash
git subtree split --prefix=packages/site-builder-for-saasykit -b site-builder-release
git push site-builder site-builder-release:main
git branch -D site-builder-release
```

1. The first command makes a temporary branch holding only the package folder's files and history, at the top level.
2. The second pushes it to the repository's `main` branch. If asked to sign in, use the `scbudgetweb` account, with a personal access token as the password (<https://github.com/settings/tokens>).
3. The third deletes the temporary branch.

The package's `vendor/`, `composer.lock` and test caches are in its `.gitignore`, so they're never pushed.

### 5. Tag the release

1. Move the `## Unreleased` section of `CHANGELOG.md` under a version heading, for example `## 1.0.0 - 2026-11-01`, and commit it.
2. Split and push again (step 4).
3. On GitHub, open **Releases › Draft a new release**, create the tag `v1.0.0` on `main`, use `v1.0.0` as the title, paste that CHANGELOG section as the description, and publish.

### 6. Installing it elsewhere

To install it in another app from a private repository, add the repository to that app's `composer.json`, then require it:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/scbudgetweb/site-builder-for-saasykit" }
]
```

```bash
composer require scbudgetweb/site-builder-for-saasykit:^1.0
```

For a public package, submit it to <https://packagist.org> instead, signed in with GitHub as `scbudgetweb`.

Then follow the installation steps in the package's [README](../../README.md).

## Releasing an update

1. Make the changes in `packages/site-builder-for-saasykit/` and add them to `CHANGELOG.md` under a new version.
2. Run the package's own tests from inside its folder (`composer install`, then `composer test`).
3. Commit, then split and push (step 4). Only the new commits are sent.
4. Tag the version on GitHub:
   - `v1.0.1` for fixes;
   - `v1.1.0` for new features;
   - `v2.0.0` for changes that need apps to change their code, config or views.

## Things to know

- **Hall Booker keeps using its own copy** through a `path` repository (symlinked into `vendor/`). Changes take effect straight away and deploy with the app.
- **Changes made on GitHub** (for example a merged pull request): copy the changed files into `packages/site-builder-for-saasykit/` here and commit them. Don't pull GitHub's history back into this repository.
- **Migrations are loaded, not published**, so apps get new migrations with a plain `php artisan migrate` after updating.
