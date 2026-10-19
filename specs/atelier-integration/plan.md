# atelier integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (Native) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A never-deployed test site runs a package's integration tests against real WordPress. The package comes from its working copy, its neighbours from tags. Atelier gets its first integration test there, moved out of laika and proven red by a hand-broken guard.

**Architecture:**
- **The design.** It is the sparred design in `~/Sites/netdust-wp-manager-framework-tests/specs/framework-tests/testbed-design.md` (branch `spec/framework-tests`, commit 961689a). Its sections are cited below as §n. Read §0, §2–§6, §8, §9 and §11 before Task 1.
- **The test site:** a new local git repo, `~/Sites/ntdst-testbed`, and its own DDEV project `ntdst-testbed`.
  - **How it is built:** by hand from netdust/bedrock, plus only the integration subset of wp-starter's `gate/` (§2). There is no `new-site.sh`, no `site.yml`, no Makefile, no theme of its own, and no YOOtheme, so nothing in it can deploy.
  - **What it carries:**
    - WordPress 6.8.2;
    - ntdst-core, ntdst-baseline and atelier from tags (VCS repos, `minimum-stability: stable`), with the lock committed;
    - twentytwentyfive;
    - PHPUnit 9.6, wp-phpunit ~6.8.0 and the polyfills.
  - **The runner.** `bin/test-package <name> <path>` (§3):
    - It writes a gitignored `composer.under-test.json`, which prepends one `path` repo: the working copy, `symlink: false`, with `versions` pinned to the copy's last tag.
    - It runs `composer update 'netdust/*'` and `composer reinstall <pkg>` on the host, then PHPUnit through `ddev exec`, under an `flock`.
    - It prints a header: the package's realpath, HEAD and dirty files, and the neighbour versions.
  - **`tests/bootstrap-site.php`** boots `NTDST_Bootstrap` from the package's `tests/Integration/site.php`, with laika-core's hook timing (§4–§5).
- **atelier owns `tests/Integration/`,** written for PHPUnit 9.6, and a one-line `composer test:int` that calls the runner with its own name and path.
- **First working version:** Task 3. In the atelier worktree, `composer test:int` prints the header and `OK (12 tests, …)`. It goes red with the guard at `services/performance/PerformanceView.php:35` commented out, and green again once it is restored (§8).

**Tech Stack:** Bedrock, DDEV, PHPUnit 9.6, wp-phpunit, Composer path and VCS repositories, bash.

**Spec:** `~/Sites/netdust-wp-manager-framework-tests/specs/framework-tests/spec.md`: F2, F3's first integration test, and spec Order 3.

## Global Constraints

**The WordPress pack, copied verbatim** (the test site carries `roots/wordpress`):

- Evidence: no task claims a line below without its evidence in the task report — the
  command as run with its output quoted, or the review that owns the line. No quote, not met.
- Base: ntdst-core is the framework base and its layering applies to every new class —
  `netdust-wp:ntdst-framework`; where files live — `netdust-wp:ntdst-patterns`. Owned by the
  whole-branch review, joined on WordPress by `netdust-wp:ntdst-drift-reviewer` on the changed
  paths (Part 1 runs core's invariants live); its `## Findings:` header is quoted.
- Golden path: the plan names the slice this feature is built to (`netdust-wp:ntdst-patterns`
  → `golden-paths/`); every departure from it is named and justified, or `none`. Judged, not
  grepped: `/plan-review` checks the slice is named, the whole-branch review that code keeps it.
- Security: every data flow (AJAX action, REST route, form post, shortcode attribute,
  settings save, custom query) states its four pillars — validate, sanitize, escape,
  authorize — as `netdust-wp:wp-security` defines them; a pillar that does not apply is
  written `n/a — <why>`, never omitted. Evidence per flow: each pillar cited `file:line`; the
  output of `grep -nE "current_user_can|wp_verify_nonce|check_ajax_referer|check_admin_referer|permission_callback" <changed files>`;
  the denial test's name, RED then GREEN. `security-sentinel` owns the review when the plan
  carries a threat model.
- Drift: nothing `netdust-wp:ntdst-drift-reviewer` reports — a Critical or Drift finding is
  fixed or is a deviation the plan names; the review quotes the agent's findings, never "looks clean".
- Tests: the close is `make gate`. Unit = Brain Monkey, integration = wp-phpunit through
  DDEV, e2e = Playwright; commands and setup are `netdust-wp:wp-testing`'s. Evidence: the exit
  code and the summary lines of a run after the task's last edit. A tier that did not run is
  reported as not run, never as a pass. A site's tests cover its code and its configuration of
  ntdst-core and ntdst-baseline; the packages' own behaviour is tested in their repos.
- Local and ship: DDEV always; the Makefile is the only route to a server
  (`netdust-devops:devops`); anything that must ship names its `deploy.payload` path, and
  `make deploy-test env=staging` output listing it is quoted. An rsync, scp or SFTP to a server
  outside the Makefile is a defect the review reports.

| Red flag — the excuse | The answer |
|---|---|
| "Admin-only screen, so no nonce" | Capability says who; the nonce says they meant it. Both. |
| "The value comes from the database" | Stored input is input. Escape it on output. |
| "The reviewer will catch it" | The reviewer checks the evidence you quoted. No quote, not met. |
| "`make gate` was green earlier" | Earlier is not after your last edit. Run it, quote it. |
| "The integration tier needs DDEV; skip it" | `ddev start`. A tier not run is not a pass. |
| "Plain WordPress is simpler here" | On ntdst-core that is drift. Use the framework's door or name the deviation. |
| "Too small for the golden path" | Then the departure is `none` or one named line. Silence is the defect. |
| "The denial is obvious, no test needed" | Obvious is untested. Write it RED first. |
| "Just rsync this one file" | A hand-deploy is drift nobody sees. The Makefile, or nothing. |

**For this plan:**

- **Golden path:** none. This is a test harness, and no slice in `ntdst-patterns/golden-paths/` fits.
- **Data flows:** none, so the four pillars are n/a: no route, form or request is added.
- **Threat model:** none needed. The one safety boundary, the `wptests` allow-list, is copied as is and is Test map row 1.

- **Where the work happens:**
  - atelier: only in `~/Sites/worktrees/atelier-integration`, branch `feature/atelier-integration`, cut from `origin/main` e777ced;
  - the test site: only in `~/Sites/ntdst-testbed`.
  - Never test from, edit or run Composer in `~/Sites/atelier`. It is laika's live install (§9.1).
  - Laika is not touched; its copy of CalendarReadTest goes in laika's cleanup (F7).
- **PHPUnit 9.6 in `tests/Integration/`:** docblock annotations (`@dataProvider`, `@group`) and `public static` providers. No `#[...]` attributes: 9.6 ignores them silently, or errors (§4).
- **Separate from the gate.** `test:int` is not in atelier's `gate` (§9, risk 5), and Infection is not part of this plan.
- **The shared box:**
  - The test site adds three containers (web, db, xhgui) and no fixed host ports (§6).
  - `ddev start`/`stop`, `composer install` and a `test:int` run are light, but tell the reviewer before the first `ddev start`.
  - Each `test:int` run is under a minute.
- **Rulings for this plan** (the reviewer's, by delegation, sparred in the design §10):
  - **No remote.** The test site is a local repo only. A GitHub remote is Stefan's call later.
  - **No `site.yml`, no Makefile.**
  - **Neighbours are pinned** to the test site's committed lock (F1: "its lock is committed"). A run updates only the package under test (`composer update netdust/<pkg>` plus `composer reinstall`), so it needs no network, is reproducible, and the committed tree stays clean. After a neighbour is tagged, the lock is refreshed deliberately with `bin/test-package --refresh`, a committed change on the test site's `main`. This ruling reverses the design §10.3's float, following the plan review (N1); it makes §9.6's offline mode unnecessary.
  - **Tag enforcement is a convention for now.** The runner's header records what was tested. The stamp and pre-push hook of §9.5 have no owner in the spec's Order yet, which the reviewer reports to Stefan.
  - **The plan lives in atelier's `specs/`.**
- **Upstream note (F8):** the seam line in the integration bootstrap and laika's `require vendor/autoload.php` in `wp-tests-config.php` are wp-starter divergences, named in the test site's README.
- **Commits:** atomic per task. atelier on `feature/atelier-integration`; the test site on its own `main`, a new repo with no remote. No merge, no tag, no push of atelier's `main`.

## Review Focus

1. **A green run that tested the tag, not the working copy.** This is the case the whole design exists for. → Task 3's red: v0.11.0 has the guard, so only the uncommitted edit can fail the test.
2. **A test that changed meaning when it moved:** no laika-core, no Polylang, no Brussels timezone. → Task 3: the moved test's diff, plus each fidelity difference named in the report (§9.3).
3. **The test site's committed tree changing during a run.** → Task 3: `git -C ~/Sites/ntdst-testbed status` is clean after the runs.
4. **The `wptests` guard.** A run must never touch a non-test database. → Task 1: wp-starter's guard contract test is green in the test site.
5. **Vendor copied into the test site.** → Task 2: `ls ~/Sites/ntdst-testbed/web/app/mu-plugins/atelier/vendor` fails after a run (§11.4).

## Simplest design

- **The simplest version:** stop after Task 1, with an empty atelier suite. That proves the harness, but not that the working copy is loaded, and only the red of Task 3 can show that.
- **Where this plan stops:** one moved test. The rest of atelier's integration tests, Polylang and the pre-push stamp are later plans.

## Test map

| Unit | Seam | Tests: tier · file · command | E2E flows |
|---|---|---|---|
| 1. The test site | `ddev exec vendor/bin/phpunit -c phpunit.unit.xml` in `~/Sites/ntdst-testbed` | unit · wp-starter's guard contract test · that command | — |
| 2. atelier `test:int` and the runner | `composer test:int` in the atelier worktree → `bin/test-package netdust/atelier <path>` | integration · the header lines and a clean test-site tree · `composer test:int` | — |
| 3. CalendarReadTest moved | `Atelier\Performance` calendar reads through `PerformanceService`/`PerformanceRepository` | integration · `tests/Integration/Performance/CalendarReadTest.php` · `composer test:int` (green, red with the guard broken, green) | — |

## Tasks

### Task 1: The test site

**Interfaces:**
- **Produces: the runner.** `bin/test-package <composer-name> <path>`, for example `bin/test-package netdust/atelier ~/Sites/worktrees/atelier-integration`.
  - It refuses (non-zero, with a message) a name that differs from the path's `composer.json` `name`, or a path that is not a git checkout.
  - It maps the name to `TESTBED_PACKAGE=atelier` and `--testsuite atelier`.
  - It holds `flock .testbed.lock`. A second run prints `testbed busy` and the holder, then exits 75.
  - `bin/test-package --refresh` updates the neighbours' tags in the committed lock.
- **Produces: the suite.** `phpunit.integration.xml` carries an `atelier` testsuite pointing at `web/app/mu-plugins/atelier/tests/Integration`. wp-starter's `integration` suite is dropped: its `tests/Integration` directory does not exist here, and an unfiltered run would throw.
- **Produces: the boot.** `tests/bootstrap-site.php`, inside `plugins_loaded:20`, constructs `NTDST_Bootstrap` from the package's `tests/Integration/site.php`, calls `register()`, then `bootCore()` at `after_setup_theme:5` and `bootFeatures()` at `:15` (laika-core `laika-core.php:34-46`; core v5.4.1 `Bootstrap.php:193`, `:211`).
- **Produces: the root constraints** in `composer.json`: `netdust/atelier: ^0.11`, `netdust/ntdst-baseline: ^2.17`, `netdust/ntdst-core: ^5.4`. The path repo's pinned version must satisfy them (`PathRepository.php:190-191`).

**Files (new repo `~/Sites/ntdst-testbed`):**
- the netdust/bedrock clone at main 61492d8 (quote the sha), with its origin dropped. Remove what the test site never carries: `phpstan-baseline.neon`, `tsconfig.json`, `config/environments/staging.php`, and the composer scripts `gate`, `test:e2e`, `lint:js`, `test:js`, `build:check` and `audit:js`. Set `minimum-stability: stable`;
- wp-starter's integration subset per §2, at bdcd217 (quote it), with its tokens rendered. The loaders come from wp-starter's root `mu-plugins/` (`ntdst-coreloader.php`, `ntdst-baseloader.php`), not from `gate/`;
- the seam line in the integration bootstrap, and `tests/bootstrap-site.php`;
- `composer.json` per §2 and `composer.lock`;
- `bin/test-package`;
- `.gitignore` (`composer.under-test.*`, `.testbed.lock`);
- `.ddev/` (`ddev config`, project `ntdst-testbed`, with `config.gates.yaml`'s `wptests` database);
- `README.md`: purpose and "never deployed", nothing more (spec F9: no hand-written how-to). The runner's usage line on a bad call is the how-to. The F8 note sits as one comment at each divergent line: the seam line in `tests/bootstrap-integration.php`, and the autoload line in `tests/wp-tests-config.php`.

- [ ] **Step 1:** build the site per §2. Check the five items in §11 that a read-only design could not, as you meet them, and quote each result.
- [ ] **Step 2:** tell the reviewer, then `composer update` (host; bedrock's lock has no netdust packages, and this writes the lock Task 1 commits), `ddev start`, `ddev exec vendor/bin/phpunit -c phpunit.unit.xml`. The guard contract test is green. Quote it. `git status` is clean.
- [ ] **Step 3:** commit on the test site's `main` (`build: a never-deployed test site for the packages' integration tests — framework-tests F2`).

### Task 2: atelier `test:int`

**Files (atelier worktree):**
- Create: `.gitattributes` (`/vendor export-ignore`, `/build export-ignore`)
- Note: the `atelier` testsuite lives in the test site (Task 1), not here.
- Create: `tests/Integration/site.php` (§5: ProductionService, ModelsService and PerformanceService with their default settings)
- Modify: `composer.json` (`scripts.test:int`, the one line calling `~/Sites/ntdst-testbed/bin/test-package netdust/atelier <this path>`; not in `gate`)

- [ ] **Step 1:** `composer install` in the atelier worktree; quote `ls vendor/autoload.php`, so the vendor check below can fail. Then quote each of these:
  - **The header and the empty run.** With no integration test yet, `composer test:int` prints the header (realpath, HEAD, clean, `resolved as 0.11.0`, the neighbour tags) and "No tests executed".
  - **Vendor stays out.** `ls ~/Sites/ntdst-testbed/web/app/mu-plugins/atelier/vendor` fails.
  - **The tree stays clean.** The test site's `git status` is clean.
  - **The refusals.** A wrong name (`bin/test-package netdust/wrong <path>`) and a path that is not a git checkout each exit non-zero with their refusal.
  - **The lock.** While a second shell holds `flock .testbed.lock`, a run prints `testbed busy` and the holder, and exits 75.
- [ ] **Step 2:** commit (`build: atelier runs its integration tests on the test site — framework-tests F2`).

### Task 3: CalendarReadTest moved (first working version)

**Files:**
- Create: `tests/Integration/Performance/CalendarReadTest.php`, from laika origin/main (blob 82392d8, the same at 3ca8368). It needs no support files: it uses only `WP_UnitTestCase` and atelier classes.

- [ ] **Step 1:** copy the file and change only the namespace line (`AtelierTests\Integration\Performance`). `diff` against laika's file shows that one line; quote it.
- [ ] **Step 2: GREEN.** `composer test:int`: header, `OK (12 tests, …)` (or the real count; quote it).
- [ ] **Step 3: RED.** In the working copy only, comment out the guard (`services/performance/PerformanceView.php:35`, `return null;`; check the exact line first). `composer test:int` shows the header marked dirty and the named failure. Quote it. Restore the file.
- [ ] **Step 4: GREEN again,** and `git -C ~/Sites/ntdst-testbed status` is clean. Quote both. Name each fidelity difference from laika's tier at origin/main (4eb1c3a) that the test meets: the timezone filter (`laika-core/timezone.php:14`; the test sets its own at `:104`), the Dutch option labels, the overrides, baseline's yootheme opt-in. Polylang is a difference only against feature/presskit. Quote `grep -rn '#\[' tests/Integration` (empty) and `jq '.scripts.gate' composer.json` (no `test:int`).
- [ ] **Step 5:** commit (`test: atelier's first integration test runs on the test site, proven red by a broken guard — framework-tests F3`).

## Close

1. atelier's `composer gate` is green (the unit tier, unchanged), and `composer test:int` is green.
2. The coder pushes `feature/atelier-integration` and tells the reviewer. The reviewer runs the feature review. The coder makes one fix pass, keeps the review as `specs/atelier-integration/review.md`, and pushes.
3. Stefan merges. The test site stays local until he decides on a remote.
