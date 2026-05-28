# agents.md -- Notifications

## Repository Overview

Notification backend and UI for ownCloud Server. Provides the notification infrastructure that other apps use to create and display user notifications. Licensed under AGPL-3.0.

## Architecture & Key Paths

- `lib/` -- PHP application logic
- `js/` -- Frontend JavaScript (notification panel)
- `css/` -- Stylesheets
- `templates/` -- Server-side templates
- `appinfo/` -- ownCloud app metadata
- `l10n/` -- Translation files
- `docs/` -- Developer documentation (API reference)
- `tests/` -- Unit tests
- `Makefile` -- Build and test automation
- `composer.json` -- PHP dependencies

## Development Conventions

- PHP code follows ownCloud coding standards (phpcs)
- Static analysis with PHPStan and Phan

## Build & Test Commands

```bash
make dist                     # Build distribution package
make test-php-unit            # Run PHP unit tests
make test-php-style           # Check PHP code style
make test-php-phpstan         # Run PHPStan static analysis
make test-acceptance-api      # Run API acceptance tests
```

## Important Constraints

- Licensed under AGPL-3.0 (copyleft). Apache 2.0 migration planned.
- Other OC10 apps depend on this app's notification API.
- All contributions require a DCO sign-off.


## OSPO Policy Constraints

### GitHub Actions
- **Only** use actions owned by `owncloud`, created by GitHub (`actions/*`), verified on the GitHub Marketplace, or verified by the ownCloud Maintainers.
- Pin all actions to their full commit SHA (not tags): `uses: actions/checkout@<SHA> # vX.Y.Z`
- Never introduce actions from unverified third parties.

### Dependency Management
- Dependabot is configured for automated dependency updates.
- Review and merge Dependabot PRs as part of regular maintenance.
- Do not introduce new dependencies without discussion in an issue first.

### Git Workflow
- **Rebase policy**: Always rebase; never create merge commits. Use `git pull --rebase` and `git rebase` before pushing.
- **Signed commits**: All commits **must** be PGP/GPG signed (`git commit -S -s`).
- **DCO sign-off**: Every commit needs a `Signed-off-by` line (`git commit -s`).
- **Conventional Commits & Squash Merge**: Use the [Conventional Commits](https://www.conventionalcommits.org/) format where the repository enforces it. Many repos use squash merge, where the PR title becomes the commit message on the default branch — apply Conventional Commits format to PR titles as well. A reusable GitHub Actions workflow enforces this.

## Context for AI Agents

This app provides both the UI (bell icon/dropdown) and the backend API for notifications. Other apps register notification providers. The `docs/` directory contains the notification workflow and OCS endpoint documentation.
