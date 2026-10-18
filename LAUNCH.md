# SEO Pro Cookie Consent launch state

Version: 1.0.1

First GitHub release: 0.1.0 (2026-10-17), a pre-1.0 beta; 1.0.0 on
2026-10-18. The repository is public since 2026-10-18 (the owner's say), so
sites get updates from GitHub without a token.

## Public since 2026-10-18

Before the switch: gitleaks over every ref (167 commits) found only expired
aidevops dispatch-claim lease markers in the `aidevops/issues-archive`
branch, no credentials; code, history, issues and pull requests name no
private paths or client sites. The `CI_RUNNER` variable was removed (no
self-hosted runner for a public repository; the runner groups refuse public
repositories too).

On now:

- Branch ruleset `main`: no deletion or force push, squash merges through a
  pull request, and Lint, Release build and both Smoke tests required (not
  "up to date"); repository admins can bypass. `SYNC_PAT` is set, so
  Repository metrics can still push `docs/metrics/`.
- Secret scanning with push protection, Dependabot alerts, private
  vulnerability reporting, CodeQL default setup (Actions, JavaScript), and
  approval for workflows from first-time contributors' forks.
- SonarCloud (public project `wpallstars_seoprocookieconsent`, `SONAR_TOKEN`
  set), Codacy (ESLint and PHP Mess Detector use the repository's files) and
  OpenSSF Scorecard, each with its README badge.

Still to do (`DEVELOPMENT.md` → At public launch, Services setup):

- [ ] CodeFactor: add the repository on codefactor.io, then
  `scripts/readme-badges.sh --add codefactor`.
- [ ] CodeRabbit full review of the whole codebase.
- [ ] Fix or explain what the reviewers find, in small pull requests by area
  (security first).

## Before 1.0

Tick these off; `scripts/preflight-release.sh` checks most of them (Starter
leftovers lists what is still the starter's).

- [x] Description: the `Description:` header and `readme.txt`'s short
  description (150 characters at most) say what this plugin does, not the
  starter's.
- [x] `README.md` and `readme.txt` open with this plugin's own offering (what
  it does, for whom, why it differs), then getting started and a guide. Keep
  both credits (Built with AI, Made from).
- [x] `readme.txt`: under 10 KB, up to 5 tags, Tested up to the latest
  WordPress, FAQ, an External services section for every service the plugin
  contacts, one Screenshots caption per screenshot.
- [x] Banner and icon: in `.wordpress-org/banner.svg` replace the headline and
  tagline, and replace the starter's plug on the stack with this plugin's own
  mark (the same in `.wordpress-org/icon.svg`); run `scripts/build-banner.sh`
  and check the icon reads at 128 px.
- [x] Screenshots of this plugin in `.wordpress-org/screenshot-N.png`
  (`scripts/build-banner.sh` makes the View details copies).
- [x] `AGENTS.md` describes this plugin; `DESIGN.md` and search keywords, if
  the plugin has them, are current.
- [x] Version 1.0.0 everywhere: `Version:`, the version constant, `Stable tag:`,
  `README.md`'s Version line and the three changelogs.
- [x] `scripts/sync-core.sh --check` lists no differences from the starter.
- [x] Checks pass: `scripts/lint.sh`, `scripts/preflight-release.sh` (no
  errors; each warning understood), `scripts/smoke-test.sh`,
  `scripts/plugin-check.sh` (no errors on either zip).
- [ ] The owner has seen it on a test site (`scripts/preview-site.sh`) and
  approved the banner, icon and screenshots.
- [ ] WordPress.org: if the Plugin Name gives another slug, ask for this
  plugin's slug in the submission notes.
- [x] After the release: `scripts/update-test.sh` (1.0.0 to 1.0.1 passed).

## WordPress.org

Not submitted. Follow `RELEASING.md` → WordPress.org when the owner says,
and record the submission here.
