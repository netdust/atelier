# atelier unit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (Native) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** atelier records the core and baseline versions it installs, owns the card's and the zip's unit tests, and gets a first Infection baseline. That baseline decides which of those tests carry weight.

**Architecture:**
- **One branch in the atelier repo, in this order.**
  - The lock is committed, and the gate prints what is installed.
  - Laika's two pure-atelier unit test files move in, line for line.
  - Infection runs report-only on the classes they test, outside the gate.
  - The audit's 18 DELETE candidates in those files are judged by survivor lists.
- **What Task 1 can and cannot tell.** Atelier's unit tier loads no core or baseline code: neither package declares an autoload (`v5.4.1:composer.json`, `v2.17.0:composer.json`), and the tests stub `ntdst_data()` with Brain Monkey (`tests/Unit/Planning/RelationTargetsTest.php:33`, `tests/Unit/Production/ProductionVocabulariesTest.php:27-37`). So Task 1 makes the versions honest; it cannot test against them. Atelier first runs against real core on the test site (spec F2, a later plan).
- **First working version:** Task 1. `composer gate` prints one line per installed `netdust/*` package (core 5.4.1, baseline 2.17.0) and passes.

**Tech Stack:** PHP 8.3 on the host (PCOV installed), Composer 2.8.9 (host, SSH to GitHub), PHPUnit 10.5, Brain Monkey 2.6, Infection 0.35.x (new, dev only).

**Spec:** `~/Sites/netdust-wp-manager-framework-tests/specs/framework-tests/spec.md` (branch `spec/framework-tests`). This plan covers three parts of it:
- F1, atelier's bullet only. Baseline's and core's bullets belong to their own plans.
- F3, its unit half.
- F6, atelier's module baseline.

## Global Constraints

- **Work only in this worktree:** `~/Sites/worktrees/atelier-unit`, branch `feature/atelier-unit`, cut from `origin/main` 9d6cefe (v0.11.0). `~/Sites/atelier` is laika's installed Composer clone (a symlink to `laika/web/app/mu-plugins/atelier`, detached at v0.11.0), so never edit, check out or run Composer there.
- **The test runner stays PHPUnit `^10.5`.** The packages keep 10.5 for the unit tier; the test site (F2) runs the integration tier later, on wp-phpunit's 9.6. Ruling (framework-tests sparring): this is the cheapest way to keep both.
- **Infection never runs inside `composer gate`** (spec F6). It has its own scripts and is report-only.
- **A survivor is every mutant that was not killed:** escaped, timed out, not covered or skipped. Each survivor is keyed by mutator + file + line + diff. A DELETE is proven when the survivor list with the test removed equals the list with it present, compared mechanically, never by eye and never by the score.
- **The unit tier has no database,** so Infection may use threads here.
- **PCOV is the coverage driver.** It is installed on the host now (`php -m` lists `pcov`).
- **The committed lock pins Infection 0.35.x, which requires PHP `^8.3`,** while atelier declares `>=8.2`. An 8.2 box cannot install the dev tools from this lock. Every box in use runs 8.3 (the host, laika's DDEV). Ruling: accepted; revisit if an 8.2 consumer appears.
- **Laika's copies of the moved tests** (`tests/Unit/Card/VCardBuilderTest.php`, `tests/Unit/PressKit/KitArchiveBuilderTest.php`) are deleted by laika's own cleanup plan, never from this branch.
- **No source-reading tests.** A test proves behaviour through the class's public seam.
- **Commits:** atomic per task, on `feature/atelier-unit` only. No merge, no tag, no push of `main`.

## Review Focus

1. **Versions that look tested but are not** (spec F1). A gate that prints 5.4.1 implies nothing about running against it. → Task 1 says so in its commit and in the gate's output. No test is added to make up for it: a reflection check on core's API would be a shape test.
2. **A moved test that silently changes meaning.** The real change is the runner: laika runs PHPUnit 9.6, atelier 10.5. The base class adds nothing, because these files call no WordPress function and set no Mockery expectation. → Task 2: the diff against laika's blob shows only the namespace and `use` lines changed, and the files run green on 10.5.
3. **A DELETE that looks safe because no mutant reaches the behaviour it guards.** For example KABT's "each archive names from scratch": the audit's cited line is a property no mutator makes static. → Task 4 quotes Task 3's mutants on each candidate's cited production lines. A candidate with none is "not judged by Infection" and stays.
4. **Two tests that cover each other both going.** → Task 4 keeps the earlier proven removals in every later run.
5. **Infection creeping into the gate, or the lock being ignored again.** These are absences, and atelier has no `ARCHITECTURE-INVARIANTS.md` until F9. → Task 1 and Task 3 run `git check-ignore -q composer.lock` (expect exit 1) and grep `scripts.gate`.

## Simplest design

- **The simplest version:** commit the lock and stop. The versions are recorded, but atelier still owns almost no tests, and nobody can say which of the moved ones matter.
- **What the plan adds:** the move (spec F3; decided for the card at laika `specs/card/review.md:27,38`, and for the zip by laika's prune ruling (c)) and a baseline on exactly the classes the move covers.
- **Where it stops:** no test site, no integration tier, and nothing in baseline or core. Those are later plans under the same spec.

## Test map

| Unit | Seam | Tests: tier · file · command | E2E flows |
|---|---|---|---|
| 1. Versions recorded | `composer gate` | unit · the existing `tests/Unit/**` (31 tests; they load no netdust package) · `composer gate` | — |
| 2. The card's and the zip's unit tests | `Atelier\Card\VCardBuilder::build()`; `Atelier\PressKit\KitArchiveBuilder::build()`, `ArchiveNameSanitizer` | unit · `tests/Unit/Card/VCardBuilderTest.php`, `tests/Unit/PressKit/KitArchiveBuilderTest.php` · `vendor/bin/phpunit tests/Unit/Card tests/Unit/PressKit`, then `composer gate` | — |
| 3. The mutation baseline | `composer mutation` (Infection filtered to the three classes) | report-only · `specs/mutation/<date>.md`, `build/infection/mutation.json` · `composer mutation` | — |
| 4. The proven prune | the 18 DELETE candidates (laika `specs/test-prune/2026-10-18.md:373-399`, slice A: 14 in VCardBuilderTest, 4 in KitArchiveBuilderTest) | unit · the two files · `composer mutation`, once per candidate, each run keeping earlier proven removals | — |

## Tasks

### Task 1: Versions recorded (first working version)

**Files:**
- Modify: `.gitignore` (drop `/composer.lock`)
- Modify: `composer.json` (`scripts.gate` gains a first step, `"@composer show netdust/*"`, printing one line per installed netdust package)
- Create: `composer.lock` (committed)

- [ ] **Step 1:** `composer update` on the host in this worktree. Quote the resolved `netdust/ntdst-core` (newest `^5.4`, v5.4.1 expected) and `netdust/ntdst-baseline` (newest `^2.16.1`, v2.17.0 expected). Ground-truth any SSH or VCS failure before trusting this plan's claim that host Composer reaches GitHub.
- [ ] **Step 2:** `composer gate`. Quote its netdust lines and the PHPUnit summary (31 tests expected). `git check-ignore -q composer.lock` exits 1.
- [ ] **Step 3:** commit (`build: the lock is committed and the gate names the netdust versions installed; the unit tier loads none of them — framework-tests F1`).

### Task 2: The card's and the zip's unit tests move in

**Files:**
- Create: `tests/Unit/Card/VCardBuilderTest.php`, from laika `feature/presskit` at 3ca8368 (blob 8ebc744)
- Create: `tests/Unit/PressKit/KitArchiveBuilderTest.php`, from laika 3ca8368 (blob 343a099)

- [ ] **Step 1:** copy both files from `git -C ~/Sites/laika show 3ca8368:<path>`. They exist only on laika's `feature/presskit` and `staging`, not on `main`. Change only the namespace (`AtelierTests\Unit\Card`, `AtelierTests\Unit\PressKit`), the `use` of the base class, and `extends PHPUnit\Framework\TestCase`. Keep every other line where it is, so the audit's `VCT:` and `KABT:` line citations still resolve in Task 4.
- [ ] **Step 2:** prove the move:
  - `diff <(git -C ~/Sites/laika show 3ca8368:tests/Unit/Card/VCardBuilderTest.php) tests/Unit/Card/VCardBuilderTest.php` shows only those lines (likewise for KABT);
  - `vendor/bin/phpunit tests/Unit/Card tests/Unit/PressKit` is green on 10.5 (86 tests expected: 44 in VCT, 42 in KABT, providers expanded).

  Quote both.
- [ ] **Step 3:** `composer gate` green; commit (`test: the card's and the zip's unit tests live in atelier, moved line for line — framework-tests F3; laika's copies go in laika's cleanup`).

### Task 3: The mutation baseline

**Files:**
- Modify: `composer.json`:
  - require-dev `infection/infection`;
  - `allow-plugins` if Infection's Composer plugin asks;
  - `scripts.mutation`: `infection --filter=services/card/VCardBuilder.php,services/presskit/KitArchiveBuilder.php,services/presskit/ArchiveNameSanitizer.php --log-verbosity=all`;
  - `scripts.mutation:diff`: `infection --git-diff-lines --git-diff-base=origin/main --log-verbosity=all`, the review-time run of F6. The plain filter and the git-diff filter exclude each other, hence two scripts.
- Create: `infection.json5`:
  - `source.directories: ["services", "shared"]` (Infection's `source` takes directories, not files);
  - `testFramework: "phpunit"`;
  - `logs.json: "build/infection/mutation.json"` and `logs.text: "build/infection/mutation.txt"`.
- Modify: `.gitignore` (`/build/`)
- Create: `bin/mutants.php` (or a `composer` script calling it). It reads `build/infection/mutation.json` and prints one sorted line per survivor (status, mutator, file, line, diff hash), so two runs compare with `diff`.
- Create: `specs/mutation/<date>.md`

- [ ] **Step 1:** install Infection, configure it, and run `composer mutation`.
- [ ] **Step 2:** write `specs/mutation/<date>.md`. It records:
  - the command;
  - the Infection version and the coverage driver;
  - per class, from the JSON log: the score and the killed / escaped / timed-out / not-covered / skipped counts;
  - every survivor as `bin/mutants.php` prints it, with the original → mutated code.

  It is a report: change no test because of it.
- [ ] **Step 3:** `scripts.gate` does not call Infection (quote it); `git check-ignore -q composer.lock` exits 1. Commit (`test: report-only mutation baseline on the card and the zip, and a changed-code mutation run for review — framework-tests F6`).

### Task 4: The proven prune

**Files:**
- Modify: `tests/Unit/Card/VCardBuilderTest.php`, `tests/Unit/PressKit/KitArchiveBuilderTest.php`
- Modify: `specs/mutation/<date>.md` (a "Prune" section)

**Inputs:**
- **The candidates and their cited production lines:** laika's prune report, `~/Sites/laika/specs/test-prune/2026-10-18.md:373-399`, the last column of each DELETE row for VCT and KABT.
- **The KABT four:** the report says they leaned on laika's integration tests, which atelier's run cannot see. Judge them on atelier's own tests alone.

- [ ] **Step 1:** for each candidate, in the report's order:
  - Quote Task 3's mutants on its cited production lines. If there are none, the verdict is **not judged by Infection**: the test stays, and the report names it for Stefan.
  - Otherwise, remove the candidate, keeping every earlier proven removal, and run `composer mutation`. Compare `bin/mutants.php`'s list with the previous run's using `diff`.
    - **Identical:** the DELETE is proven.
    - **A new survivor:** restore the test, or first move its killing assertion into the cited cover, and name the mutant.
  - When a removal leaves a data provider unused (for example `emptyFieldProvider`, `lineTerminatorProvider`, audit :585), delete the provider with its last test.
- [ ] **Step 2:** ruling (the reviewer's, for the prune audit's move 8): add the `editorNames` rows "question mark" and "combining mark" to KABT (audit :506-508). laika's `presskit.spec.ts:1114` DELETE needs them, and they now live here. Prove them green.
- [ ] **Step 3:** quote, per candidate, the verdict and the `diff` output (or "identical") in the report's "Prune" section. Then run `composer gate` and `composer mutation` once more: the final survivor list equals Task 3's, plus or minus only what the report names.
- [ ] **Step 4:** commit (`test: prune the card's and the zip's unit tests, each removal proven by Infection's survivor list — framework-tests F6`).

## Close

1. `composer gate` is green and prints the installed netdust versions.
2. The coder pushes `feature/atelier-unit` and tells the reviewer. The reviewer runs the feature review on it (`netdust-gates:feature-review`, plan named). The coder makes one fix pass, RED→GREEN, keeps the review as `specs/atelier-unit/review.md`, and pushes again.
3. Stefan merges and tags atelier (0.11.1 or 0.12.0, his call). Laika's cleanup then deletes laika's two copies.
