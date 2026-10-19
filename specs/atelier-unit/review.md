# atelier-unit — feature review

**Range:** `9d6cefe..380da01` (`origin/feature/atelier-unit`), run by the reviewer session (laika-c5).
**Plan:** `specs/atelier-unit/plan.md`.
**Reviewer:** superpowers' code reviewer (opus). No threat model, no `ARCHITECTURE-INVARIANTS.md`, no site, so no other reviewer joins.
**Re-checked by the reviewer:**
- `composer gate`: `OK (94 tests, 779 assertions)`, netdust 2.17.0 / 5.4.1.
- One `composer mutation`: identical to `accepted.txt`.
- The move diffs against laika's blobs: only the namespace and `use` lines.

## Blocking

1. **The zip half of the baseline is false, and three prunes went unproven** (C1).
   - **What happens:** Infection's include interceptor answers stat calls through `url_stat`, which returns false for an unreadable path (`vendor/infection/include-interceptor/src/IncludeInterceptor.php:385`). So in every mutant process `KitArchiveBuilderTest::test_an_unreadable_file_is_skipped_and_reported` (`:159-174`) fails. Infection runs a covering test's whole file and stops at the first failure, so every KitArchiveBuilder and ArchiveNameSanitizer mutant that KABT covers was counted killed, whatever it changed.
   - **How far it reaches:** 32 of the zip's 123 kills are this failure. Re-judged with plain PHPUnit, 13 zip mutants escape before the prune and 16 at head.
   - **The three prunes:** #15, #17 and #18 could not have come out other than "identical".
     - #17 (the README test) was the only real kill of `KitArchiveBuilder.php:45` ×3.
     - #15 and #18 together held atelier's only check that an entry carries the file's own bytes. A hand-mutant `addFromString($folder.'/'.$name, '')` dies at 9a390dc and escapes at head.

   **Fix:**
   1. Keep that test out of Infection's runs: `#[Group('no-mutation')]` plus `"testFrameworkOptions": "--exclude-group=no-mutation"`.
   2. Re-run the Task 3 baseline, and rewrite the zip rows, the survivor list and the cited-lines table in `specs/mutation/2026-10-19.md`.
   3. Restore #17's README assertion, with the text padded so UnwrapTrim dies too.
   4. Restore a bytes assertion: both entries' `getFromName()` in `test_an_entry_carries_its_files_extension_once`. Using both entries also kills "the first file for every entry".
   5. Re-judge #15 and #18 with that assertion in place.
   6. Prove the exclusion first: one run of the suite under the interceptor with no mutation, with zero failures.

## Should fix

2. **Survivors to kill now** (the reviewer's verdicts, each test verified green on the original and red on the mutant in scratch, `probe/ProposedVCardKillsTest.php`):
   - **`VCardBuilder.php:48` (cut `-1` → `-2`):** `foldLine('NOTE:'.str_repeat('x',200))` gives physical line lengths `[75, 75, 57]`.
   - **`VCardBuilder.php:98` (`dialable` made protected):** `CardModel.php:63` calls it from outside the class. `dialable('+32 (0)3 123-45-67')` is `'+32031234567'`, `'03 + 123 +45'` is `'0312345'`, and `'+ ( ) -'` is `''`.
   - **`VCardBuilder.php:121`:** a source with no name parts has no `N:` line, and `given 'Jan'` with family `''` gives `N:;Jan;;;`.
   - **`VCardBuilder.php:134` (UnwrapTrim):** title `" zakelijk leider\n"`, email `'   '`, company `"Laika \n"` and `' https://laika.be '` give `TITLE:zakelijk leider`, `ORG:Laika`, `URL:https://laika.be` and no EMAIL line.
   - **The zip mutants C1 hid:**
     - `KitArchiveBuilder.php:45` ×4 (README; covered by 1.3);
     - `ArchiveNameSanitizer.php:106` DoWhile: with labels `Dossier-1`, `Dossier`, `Dossier`, a file silently disappears;
     - `:86`, the trim after the cut;
     - `:79`, a trailing dot;
     - `:119` IncrementInteger: the 120-byte cap on an ASCII name.
3. **A silent Infection exit still passes `composer mutation`** (M1). The script deletes `build/infection/mutation.json` first and ends with `@php bin/mutants.php`, so a run that writes nothing fails and every run prints its list.
4. **`bin/mutants.php`'s default log path is relative to the working directory** (M2). Use `$root . 'build/infection/mutation.json'`.

## Note

- **Equivalent, leave:**
  - `VCardBuilder.php:39` (`<=` → `<`) and `:40` (ReturnRemoval): a 75-octet line comes back unchanged through the fold path. Swept for every length up to 75.
  - `ArchiveNameSanitizer.php:59` and `KitArchiveBuilder.php:130` CastString.
  - `KitArchiveBuilder.php:113` Decrement and LessThan.
- **Leave:**
  - `KitArchiveBuilder.php:95` (unreachable at the unit seam).
  - `ArchiveNameSanitizer.php:109` (a timeout is detected).
  - `ArchiveNameSanitizer.php:46`, `:113` and `KitArchiveBuilder.php:68`.
- **The compact record lost its blank-value check** (M4). A compact-only empty-line mutant escapes since #7 went. Optional: run `test_a_minimal_source_is_a_valid_record_with_no_empty_line` in both modes.
- **The reviewer's stray brief** (M3). `.coder-brief.md` is untracked and is never merged. Stefan deletes it.
- **For Stefan, a production behaviour outside this branch.** Two collections with the same label share a folder but not a name space (`KitArchiveBuilder.php:66-67`), so with `addFile` overwriting, a second `Fotos/koe.jpg` silently replaces the first. laika's collections take distinct labels from `wording.json`, so it is not reachable there today.
- **Lesson candidate** (`netdust-wp:wp-testing`, Infection). A test that relies on file permissions fails under Infection's include interceptor and masks every mutant in its file. Before trusting a baseline, run the suite once under the interceptor with no mutation.

Findings: 1 Blocking · 3 Should fix · 5 Note
