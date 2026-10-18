# Plan review — atelier-unit (round 2)
Reviewed-plan: 0e2662859753afcaa695796efc6f33cbe38668e3
Verdict: not ready

Round 1's B1 and S1, S2, S4 and S5 are closed. S3 is closed on paper, but the commands the plan writes switch it off: Infection 0.35.8 does not mutate uncovered code unless you pass a flag, so the "not covered" survivors the plan now counts never appear. That is the one Blocking item below, and the fix is one flag on two scripts. Everything else goes to Stefan as one Should fix and some Notes.

## Round 1, item by item
- **B1, versions that look tested:** closed. The Architecture section, the First working version, Review Focus 1, Test map row 1, Task 1 Step 2 and the Step 3 commit no longer promise to find breakage, and the two stubs are cited. The citations hold.
- **S1, `composer show`:** closed. `"@composer show netdust/*"` works. In Composer 2.8.9, `ShowCommand.php:300` sends a name containing `*` to the list view, and `:407` turns it into a regex. No `./netdust` directory exists, so the shell leaves the glob as it is.
- **S2, the move's proof:** closed.
  - The proof is a diff against pinned blobs, and the blobs are right: `8ebc744`, `343a099`.
  - The 10.5 run takes several paths (`TestSuiteBuilder.php:44-47`).
  - 86 is correct: VCT has 36 methods with providers of 3 and 7 rows, which is 44. KABT has 21 methods with 22 `editorNames` rows, which is 42.
  - Review Focus 2 names the runner change.
- **S3, survivors:** the definition, `logs.json`, `--log-verbosity=all` and the mechanical `diff` are all there. The "not covered" part does not work (B1 below).
- **S4, mutants on the cited lines:** closed. Step 1 quotes the cited-line mutants, uses the "not judged by Infection" verdict, and keeps earlier removals.
- **S5, the review-time run:** closed.
  - `source.directories` is shown, the scripts are shown, and the `mutation:diff` script exists.
  - `--git-diff-base` is accepted together with `--git-diff-lines` (`SourceFilterOptions.php:142-158`).
- **N1 to N7:** all taken up. N5 is now a ruling in the plan, so Stefan can see it.

## Blocking

**B1. Infection 0.35 does not mutate code that no test covers, so the survivor list cannot grow when a removed test was a line's only cover.**
- **Plan:**
  - Global Constraints, line 28: a survivor includes "not covered", and "A DELETE is proven when the survivor list with the test removed equals the list with it present".
  - Task 3 Files, lines 92-93: `scripts.mutation` and `scripts.mutation:diff`, neither with `--with-uncovered`.
  - Task 4 Step 1, line 124.
- **Source** (Infection tag 0.35.8, commit 642f797):
  - `CHANGELOG.md:35,52`, in 0.31.0: "Remove `--only-covered`; Introduce `--with-uncovered` … by default, Infection doesn't mutate uncovered code anymore".
  - `src/Command/RunCommand.php:194-198`: `--with-uncovered` is a flag that is off by default.
  - `src/PhpParser/NodeTraverserFactory.php:103-104` adds `ExcludeUntestedNodesVisitor` when only covered code is mutated. That visitor (`:50`) marks every node with no covering test as ineligible, so Infection generates no mutant for it.
  - `src/Reporter/JsonReporter.php:84`: `'uncovered' => $this->onlyCoveredMode ? [] : …`.
- **What is wrong:**
  - Take a candidate that is the only atelier test executing a line. This is most likely among the KABT four, whose other cover was laika's PKT. Remove it, and that line's mutants go from killed to not generated at all. They were never in the survivor list, so they are still not in it. The `diff` reads "identical" and the DELETE counts as proven.
  - This is the false proof round 1's S3 was written to close, and it is the case Review Focus 3 and 4 guard.
  - The same default makes `mutation:diff` stay silent about changed code that has no test at all, and that is the code a review most needs to see.
  - The 0.35.x pin makes this certain, not a matter of version drift.
- **Smallest change:**
  - Add `--with-uncovered` to both scripts.
  - Optionally, have `bin/mutants.php` print `stats.totalMutantsCount` as its first line, so a mutant that disappears can never pass a `diff` unnoticed.
  - In Task 4 Step 1, "none" then means no mutant on the cited lines that Task 3 reports as anything other than "not covered".

## Should fix

**S1. Review Focus 1 promises gate output that no step produces** (Review Focus line 38: "Task 1 says so in its commit and in the gate's output").
- Task 1 adds only `@composer show netdust/*`, which prints names and versions. It cannot say that the unit tier loads neither package. The commit message (Task 1 Step 3) does say so.
- **Change:** drop "and in the gate's output". Alternatively, add an `@php -r "echo 'unit tier loads no netdust package (spec F2 tests real core)', PHP_EOL;"` step after the `show` line.

## Note

- **N1. Keep the reference lists.**
  - `bin/mutants.php` prints only survivors (line 99). Task 4 Step 1's "Task 3's mutants on its cited production lines" (line 123) also needs the killed ones. Those are only in `build/infection/mutation.json`: `--log-verbosity` does not matter here, because `TargetDetectionStatusesProvider.php:150-165` collects killed mutants for every JSON log. That file is gitignored, and Task 4's first run overwrites it.
  - "The previous run's" list (line 124) has to be the last accepted state, not a run whose removal was restored.
  - **Change:** copy Task 3's JSON to `build/infection/baseline.json`, and keep the accepted list as `build/infection/accepted.txt`.
- **N2. What the JSON log can give** (Task 3 Step 2, line 106).
  - It has no per-class stats, so the per-class counts come from grouping entries by `originalFilePath`.
  - It lists no "skipped" mutants, only `stats.skippedCount` (`JsonReporter.php:63-86`). Skipped means skipped for time (`MutationTestingRunner.php:174`), so per-class "skipped" can only be the run total, expected to be 0.
- **N3. `--filter` is deprecated** since 0.34.0 and warns on every run (`SourceFilterOptions.php:135`). It still works. The current spelling is positional: `infection services/card/VCardBuilder.php services/presskit/KitArchiveBuilder.php services/presskit/ArchiveNameSanitizer.php`.
- **N4. Composer's script timeout.** Composer kills a script after 300 s (`Config.php:36`). If one `composer mutation` run gets past that on this box, it dies part-way. Put `"Composer\\Config::disableProcessTimeout"` first in both scripts (`Config.php:657`).
- **N5. `bin/mutants.php` ships inside the mu-plugin.** laika installs atelier as a source clone under `web/app/mu-plugins/atelier`, which is web-reachable on Bedrock. Every PHP file atelier ships now carries a guard: `services/**` uses `defined('ABSPATH') || exit;`, and `tests/bootstrap.php:2` uses `PHP_SAPI === 'cli' || exit;`. Open `bin/mutants.php` with the same CLI guard.
- **N6. Task 4 Step 2 names the rows but not their values** (line 128).
  - Source decides them. `?` is outside `SAFE_CHARACTERS` and is dropped, and `\p{M}` is kept (`ArchiveNameSanitizer.php:19-22`, `:65`). That matches the e2e title's "? dropped" (laika `tests/E2E/presskit.spec.ts:1114`).
  - For example: `'question mark' => ['Wat zag de koe?', 'Documenten/Wat zag de koe.pdf']` and `'combining mark' => ["Danie\u{0308}l", "Documenten/Danie\u{0308}l.pdf"]`.
  - The step calls this "the reviewer's" ruling. Round 1 left move 8 to Stefan, so he confirms it when he reads the plan.

## Premises checked
- core v5.4.1 and baseline v2.17.0 declare no autoload; baseline requires `netdust/ntdst-core ^5.4`; no `composer/installers` → `git show v5.4.1:composer.json`, `git show v2.17.0:composer.json` → holds
- newest `^5.4` is v5.4.1, newest `^2.16.1` is v2.17.0 → `git ls-remote --tags` on both repos → holds
- the tests stub `ntdst_data` → `tests/Unit/Planning/RelationTargetsTest.php:33`, `tests/Unit/Production/ProductionVocabulariesTest.php:27-37` (the registry is returned at :37) → holds
- 31 existing tests → 26 methods, plus `activityEdges`' 6 rows (`RelationTargetsTest.php:55`) → holds
- `@composer show netdust/*` prints one line per installed package → Composer 2.8.9 `ShowCommand.php:300`, `:407` → holds
- the gate's lint covers every PHP file outside vendor, so `bin/mutants.php` too → `composer.json` `scripts.lint` → holds
- source blobs 8ebc744 and 343a099 at laika 3ca8368 → `git rev-parse 3ca8368:<path>` → holds
- only lines 5 and 8 (VCT) and lines 5 and 9 (KABT) need to change; the files reference no laika fixture or helper → `NtdstTests\Unit\TestCase` is the only foreign symbol; `asset()` writes temp files (KABT:438-442) → holds
- the providers are static, and `@dataProvider` doc-comments run on 10.5 → VCT:139, :297; KABT:223 → holds
- the moved classes need only `ABSPATH`, which atelier's bootstrap defines → `VCardBuilder.php:7`, `KitArchiveBuilder.php:7`, `ArchiveNameSanitizer.php:7`; `tests/bootstrap.php:4` → holds
- PHPUnit 10.5 runs several path arguments → `TestSuiteBuilder.php:44-47` (10.5.65) → holds
- 86 tests (44 + 42) → method and provider-row count → holds
- the host has PCOV, and its directory defaults to the worktree; no Xdebug; ext-zip, mbstring and iconv present → `php -m`; `php -i` from the worktree prints `pcov.directory => …/atelier-unit` → holds
- Infection 0.35.8 needs PHP ^8.3 and pulls the `infection/extension-installer` plugin; no conflict with Brain Monkey's patchwork ≥2.1.25 → `composer.json` at 0.35.8 → holds
- `source` takes `directories` and `excludes`; `logs.json` is in the schema; `testFramework` is `phpunit` → `resources/schema.json` → holds
- the plain filter and the git-diff filter exclude each other; `--git-diff-base` is accepted with `--git-diff-lines` → `SourceFilterOptions.php:142-158`, `:254` → holds
- uncovered mutants show up as "not covered" in the survivor list → `RunCommand.php:194-198`, `NodeTraverserFactory.php:103-104`, `JsonReporter.php:84` → does not hold without `--with-uncovered` (B1)
- the JSON log lists killed mutants, so cited-line mutants can be quoted → `TargetDetectionStatusesProvider.php:150-165` → holds
- the JSON log carries skipped mutants and per-class stats → `JsonReporter.php:63-86` → does not hold (N2)
- the audit's DELETE rows, move 8 and the dead providers → laika `specs/test-prune/2026-10-18.md:375-399`, `:508`, `:585` → holds
- KABT:142's cited lines cannot be reached by a mutator → `ArchiveNameSanitizer.php:34` is `private array $issued = [];`, `KitArchiveBuilder.php:66` is `new ArchiveNameSanitizer()` → holds
- laika's DDEV and the host both run PHP 8.3 → laika `.ddev/config.yaml:4`; `php -v` 8.3.6 → holds
- `netdust-gates:feature-review` exists → `netdust-gates/0.10.0/commands/feature-review.md` → holds
- the spec path and its amended F1 → `~/Sites/netdust-wp-manager-framework-tests/specs/framework-tests/spec.md` at 4bb9b2a → holds
