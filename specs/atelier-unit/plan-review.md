# Plan review — atelier-unit (round 3)
Reviewed-plan: 79b6126061d727ff97115b455064a9a799c04ea7
Verdict: ready

Round 2's B1 and S1 are closed, and all six of its notes were taken. I checked each changed part against its source: Infection 0.35.8 (commit 642f797), Composer 2.8.9 and atelier at the worktree HEAD. Nothing in them is Blocking. The four Notes below are optional edits, and the plan can go to Stefan as it is.

## Round 2, item by item
- **B1, `--with-uncovered`:** closed.
  - Both scripts now pass the flag (Task 3, lines 92-93), and the plan says why.
  - With the flag set, `ExcludeUntestedNodesVisitor` is not added (`src/PhpParser/NodeTraverserFactory.php:103-104`). It is the only exclusion that depends on coverage, so `stats.totalMutantsCount` stays the same from one run to the next.
  - The JSON log lists the uncovered mutants (`src/Reporter/JsonReporter.php:84`, `src/Metrics/TargetDetectionStatusesProvider.php:153`).
  - So when a removed test was the only one covering a line, that line's mutants appear in the `diff` as new "not covered" lines.
  - The two optional parts were taken as well: the count on the first line (line 99), and the "none other than not covered" verdict (line 123).
- **S1, Review Focus 1:** closed. Line 38 now says only "Task 1 says so in its commit". The commit message in Task 1 Step 3 does say it ("the unit tier loads none of them").
- **N1, keep the reference lists:** taken. Line 109 keeps `baseline.json` and `accepted.txt`. Task 4 Step 1 reads the killed mutants from `baseline.json` and runs `diff` against `accepted.txt` (lines 123-125).
- **N2, what the JSON log gives:** taken (line 106).
- **N3, the positional filter:** taken.
  - `infection <path>…` is routed to `run` (`src/Console/Application.php:98-99`).
  - All three paths exist under `services/`, so Infection treats them as source paths (`src/Configuration/PositionalPathsClassifier.php:172`).
  - Each path is matched as a quoted substring of the file's real path (`src/FileSystem/Finder/Iterator/RealPathFilterIterator.php:82`).
- **N4, Composer's script timeout:** taken. `Composer\Config::disableProcessTimeout` exists in Composer 2.8.9 (`src/Composer/Config.php:657`), and the default timeout is 300 s (`:36`).
- **N5, the CLI guard:** taken (line 99). It is the same form as `tests/bootstrap.php:2`.
- **N6, the row values:** taken (lines 128-130), and labelled as a ruling Stefan sees. Both values hold:
  - **'question mark':** the label has no extension, so `withExtension` adds `.pdf` (`services/presskit/KitArchiveBuilder.php:128-136`). Then `?` is dropped (`services/presskit/ArchiveNameSanitizer.php:22`, `:65`). The result is `Documenten/Wat zag de koe.pdf`.
  - **'combining mark':** `\p{M}` is kept, and nothing on the path normalizes the name (`ArchiveNameSanitizer.php:45` only scrubs it). The result is `Documenten/Danie\u{0308}l.pdf`.
  - The new row is distinct from the existing 'accents' row (KABT:239), which uses the precomposed U+00EB (bytes `c3 ab`).

## Blocking
None.

## Should fix
None.

## Note
- **N1. Review Focus 3 still states the old rule** (line 40).
  - It says: "A candidate with none is 'not judged by Infection'".
  - Task 4 Step 1 (line 123) now applies a wider rule: none of the mutants is anything other than "not covered".
  - **Change:** reword Review Focus 3 to match, so the whole-branch reviewer checks the rule the code follows.
- **N2. Task 4 Step 1 has no verdict for a list that changes without gaining a survivor** (lines 125-126).
  - The step has two branches: "Identical" and "A new survivor". A run where a survivor disappears, or where the first line differs, fits neither.
  - Removing a test cannot kill a mutant, and the total is fixed (B1 above). So this can only come from a flaky timeout.
  - **Change:** add one line: "Anything else: not proven; rerun once, then name it in the report."
- **N3. The survivor list cannot see "skipped" mutants** (line 28 against line 99).
  - The survivor definition includes skipped mutants. The JSON log lists none of them (`JsonReporter.php:78-86`), and only `stats.skippedCount` records them.
  - A mutant going from killed to skipped is practically impossible: removing a test only lowers a mutant's nominal test time, and the skip is a time limit (`src/Process/Runner/MutationTestingRunner.php:166-178`).
  - **Change (optional):** print `stats.skippedCount` on the first line beside the total. That closes the gap at no cost.
- **N4. No step runs `composer mutation:diff`** (line 93).
  - This branch changes nothing under `services/` or `shared/`. Run here, Infection reports that the git diff has no files, as a success, and exits 0 (`src/Git/CommandLineGit.php:102-103`, `src/Command/RunCommand.php:398-402`).
  - **Change (optional):** one run in Task 3 Step 3 would prove the script's flags parse. It proves nothing about mutants.

## Premises checked
- `--with-uncovered` turns off the uncovered-node exclusion → Infection `src/Command/RunCommand.php:194-198`, `src/PhpParser/NodeTraverserFactory.php:103-104`, `CHANGELOG.md:35,52` → holds
- the mutant total does not depend on coverage when the flag is set → `NodeTraverserFactory.php:84-104`: no other visitor reads coverage → holds
- the JSON log carries `stats.totalMutantsCount`, `killed` and `uncovered` → `src/Reporter/JsonReporter.php:64`, `:80`, `:84` → holds
- the JSON log lists no skipped mutants, only `stats.skippedCount` → `JsonReporter.php:71`, `:78-86` → holds; this is why line 106 reports skipped as a run total
- `--log-verbosity=all` is a valid value → `src/Console/LogVerbosity.php:50` → holds
- `logs.text` and `logs.json` are in the config schema → `resources/schema.json:74`, `:82` → holds
- a bare `infection <path>` runs `run` → `src/Console/Application.php:98-99`, `:162-191` → holds
- the three paths are classified as source paths → `services/card/VCardBuilder.php`, `services/presskit/KitArchiveBuilder.php` and `services/presskit/ArchiveNameSanitizer.php` exist; `PositionalPathsClassifier.php:172` → holds
- `--filter` is deprecated since 0.34.0 → `src/Command/Option/SourceFilterOptions.php:135` → holds
- positional paths and the git-diff filter exclude each other → `SourceFilterOptions.php:273` → holds
- `--with-uncovered` can be combined with `--git-diff-lines` → `SourceFilterOptions.php` asserts no conflict between them → holds
- Composer kills a script after 300 s, and `Config::disableProcessTimeout` exists → Composer 2.8.9 `src/Composer/Config.php:36`, `:657` → holds
- the CLI guard form is `PHP_SAPI === 'cli' || exit;` → atelier `tests/bootstrap.php:2` → holds
- laika installs atelier as a source clone in a web-reachable directory → `~/Sites/atelier` is a symlink to `laika/web/app/mu-plugins/atelier`, which has a `.git` → holds
- the gate's lint also covers `bin/mutants.php` → atelier `composer.json`, `scripts.lint` → holds
- the 'question mark' and 'combining mark' values → `KitArchiveBuilder.php:88-90`, `:128-136`; `ArchiveNameSanitizer.php:22`, `:45`, `:65` → holds
- move 8 is at audit :506-508 and serves `presskit.spec.ts:1114` → laika `specs/test-prune/2026-10-18.md:506`, `:508`; `tests/E2E/presskit.spec.ts:1114` at 3ca8368 ("? dropped") → holds
