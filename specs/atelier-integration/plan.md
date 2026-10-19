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
  - **Neighbours float** to the newest tag on every run (F1's "the tags its constraint allows"). `TESTBED_OFFLINE=1` updates only the package under test, against the committed lock (§9.6).
  - **Tag enforcement is a convention for now.** The runner's header records what was tested; the stamp and pre-push hook of §9.5 is a later unit.
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
| 2. atelier `test:int` and the runner | `composer test:int` in the atelier worktree → `bin/test-package atelier <path>` | integration · the header lines and a clean test-site tree · `composer test:int` | — |
| 3. CalendarReadTest moved | `Atelier\Performance` calendar reads through `PerformanceService`/`PerformanceRepository` | integration · `tests/Integration/Performance/CalendarReadTest.php` · `composer test:int` (green, red with the guard broken, green) | — |

## Tasks

### Task 1: The test site

**Files (new repo `~/Sites/ntdst-testbed`):**
- the netdust/bedrock clone, with its origin dropped;
- wp-starter's integration subset per §2, at a named wp-starter commit (quote its sha), with its tokens rendered;
- the seam line in the integration bootstrap, and `tests/bootstrap-site.php`;
- `composer.json` per §2 and `composer.lock`;
- `bin/test-package`;
- `.gitignore` (`composer.under-test.*`, `.testbed.lock`);
- `.ddev/` (`ddev config`, project `ntdst-testbed`, with `config.gates.yaml`'s `wptests` database);
- `README.md`: purpose, never deployed, how to run, the F8 upstream note.

- [ ] **Step 1:** build the site per §2. Check the five items in §11 that a read-only design could not, as you meet them, and quote each result.
- [ ] **Step 2:** tell the reviewer, then `composer install` (host), `ddev start`, `ddev exec vendor/bin/phpunit -c phpunit.unit.xml`. The guard contract test is green. Quote it. `git status` is clean.
- [ ] **Step 3:** commit on the test site's `main` (`build: a never-deployed test site for the packages' integration tests — framework-tests F2`).

### Task 2: atelier `test:int`

**Files (atelier worktree):**
- Create: `.gitattributes` (`/vendor export-ignore`, `/build export-ignore`)
- Create: `tests/Integration/site.php` (§5: ProductionService, ModelsService and PerformanceService with their default settings)
- Modify: `composer.json` (`scripts.test:int`, the one line calling `~/Sites/ntdst-testbed/bin/test-package atelier <this path>`; not in `gate`)

- [ ] **Step 1:** with no integration test yet, `composer test:int` prints the header (realpath, HEAD, clean, `resolved as 0.11.0`, the neighbour tags) and "No tests executed". `ls ~/Sites/ntdst-testbed/web/app/mu-plugins/atelier/vendor` fails, and the test site's `git status` is clean. Quote all three.
- [ ] **Step 2:** commit (`build: atelier runs its integration tests on the test site — framework-tests F2`).

### Task 3: CalendarReadTest moved (first working version)

**Files:**
- Create: `tests/Integration/Performance/CalendarReadTest.php`, from laika (`git -C ~/Sites/laika show origin/main:tests/Integration/Atelier/CalendarReadTest.php`; fall back to 3ca8368 if main lacks it, and say which)

- [ ] **Step 1:** copy the file and change only the namespace line (`AtelierTests\Integration\Performance`). If laika fixtures or support it needs do not exist here, write the neutral equivalent and name each one; keep the test's assertions as they are. `diff` against laika's file shows only those lines; quote it.
- [ ] **Step 2: GREEN.** `composer test:int`: header, `OK (12 tests, …)` (or the real count; quote it).
- [ ] **Step 3: RED.** In the working copy only, comment out the guard (`services/performance/PerformanceView.php:35`, `return null;`; check the exact line first). `composer test:int` shows the header marked dirty and the named failure. Quote it. Restore the file.
- [ ] **Step 4: GREEN again,** and `git -C ~/Sites/ntdst-testbed status` is clean. Quote both. Name each fidelity difference from laika's tier that the test meets (§9.3).
- [ ] **Step 5:** commit (`test: atelier's first integration test runs on the test site, proven red by a broken guard — framework-tests F3`).

## Close

1. atelier's `composer gate` is green (the unit tier, unchanged), and `composer test:int` is green.
2. The coder pushes `feature/atelier-integration` and tells the reviewer. The reviewer runs the feature review. The coder makes one fix pass, keeps the review as `specs/atelier-integration/review.md`, and pushes.
3. Stefan merges. The test site stays local until he decides on a remote.
