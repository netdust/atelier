# atelier unit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (Native) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** atelier's own gate tests against the core and baseline versions sites run. atelier owns the card's and the zip's unit tests. A first Infection baseline decides which of those tests carry weight.

**Architecture:**
- **One branch in the atelier repo, three steps.** The lock is committed and the gate prints what it ran against. Laika's two pure-atelier unit test files move in. Infection runs report-only on the classes they test, outside the gate.
- **What the baseline is for.** It judges the 18 DELETE candidates that laika's prune audit found in those two files: a test goes only when the list of surviving mutants does not grow without it.
- **First working version:** Task 1. `composer gate` prints `netdust/ntdst-core` 5.4.x and `netdust/ntdst-baseline` 2.17.x and passes, or shows what the real versions break.

**Tech Stack:** PHP 8.3 on the host, Composer 2.8 (host, SSH to GitHub), PHPUnit 10.5, Brain Monkey 2.6, Infection (new, dev only) with PCOV (host).

**Spec:** `~/Sites/netdust-wp-manager/specs/framework-tests/spec.md` (branch `spec/framework-tests`): F1, F3 (its unit half), F6. This plan does no other requirement.

## Global Constraints

- **Work only in this worktree:** `~/Sites/worktrees/atelier-unit`, branch `feature/atelier-unit`, cut from `origin/main` 9d6cefe (v0.11.0). `~/Sites/atelier` is laika's installed Composer clone (a symlink to `laika/web/app/mu-plugins/atelier`, detached at v0.11.0), so never edit, check out or run Composer there.
- **The test runner stays PHPUnit `^10.5`.** The packages keep 10.5 for the unit tier; the never-deployed test site (spec F2) runs the integration tier later, on wp-phpunit's 9.6. Ruling (framework-tests sparring): this is the cheapest way to keep both.
- **Infection never runs inside `composer gate`** (spec F6). It has its own `composer mutation` script and is report-only.
- **Judge by surviving mutants, not the score.** A DELETE is proven when the list of surviving mutants is the same with and without the test. The unit tier has no database, so Infection may use threads here.
- **PCOV is the coverage driver,** installed on the host by Stefan (`sudo apt install php8.3-pcov`). Task 3 starts only when `php -m` lists `pcov`. If it is missing, report it and stop at Task 2.
- **Leave laika alone.** Laika's copies of the moved tests (`tests/Unit/Card/VCardBuilderTest.php`, `tests/Unit/PressKit/KitArchiveBuilderTest.php`) are deleted by laika's own cleanup plan, never from this branch.
- **No source-reading tests.** A test proves behaviour through the class's public seam (`netdust-gates` `source-scan-ratchets`).
- **Commits:** atomic per task, on `feature/atelier-unit` only. No merge, no tag, no push of `main`. The tag is Stefan's release step after the review.

## Review Focus

1. **A green gate against stale versions** (spec F1). That is the defect this plan exists for: the gate ran against core 5.2.0 and baseline 2.6.0 behind a `^5.4` / `^2.16.1` constraint, with the lock gitignored. → Task 1: the committed lock, plus the gate printing `composer show` for both packages.
2. **A moved test that silently changes meaning.** laika's tests extend `NtdstTests\Unit\TestCase` (Brain Monkey set-up and tear-down); atelier's extend PHPUnit's `TestCase`. → Task 2: the same test count and the same assertion count before and after the move, quoted.
3. **A DELETE that looks safe because the score held.** Equivalent mutants or a timeout can keep the score level while a different mutant survives. → Task 4 compares the surviving-mutant lists by mutator and line.
4. **Infection creeping into the gate, or the lock ignored again.** → Task 1 and Task 3 each check `composer.json`'s `gate` script and `.gitignore`.

## Simplest design

- **The simplest version:** commit the lock and stop. That would make the gate honest about versions, but atelier would still own almost no tests and nobody could say which of the moved tests matter.
- **What the plan adds:** the move (spec F3, decided at laika `specs/card/review.md:27,38` and never done) and a baseline run on exactly the classes the move covers.
- **Where it stops:** no test site, no integration tier, and nothing in baseline or core. Those are later plans under the same spec.

## Test map

| Unit | Seam | Tests: tier · file · command | E2E flows |
|---|---|---|---|
| 1. Dependency hygiene | `composer gate` | unit · the existing `tests/Unit/**` (31 tests) against the locked versions · `composer gate` | — |
| 2. The card's and the zip's unit tests | `Atelier\Card\VCardBuilder::build()`; `Atelier\PressKit\KitArchiveBuilder::build()`, `ArchiveNameSanitizer` | unit · `tests/Unit/Card/VCardBuilderTest.php`, `tests/Unit/PressKit/KitArchiveBuilderTest.php` · `composer test` | — |
| 3. The mutation baseline | `composer mutation` (Infection on `services/card/VCardBuilder.php`, `services/presskit/KitArchiveBuilder.php`, `services/presskit/ArchiveNameSanitizer.php`) | report-only · `specs/mutation/<date>.md` · `composer mutation` | — |
| 4. The proven prune | the 18 DELETE candidates (laika `specs/test-prune/2026-10-18.md`, slice A: 14 in VCardBuilderTest, 4 in KitArchiveBuilderTest) | unit · the two files · `composer mutation`, once per candidate group, with and without | — |

## Tasks

### Task 1: Dependency hygiene (first working version)

**Files:**
- Modify: `.gitignore` (drop `/composer.lock`)
- Modify: `composer.json` (`scripts.gate` gains a first step that prints the versions, e.g. `@composer show netdust/ntdst-core netdust/ntdst-baseline`)
- Create: `composer.lock` (committed)

- [ ] **Step 1:** `composer update` on the host in this worktree. Check that it resolves `netdust/ntdst-core` to the newest `^5.4` tag and `netdust/ntdst-baseline` to the newest `^2.16.1` tag (2.17.0 expected), and quote the resolved versions. Ground-truth any SSH or VCS failure before trusting this plan's claim that host Composer reaches GitHub.
- [ ] **Step 2:** `composer gate`, and quote its version lines and the PHPUnit summary. A test that fails against the real versions is this task's real finding: fix the test or the code through its seam, RED→GREEN, and name each one in the report. Never pin back to the old versions.
- [ ] **Step 3:** commit (`build: the lock is committed and the gate names the core and baseline it ran against — framework-tests F1`).

### Task 2: The card's and the zip's unit tests move in

**Files:**
- Create: `tests/Unit/Card/VCardBuilderTest.php` (from laika `tests/Unit/Card/VCardBuilderTest.php`)
- Create: `tests/Unit/PressKit/KitArchiveBuilderTest.php` (from laika `tests/Unit/PressKit/KitArchiveBuilderTest.php`)
- Modify, only if a moved test needs it: `tests/bootstrap.php`

- [ ] **Step 1:** in laika (read-only), run the two files and record their counts: `cd ~/Sites/laika && ddev exec vendor/bin/phpunit -c phpunit.unit.xml tests/Unit/Card tests/Unit/PressKit`.
  - Another Claude session books long runs on this box. This is a few seconds of unit tests, but send your reviewer one line first.
  - If DDEV is busy, take the counts from the files instead: the number of test methods, with data provider rows expanded.
- [ ] **Step 2:** move both files into atelier under the namespaces `AtelierTests\Unit\Card` and `AtelierTests\Unit\PressKit`, extending PHPUnit's `TestCase`.
  - Port what laika's `NtdstTests\Unit\TestCase` did for them: Brain Monkey set-up and tear-down, only if they call a WordPress function. Check with grep before adding anything.
  - Keep every assertion and every test as it is. The prune happens in Task 4, proven.
- [ ] **Step 3:** `composer gate`. The two files must show the same test and assertion counts as in Step 1; quote both.
- [ ] **Step 4:** commit (`test: the card's and the zip's unit tests live in atelier — framework-tests F3; laika's copies go in laika's cleanup`).

### Task 3: The mutation baseline

**Files:**
- Modify: `composer.json` (require-dev `infection/infection`; `scripts.mutation`; an `allow-plugins` entry if Infection's Composer plugin asks for one)
- Create: `infection.json5` (source: the three classes in Test map row 3; `testFramework` phpunit; `logs.text` and `logs.summary` under `build/infection/`, gitignored)
- Modify: `.gitignore` (`/build/`)
- Create: `specs/mutation/<date>.md`

- [ ] **Step 1:** check that `php -m` lists `pcov`. If not, stop and report: "Task 3 waits for `sudo apt install php8.3-pcov` on the host".
- [ ] **Step 2:** install Infection, configure it and run `composer mutation`.
- [ ] **Step 3:** write `specs/mutation/<date>.md`. It records:
  - the command;
  - the Infection version and the coverage driver;
  - per class: the score, and the killed / escaped / timed-out / uncovered counts;
  - every surviving mutant, as mutator, file:line and the original → mutated code.

  It is a report: change no test because of it.
- [ ] **Step 4:** check that `scripts.gate` does not call Infection. Commit (`test: report-only mutation baseline on the card and the zip — framework-tests F6`).

### Task 4: The proven prune

**Files:**
- Modify: `tests/Unit/Card/VCardBuilderTest.php`, `tests/Unit/PressKit/KitArchiveBuilderTest.php`
- Modify: `specs/mutation/<date>.md` (a "Prune" section)

**The candidates** are laika's prune report, slice A ("DELETE" rows for VCT and KABT), read from `~/Sites/laika/specs/test-prune/2026-10-18.md`. That report says the four KABT DELETEs leaned on laika's integration tests, which atelier's run cannot see. Judge them here on atelier's own tests alone.

- [ ] **Step 1:** for each candidate, or group sharing one cited cover, run Infection with the candidate removed and compare the surviving-mutant list with Task 3's.
  - **Same list:** the DELETE is proven; remove the test.
  - **A new survivor:** keep the test, or move its killing assertion into the cited cover first. Name the mutant.
- [ ] **Step 2:** quote, per candidate, the verdict and the mutant diff (or "identical") in the report's "Prune" section. Then `composer gate` and `composer mutation` once more: the final survivor list equals Task 3's.
- [ ] **Step 3:** commit (`test: prune the card's and the zip's unit tests, each removal proven by Infection's survivor list — framework-tests F6`).

## Close

1. `composer gate` is green and prints the locked versions.
2. The reviewer session runs the feature review on `feature/atelier-unit` (`netdust-gates:feature-review`, a plan named). The coder makes one fix pass, RED→GREEN, keeps the review as `specs/atelier-unit/review.md` and pushes.
3. Stefan merges and tags atelier (0.11.1 or 0.12.0, his call). Laika's cleanup then deletes laika's two copies.
