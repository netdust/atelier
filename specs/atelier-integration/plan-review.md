# Plan review — atelier-integration
Reviewed-plan: 6c54a9fd709906952c505165a416a925bb8a1f4d
Verdict: ready with fixes

Every premise the plan and the design stand on holds in the source, so nothing is Blocking. CalendarReadTest is on laika main, with 12 tests. The guard is at `PerformanceView.php:35`, and breaking it fails exactly one test. `composer reinstall` exists, the tags resolve under `minimum-stability: stable`, and DDEV v1.25.3 has the project name free. The Should-fix items are about the plan itself: one proof that cannot fail, a runner contract named two ways, one invented mode, and one contradiction with the spec.

## Blocking

None.

## Should fix

**S1. Review Focus 5's check cannot fail** (plan line 56; Task 2 Step 1, line 96).
- The worktree has no `vendor/` and no `build/`: `ls -la /home/ntdst/Sites/worktrees/atelier-integration` at 2d3e75d.
- The mirror filters only through `.gitattributes` (Composer 2.8.9 `Downloader/PathDownloader.php:159-160`, `ArchivableFilesFinder` with `GitExcludeFilter`). With nothing to exclude, `ls ~/Sites/ntdst-testbed/web/app/mu-plugins/atelier/vendor` fails whether `.gitattributes` works or not.
- The first `vendor/` arrives with the Close's `composer gate`, after the last check has run.
- **Change:** Task 2 Step 1 first runs `composer install` in the worktree and quotes `ls vendor/autoload.php`. Then it runs `test:int` and the absence check.

**S2. The tasks have no `Interfaces` blocks, so the runner's contract is named two ways and three decisions are left open.**
- **The package argument.**
  - Architecture (line 16) says `bin/test-package <name> <path>`. Test map row 2 (line 68) and Task 2 (line 94) say `bin/test-package atelier <path>`. Design §3 step 2 and its `test:int` snippet say `netdust/atelier`, and the runner refuses unless that equals the `composer.json` `name` (`atelier/composer.json:2` is `netdust/atelier`).
  - **Change:** pick one (the design's `netdust/atelier`). State that it maps to `TESTBED_PACKAGE=atelier` and `--testsuite atelier`.
- **Where the atelier `<testsuite>` lands.**
  - Design §4 adds it "when the package's first integration test lands", which is Task 3. But Task 3 lists and commits only atelier files.
  - PHPUnit 9.6.35 skips a `--testsuite` name it does not know without a word (`TextUI/TestSuiteMapper.php:41`). An empty result prints "No tests executed!" and exits 0 (`TextUI/DefaultResultPrinter.php:413-419`, `TextUI/Command.php:151-154`). So Task 2 Step 1 prints the same thing whether the suite is wired or not.
  - **Change:** put the suite in Task 1's files.
  - Also drop wp-starter's `integration` suite. Its `tests/Integration` directory is not in the testbed, so an unfiltered run throws (`TestSuiteMapper.php:71`).
- **The order of the calls in `bootstrap-site.php`.**
  - Design §4 says: build `NTDST_Bootstrap`, then `bootCore` at `after_setup_theme:5` and `bootFeatures` at `:15`. It leaves out `register()`.
  - In core v5.4.1 the constructor only stores the config (`core/Bootstrap.php:193-199`), and services are registered by `register()` (`:211`). laika-core calls it (origin/main `laika-core.php:35-37`).
  - Without it, `bootCore` boots nothing, `create()` meets unregistered models, and Task 3's green fails because of the harness, not the code.
  - **Change:** name the sequence in Task 1's Produces: construct, `register()`, `bootCore()` at `after_setup_theme:5`, `bootFeatures()` at `:15`, all inside `plugins_loaded:20`.
- **The testbed's root `require` constraints.**
  - Neither the plan nor the design states them, yet the path repo's pinned `versions` value must satisfy the root constraint (`Repository/PathRepository.php:190-191`).
  - netdust/bedrock's committed lock has no netdust packages. Task 1 Step 2's `composer install` therefore needs a `composer update` before it, to write the lock Task 1 commits.
  - **Change:** state the constraints (e.g. `netdust/atelier: ^0.11`) and the `update` step.

**S3. Test map row 2 names no test, and the runner's two refusals are never run** (line 68).
- The row's test is "the header lines and a clean test-site tree". Task 3's red proves the header's dirty marker. Nothing exercises the other two behaviours:
  - the `flock` (design §3 step 1, and §6's "A waiting session sees 'testbed busy'"). On a box another session shares, this is the behaviour that matters;
  - the name and git-checkout refusal (§3 step 2).
- **Change:** add two quoted runs to Task 2 Step 1.
  - A wrong name, or a path that is not a git checkout, exits non-zero with the refusal.
  - A run while a second shell holds `flock .testbed.lock` prints busy and the holder, then exits 75 (or waits, whichever the runner does).

**S4. `TESTBED_OFFLINE=1` is invented scope, with no task and no test** (Global Constraints ruling, line 44).
- No spec line asks for offline runs.
- No task builds the mode, and no row runs it. It is a second code path through the runner (update only the package under test, against the committed lock), and it is never exercised.
- **Change:** drop it from this plan, so that an offline run fails honestly at Composer. Or give it a quoted step in Task 2.

**S5. Task 1's README contradicts the spec** (Task 1 Files, line 83: "README.md: purpose, never deployed, how to run, the F8 upstream note").
- Spec F9 says "No hand-written how-to docs: they rot", and its Out of scope lists "Hand-written how-to-test docs (F9)".
- **Change:**
  - Drop "how to run". The runner's usage line on a bad call is the how-to.
  - Keep purpose, never-deployed and the F8 note, or carry the F8 note as one comment at each of the two divergent lines: the seam line in `tests/bootstrap-integration.php` and the autoload line in `tests/wp-tests-config.php`.

**S6. The WordPress pack is missing from Global Constraints, and the plan says nothing about a golden path** (lines 28-48).
- `netdust-gates/skills/policy/wordpress.md` (0.10.0) is "copied verbatim" into a WordPress plan.
- The tasks meet its lines: evidence by quote, DDEV, and no data flow.
- One line is not met: "Golden path: … or `none`". The pack's red flag says "Silence is the defect", and it names `/plan-review` as the check.
- **Change:** paste the pack, and add: "Golden path: none (test harness; no slice in `ntdst-patterns/golden-paths/` fits). Data flows: none, so the four pillars are n/a: no route, form or request is added."

## Note

**N1. The reason for floating the neighbours does not hold against the spec** (line 44).
- The ruling cites F1's "the tags its constraint allows". The same F1 sentence says "its lock is committed": a pinned set of tags, refreshed on purpose.
- F2 asks only that "every neighbour from tags", and a committed testbed lock already gives that.
- The pinned design is simpler:
  - one `composer update netdust/atelier` per run, which is exactly S4's offline mode;
  - no network on every run (design risk 6);
  - runs that can be reproduced.
- **Its cost:** a deliberate lock refresh after each neighbour tag.
- This is Stefan's call. If floating stays, give it a reason other than F1.

**N2. F2's tag rule has no owner** (line 45).
- "A package is tagged only after its integration run passes" is deferred to "a later unit" (design §9.5), and spec Order 1-8 lists no such unit.
- This plan cuts no tag, so nothing is violated. Stefan should still see that the rule has no home.

**N3. Review Focus 2 should name the laika commit it compares against.**
- On laika origin/main (4eb1c3a), the integration tier loads no Polylang: `git grep -i polylang origin/main -- tests/bootstrap-integration.php phpunit.integration.xml web/app/mu-plugins/laika-core/` finds nothing. Polylang arrives with feature/presskit's bootstrap and its listener. The test's blob is the same on both (82392d8).
- Against main, the differences are:
  - the timezone filter (`laika-core/timezone.php:14`);
  - the Dutch option labels (`labels.php:23-55`; no assertion reads them);
  - the overrides (`plugin-config.php:27-50`; the test passes its own CONFIG);
  - baseline's yootheme opt-in (`laika-core.php:26-30`).
- Polylang is a difference only against presskit. Design §9.3's "on for every laika run" holds for presskit only.

**N4. Task 3's source and Step 1 can be fixed in advance** (lines 102, 104).
- Main has the file, and the blob is 82392d8 there and at 3ca8368. The fallback branch and the "write the neutral equivalent" branch can never run: the test uses only `WP_UnitTestCase` and atelier classes (lines 7-13).
- **Change:** say "blob 82392d8; no support files; the diff is the namespace line only".

**N5. The bedrock clone brings in what design §2 says the testbed never carries.** On netdust/bedrock main 61492d8 these are:
- `phpstan-baseline.neon`, `tsconfig.json` and `config/environments/staging.php`;
- the composer scripts `gate`, `test:e2e`, `lint:js`, `test:js`, `build:check` and `audit:js`, which call `bin/*.sh` files the testbed will not have;
- `minimum-stability: dev`, which must become `stable`.

Task 1 should list what it removes, and quote bedrock's sha the way it quotes wp-starter's.

**N6. The loaders are not in `gate/`.** Design §2 lists them under it, but they are at wp-starter's root, `mu-plugins/ntdst-coreloader.php` and `mu-plugins/ntdst-baselineloader.php` (`git -C ~/Sites/wp-starter ls-files`).

**N7. Two "never" lines have no check** (lines 35-36).
- The lines are "no `#[...]` in `tests/Integration`" and "`test:int` not in `gate`".
- atelier has no `ARCHITECTURE-INVARIANTS.md` until F9.
- **Change:** until then, have Task 3 Step 4 quote `grep -rn '#\[' tests/Integration` (empty) and `jq '.scripts.gate' composer.json`, as atelier-unit's Review Focus 5 did.

**N8. No threat model is needed.**
- The plan adds no request, auth, capability check or outbound fetch of a user's URL.
- The one safety boundary, the `wptests` allow-list, is copied as is, and its contract test is row 1.
- The testbed has its own DB container.

## Premises checked
- CalendarReadTest exists on laika main → `git show origin/main:tests/Integration/Atelier/CalendarReadTest.php`, blob 82392d8, the same at 3ca8368 → holds
- It has 12 tests → `grep -c "public function test_"` = 12 → holds
- It needs no laika fixture, Polylang, YOOtheme or laika-core config:
  - it uses only `WP_UnitTestCase` and atelier classes (`:7-13`), and registers PerformanceModel itself (`:33`);
  - `premiere_year` and `kind` are atelier's (`ProductionModel.php:52-57`), and so is `country` (`OrganisationModel.php:39`);
  - laika's `labels.php` relabels options only.
  - → holds
- Timezone: laika forces it (`laika-core/timezone.php:14`), the test sets it itself (`:104`), and the calendar compares dates only (`PerformanceRepository.php:117-130`) → holds; a fidelity difference, not a blocker
- The guard: `PerformanceView.php:33-36` at e777ced, with `return null;` on `:35`; the same block in v0.11.0 → holds
- Breaking `:35` fails exactly one test:
  - only `test_calendar_hides_performances_of_unpublished_productions` makes a draft production (`:129-146`);
  - `find()` defaults to `'publish'` (`ProductionRepository.php:22-25`);
  - the calendar has no other production-status filter (`PerformanceRepository.php:115-170`).
  - → holds
- `composer reinstall` in Composer 2.8.9 → `composer help reinstall` → holds
- A path repo can pin a version → `PathRepository.php:190-191` → holds
- A worktree's dist reference is the composer.json hash, so `reinstall` is needed → `PathRepository.php:184`, `:206` (`.git` is a file in a worktree) → holds
- The mirror honours only `.gitattributes` `export-ignore` → `PathDownloader.php:159-160`; `ArchivableFilesFinder` with an empty exclude list → holds
- `minimum-stability: stable` plus VCS repos resolve the tags:
  - remote tags are core v5.4.1, baseline v2.17.0 and atelier v0.11.0 (`git ls-remote`);
  - atelier v0.11.0 requires core `^5.4` and baseline `^2.16.1`; baseline v2.17.0 requires core `^5.4`; no constraint is `dev-*`;
  - bedrock's root `roave/security-advisories: dev-latest` still resolves (`RootPackageLoader::extractStabilityFlags`).
  - → holds
- DDEV: `ddev version` is v1.25.3; `ntdst-testbed` is not in `~/.ddev/project_list.yaml`; `~/Sites/ntdst-testbed` does not exist → holds
- wp-starter's `gate/` integration subset at bdcd217 (HEAD, clean) → the files exist → holds
- The loaders are part of that subset → they are in `wp-starter/mu-plugins/`, not `gate/` → partly (N6)
- The guard is an allow-list → `gate/tests/bootstrap-integration.php:42-52`, echo at `:56` → holds
- The contract test asserts only the exit code, the guard message and the echo → `IntegrationBootstrapGuardTest.php:34-74`; a seam after `:56` keeps it green → holds
- `wptests` comes from a post-start hook → `gate/.ddev/config.gates.yaml:17` → holds
- The unit tier needs core installed → `gate/tests/bootstrap-unit.php:29` requires `ntdst-core/core/Container.php`, which is present at v5.4.1 → holds
- The testbed needs laika's autoload line → laika origin/main `tests/wp-tests-config.php:27-28`; atelier autoloads by classmap → holds
- A second load of wp-phpunit's `functions.php` is safe → `includes/bootstrap.php:31` uses `require_once` → holds
- `WP_TESTS_SKIP_INSTALL` exists → `includes/bootstrap.php:257` → holds
- netdust/bedrock:
  - there is no local clone; `new-site.sh:132` clones `git@github.com:netdust/bedrock.git`, whose main is 61492d8;
  - it carries roots/wordpress 6.8.2, twentytwentyfive, phpunit `^9.6`, wp-phpunit `~6.8.0`, polyfills `^4.0` and brain/monkey `^2.7`;
  - its `.gitignore` covers `web/app/mu-plugins/*/` and `/vendor`.
  - → holds; its `minimum-stability: dev` must change (N5)
- The boot is construct, `register`, then `bootCore` at `:5` and `bootFeatures` at `:15` → laika origin/main `laika-core.php:34-46`; core v5.4.1 `Bootstrap.php:193`, `:211`, `:611`, `:652` → holds; design §4 leaves out `register` (S2)
- Atelier's services boot on English DEFAULTS → `ProductionService.php:15`, `ModelsService.php:19`, `PerformanceService.php:16` → holds
- Baseline boots itself at `plugins_loaded:5` → v2.17.0 `ntdst-baseline.php:140`, `:172` → holds
- Atelier's `phpunit.xml` lists only `tests/Unit` → `phpunit.xml:3` → holds
- Host PHP has pcov and no mysqli → `php -m` → holds
- The atelier worktree has no `vendor/` → `ls -la` → holds (it is why S1's check cannot fail)
- PHPUnit 9.6 ignores an unknown `--testsuite` name, and an empty run exits 0 → `TestSuiteMapper.php:41`, `DefaultResultPrinter.php:413-419`, `Command.php:151-154` → holds (S2)
- The design file is at 961689a on spec/framework-tests, clean → `git log` → holds
- The worktree is `feature/atelier-integration` at 2d3e75d, which is e777ced plus the plan commit → holds
