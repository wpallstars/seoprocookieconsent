# SEO Pro Cookie Consent launch state

Version: 1.0.0

First GitHub release: 0.1.0 (2026-10-17), a pre-1.0 beta; Before 1.0 below
is still open. The repository is private, so sites get updates only with a
read-only `WPALLSTARS_GITHUB_TOKEN` or by installing the release zip. Making
it public needs the owner's say.

## While private

Follow `DEVELOPMENT.md` → While private. No branch protection or repository
rules, CodeQL, secret scanning or Scorecard are on: they need a public
repository or a paid GitHub plan. Connect Codacy, CodeFactor and SonarCloud
at public launch. `SYNC_PAT` is needed only once `main` is protected
(`DEVELOPMENT.md` → Services setup, step 4).

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
- [ ] `scripts/sync-core.sh --check` lists no differences from the starter.
  At 1.0.0, three differ: `RELEASING.md` and `scripts/lib/deploy-remote.sh`
  (GH#67) and `STYLING.md` (GH#68) changed here first; take them to the
  starter.
- [x] Checks pass: `scripts/lint.sh`, `scripts/preflight-release.sh` (no
  errors; each warning understood), `scripts/smoke-test.sh`,
  `scripts/plugin-check.sh` (no errors on either zip).
- [ ] The owner has seen it on a test site (`scripts/preview-site.sh`) and
  approved the banner, icon and screenshots.
- [ ] WordPress.org: if the Plugin Name gives another slug, ask for this
  plugin's slug in the submission notes.
- [ ] After the release: `scripts/update-test.sh`.

## At public launch

First follow `DEVELOPMENT.md` → Secrets in history, then
`DEVELOPMENT.md` → At public launch. Update this file with what is on.

## WordPress.org

Not submitted. Follow `RELEASING.md` → WordPress.org when the owner says,
and record the submission here.
