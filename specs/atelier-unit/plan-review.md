# Plan review — atelier-unit
Reviewed-plan: b1266379eafc20f02190feb6da74c7f76f9b3e23
Verdict: not ready

One Blocking item: a premise that is false in source. The fix is a rewording, but Stefan should read the reworded version, because it changes what F1 delivers at atelier. The five Should fix items are commands and definitions. None of them changes the plan's shape.

## Blocking

**B1. Atelier's unit tier loads no core or baseline code, so Task 1 cannot "show what the real versions break".**
- **Plan:**
  - Architecture, "First working version" (line 10): "passes, or shows what the real versions break".
  - Task 1 Step 2 (line 59): "A test that fails against the real versions is this task's real finding".
  - Review Focus 1 (line 29).
  - Test map row 1 (line 44): "against the locked versions".
- **Source:**
  - Neither package declares an autoload. `git -C ~/Sites/ntdst-core show v5.4.1:composer.json` and `git -C ~/Sites/ntdst-baseline show v2.17.0:composer.json` have no `autoload` key, and `~/Sites/atelier/vendor/composer/autoload_*.php` holds no netdust entry.
  - `tests/bootstrap.php:3` requires only `vendor/autoload.php`. No file under `tests/`, `services/` or `shared/` requires a `vendor/netdust` path.
  - The tests stub core instead of loading it: `tests/Unit/Planning/RelationTargetsTest.php:33` (`Functions\expect('ntdst_data')->never()`) and `tests/Unit/Production/ProductionVocabulariesTest.php:27-37` (a hand-written registry returned from `ntdst_data`). Brain Monkey can stub `ntdst_data` only because core's function is never defined.
- **What is wrong:** after Task 1 the gate prints 5.4.1 and 2.17.0 and passes, but no line of either package ran. It would pass the same way against 5.2.0. This is edge class 8 (compiled, not rendered), and it is the false comfort Review Focus 1 exists to catch. The spec's "Atelier's gate runs against core 5.2.0" is just as loose, but the promise that Task 1 can find a breakage is the plan's own.
- **Smallest change:** reword those four places:
  - Task 1 records and prints what is installed: the lock plus the version lines.
  - No atelier unit test loads core or baseline (cite the two stubs).
  - Atelier first runs against real core on F2's test site.
  - Drop "or shows what the real versions break" and Step 2's fix path.
  - Add no test here to make up for it. A reflection check on core's registry signature would be a shape test.

## Should fix

**S1. Task 1's version line breaks the gate** (Task 1, Files, line 55).
- `@composer show netdust/ntdst-core netdust/ntdst-baseline` passes `netdust/ntdst-baseline` as the `version` argument. In Composer 2.8.9 (`src/Composer/Command/ShowCommand.php`, read from the phar), the second argument is `InputArgument('version', …, 'Version or version constraint to inspect')`, and `getPackage()` passes it to `parseConstraints()`. The gate's new first step therefore throws before lint runs.
- A single `show <package>` also prints a full info block.
- **Change:** use `"@composer show netdust/*"`. The `package` argument takes a wildcard to filter the list, and the output is one line per installed netdust package, with its version.

**S2. Task 2's before-count runs one file, on a different runner** (Task 2 Step 1 line 69, Step 3 line 75; Review Focus 2 line 30).
- laika runs PHPUnit 9.6.35 (laika `vendor/composer/installed.json`). Its CLI reads only the first positional argument (`vendor/phpunit/phpunit/src/TextUI/CliArguments/Builder.php:234-235`), so it silently drops `tests/Unit/PressKit`.
- Step 1 therefore counts 44 tests: VCT has 36 methods and two providers of 3 and 7 rows. Atelier's two files hold 86: KABT adds 21 methods with `editorNames`' 22 rows, which is 42.
- The fallback, "take the counts from the files", gives no assertion count, and Step 3 demands one. Step 3's `composer gate` prints only the suite total.
- Review Focus 2 names the base class as the risk. These tests call no WordPress function and set no Mockery expectation, so the base class adds nothing. The real change is PHPUnit 9.6 → 10.5.
- **Change, simplest first:**
  - Drop the laika run and prove the move with a diff, `diff <(git -C ~/Sites/laika show 3ca8368:tests/Unit/Card/VCardBuilderTest.php) tests/Unit/Card/VCardBuilderTest.php` (likewise for KABT). It should show only the namespace and `use` lines changed.
  - Add a green `vendor/bin/phpunit tests/Unit/Card tests/Unit/PressKit` on 10.5, which accepts several paths.
  - That proves "every assertion kept" better than equal counts do, and it needs no DDEV. If the counts stay, use one laika command per file.
  - Either way, pin the source. Both files exist only on laika `feature/presskit` (at 3ca8368: blobs 8ebc744 and 343a099) and `staging`, not on laika's `main`.
  - Review Focus 2 should name the runner change.

**S3. "Surviving mutant" is undefined, and the configured logs cannot give what Task 3 Step 3 records** (Task 3 Files line 82, Step 3 lines 88-92; Task 4 Step 1 line 105; Review Focus 3).
- Removing the only test that covers a line makes its mutants "not covered", not "escaped". A killed mutant can also become a timeout. If the compared list holds only escaped mutants, such a DELETE passes as proven. That is exactly Review Focus 3's case.
- `logs.summary` covers the whole run, and `logs.text` omits killed mutants at default verbosity. Neither can give "per class: the score, and the killed … counts".
- **Change:**
  - Define a survivor as every mutant that was not killed: escaped, timed out, not covered or skipped. Key each one by mutator + file + line + diff.
  - Add `logs.json` (it is in 0.35.8's schema) and run with `--log-verbosity=all`.
  - Compare runs by extracting those keys, so the comparison over 18 runs is mechanical, not done by eye.

**S4. An identical list proves nothing where Infection generates no mutant for the behaviour** (Task 4 Step 1, lines 105-107).
- For each DELETE, the audit names the production line a mutant must reach (`laika/specs/test-prune/2026-10-18.md:373-399`, last column).
- For KABT:142 that line is "`ArchiveNameSanitizer.php:34` or `KitArchiveBuilder.php:66` made static" (:397). In source, those lines are `private array $issued = [];` and `$names = new ArchiveNameSanitizer();`. No Infection mutator makes a property static or shares an instance. The run will report "identical", and the test goes.
- Atelier's only other cover, KABT:255-261, holds only through state carried between data-provider rows (audit :87, :397, :843).
- **Change:**
  - For each candidate, quote Task 3's mutants on the audit's cited lines.
  - Report a candidate with no mutant there as "not judged by Infection". It stays until Stefan decides.
  - State that each run keeps the earlier proven removals. Otherwise two candidates that cover each other can both go on separate "identical" runs.

**S5. F6's "changed code only at review" has no home, and Task 3's config shuts it out** (Spec line 14; Task 3 Files line 82).
- In Infection 0.35.8, `source` accepts only `directories` (required) and `excludes` (`resources/schema.json`). "Source: the three classes" therefore becomes two directories plus excludes, and a review-time `--git-diff-lines` run would never reach grants, performance, planning, profile or production.
- The step also shows neither `infection.json5` nor the script.
- **Change:**
  - Show `infection.json5` with `source.directories: ["services", "shared"]`.
  - Put the three classes on the baseline command: `infection --filter=services/card/VCardBuilder.php,services/presskit/KitArchiveBuilder.php,services/presskit/ArchiveNameSanitizer.php` (`src/Command/Option/SourceFilterOptions.php:80`).
  - Then choose one of two:
    - add a second script for review, `infection --git-diff-lines --git-diff-base=origin/main` (the plain filter and the git-diff filter exclude each other, :120, hence two scripts);
    - or narrow the Spec line to "F6's module baseline" and name who owns the review-time run.

## Note

- **N1. The Spec line claims more than this plan delivers.**
  - Under the spec's Cut, the baseline and core plans own F1's second and third bullets (baseline loads real core; core's branch alias) and F6's "on each package". Write "F1 (atelier's bullet)" so F1 is not closed when this merges.
  - The cited path `~/Sites/netdust-wp-manager/specs/framework-tests/spec.md` does not exist on disk, because that checkout is on `master`. Cite `~/Sites/netdust-wp-manager-framework-tests/specs/framework-tests/spec.md`, or `git show spec/framework-tests:specs/framework-tests/spec.md`.
- **N2. Task 3's stop will not fire.** PCOV is installed now: `php -m` on PHP 8.3.6 lists `pcov`.
- **N3. The committed lock pins a dev tool that needs PHP 8.3.** Infection 0.35.8, the newest tag, requires `php ^8.3`, while atelier declares `>=8.2`, so an 8.2 box cannot install from the lock. The host and laika's DDEV are both 8.3, so nothing breaks today. One line in the plan is enough.
- **N4. Deleting a test can leave its provider dead.** Deleting VCT:125 and VCT:288 leaves `emptyFieldProvider` and `lineTerminatorProvider` unused (audit :585), and PHPUnit does not warn about that. Delete each provider with its last test.
- **N5. Audit move 8 now has no owner.** Move 8 (audit :506-508) adds the `editorNames` rows "question mark" and "combining mark" to KABT, for laika's presskit.spec.ts:1114 DELETE. With KABT in atelier, the rows land in atelier, and laika's cleanup can only get them in through an atelier change. Stefan decides whether this Task 4 takes them or a later atelier change does.
- **N6. Keep the move line for line.** Change only the namespace and `use` lines, so the audit's `VCT:` and `KABT:` line citations still resolve in atelier during Task 4.
- **N7. Review Focus 4 has no invariants check yet.** Its "never in the gate" and "lock not ignored" are absences, and atelier has no `ARCHITECTURE-INVARIANTS.md` until F9 adds one. The manual checks stand until then. Task 3 edits `.gitignore`, so add `git check-ignore -q composer.lock` (expect exit 1) to Task 3 Step 4.

## Premises checked
- `^5.4`/`^2.16.1` required, lock gitignored → `composer.json:14-15`, `.gitignore:2` → holds
- gate = dump-autoload, lint, test → `composer.json:37-41` → holds
- host Composer reaches GitHub over SSH; newest `^5.4` is v5.4.1, newest `^2.16.1` is v2.17.0 → `git ls-remote` on both repos; Composer 2.8.9 → holds
- baseline v2.17.0 resolves with core 5.4.1 → `v2.17.0:composer.json` requires `netdust/ntdst-core ^5.4` → holds
- sites run those versions → laika `composer.lock`: core v5.4.1, baseline v2.17.0, atelier v0.11.0 → holds
- the stale vendor held core 5.2.0 and baseline 2.6.0 → `~/Sites/atelier/vendor/composer/installed.json` (also PHPUnit 10.5.65, Brain Monkey 2.7.0, no bacon/bacon-qr-code) → holds
- the gate "ran against" those versions, so a new version can fail a test → no `autoload` in core v5.4.1 or baseline v2.17.0; `tests/bootstrap.php:3`; `RelationTargetsTest.php:33`; `ProductionVocabulariesTest.php:27-37` → does not hold (B1)
- 31 existing tests → 26 methods plus a 6-row provider (`RelationTargetsTest.php:43-55`) → holds
- atelier's tests extend PHPUnit's `TestCase` → `tests/Unit/Support/LabelsTest.php:8,10` → holds
- laika's `TestCase` is Brain Monkey set-up and tear-down → laika `tests/Unit/TestCase.php:19-33` (it also uses `MockeryPHPUnitIntegration`, which these files never exercise) → holds
- the moved tests call no WordPress function → no `Functions\` in either file; the classes are pure (`VCardBuilder.php:9-14`, `KitArchiveBuilder.php:9-14`, `ArchiveNameSanitizer.php:9-13`) → holds (port nothing)
- laika's command counts both files → laika PHPUnit 9.6.35, `CliArguments/Builder.php:234-235` → does not hold (S2)
- `composer show A B` prints both packages → Composer 2.8.9 `ShowCommand`: the second argument is `version` and goes to `parseConstraints()` → does not hold (S1)
- the three classes exist at the paths given → `services/card/VCardBuilder.php:15`, `services/presskit/KitArchiveBuilder.php:15`, `services/presskit/ArchiveNameSanitizer.php:14`; identical to laika's installed v0.11.0 → holds
- `infection.json5` can name three classes as its source → 0.35.8 `resources/schema.json`: `source` is `directories` + `excludes` → does not hold as written (S5)
- Infection works with PHPUnit 10.5 on PHP 8.3 → 0.35.8 requires `php ^8.3`; `PhpUnitAdapter.php:348` handles 10.5; `XmlConfigurationManipulator.php:216` adds `<source>` itself, so atelier's `phpunit.xml` needs none → holds
- PCOV on the host → `php -m` → holds (installed now)
- 18 DELETE candidates, 14 VCT and 4 KABT → audit :373-399 → holds
- the move was decided at `card/review.md:27,38` → holds for VCardBuilderTest; KABT's move is ruling (c) (audit :664, :734) → holds
- the KABT DELETEs lean on laika's integration tests → audit :392, :396-399, :732 → holds
- `~/Sites/atelier` is laika's clone at v0.11.0 → symlink to `laika/web/app/mu-plugins/atelier`, HEAD 9d6cefe = v0.11.0 → holds
- the source files are "laika `tests/Unit/...`" → present only on laika `feature/presskit` and `staging`, not `main` → holds only for the current checkout (S2)
- the WordPress pack and a threat model are not owed → atelier has no `site.yml` and no `roots/wordpress` (policy `SKILL.md:69`); the spec flags no security surface → holds
- spec path `~/Sites/netdust-wp-manager/specs/framework-tests/spec.md` → absent on disk; the file lives on branch `spec/framework-tests` → does not resolve (N1)
