# atelier-integration — feature review

**Range:**
- atelier: `e777ced..5433306` (`origin/feature/atelier-integration`);
- the test site: `~/Sites/ntdst-testbed` at 1ce2df8 (local `main`, no remote).

The reviewer session (laika-c5) ran the review.
**Plan:** `specs/atelier-integration/plan.md`. **Design:** `netdust-wp-manager/specs/framework-tests/testbed-design.md`.
**Reviewer:** superpowers' code reviewer (opus). There is no threat model and atelier has no `ARCHITECTURE-INVARIANTS.md` yet (F9), so no other reviewer joins.
**Re-run by the reviewer:**
- `composer test:int` gives `@ 5433306 (clean), resolved as 0.11.0`, neighbours core 5.4.1 and baseline 2.17.0, `OK (12 tests, 87 assertions)`, and the test site stays clean.
- `composer gate` gives `OK (105 tests, 833 assertions)`.

## Blocking

None. atelier's branch is ready as it stands. The test site needs this fix pass before a second package, or atelier's next minor tag, uses it.

## Should fix

1. **The worktree's `.git` file travels into the copy** (I1).
   - **What happens:** Composer's Finder skips only `.git` directories, so the copy holds `gitdir: …laika/web/app/mu-plugins/atelier/.git/worktrees/atelier-integration`. Git run in the copy acts on that worktree's index and the shared refs: `git status` rewrites the index, and `git reset --hard` moves the real branch. An IDE opening the test site would run git there.
   - **Fix:** the runner creates the hazard, so it removes it. After `reinstall`, it deletes a `.git` file from the installed copy (`composer show --path "$name"`), for any package. Add `/.git export-ignore` in atelier's `.gitattributes` as a belt.
   - **Proof:** `ls -la ~/Sites/ntdst-testbed/web/app/mu-plugins/atelier/.git` exists today and fails after the fix.
2. **The package's next minor or major tag breaks `test:int`** (I2).
   - **What happens:** the test site pins `"netdust/atelier": "^0.11"` (`composer.json:48`). Once v0.12.0 exists, `git describe` (`bin/test-package:38`) pins the path repo to 0.12.0, and `composer update` fails. `--refresh` keeps the root constraint, so it cannot repair it.
   - **Fix:** `| .require[$name] = $version` in the jq at `:41-43`, so the package under test always satisfies its own pin.
   - **Proof:** a scratch clone of atelier under the scratchpad with a local `v0.12.0` tag is RED before the fix and GREEN after. Never tag the real repo.
3. **Runner robustness:**
   - **M1.** A killed runner releases the host lock while PHPUnit keeps running in the container. Take a lock inside the container as well (`flock -n /tmp/testbed.lock … vendor/bin/phpunit …`).
   - **M2.** A relative path argument resolves against the test site's root, because the `cd` comes before `realpath "$2"`. Resolve the path first.
   - **M5.** A package with no registered `<testsuite>` runs nothing and exits 0. The runner checks `phpunit.integration.xml` for the suite and refuses otherwise.
   - **M6.** The plan's "never test from laika's live install" is not enforced. Refuse a realpath matching `*/web/app/mu-plugins/*`.
   - **M7.** `composer update` audits over the network. Add `--no-audit`, so the "no network" ruling holds.
   - **M8.** Two fixes:
     - a checkout with no tag gets git's error, exit 128; refuse it with 64 instead;
     - check the arguments before taking the lock, so a bad call is refused, not reported as "busy".
   - **M4.** `--refresh` has no quoted run; add a dry run.
4. **Test-site hygiene:**
   - **M10, dead weight:** drop the bedrock dead weight (phpstan, phpstan-wordpress, wpcs, phpcompatibility-wp, the dealerdirect installer, pint and `pint.json`), plus the Playwright, node_modules and `.claude` entries in `.gitignore`. Rename the package from `roots/bedrock` to `netdust/ntdst-testbed`.
   - **M9, `ntdst_set`:** add laika-core's `ntdst_set(NTDST_Bootstrap::class, …)` line to `bootstrap-site.php` for fidelity. Nothing reads it today.
   - **M3, a known limit:** the copy also carries gitignored and untracked files (`.phpunit.result.cache`, a worktree `.env`). Name this as a limit in a comment at the runner's mirror line.

## Note

- **Strengths confirmed by the reviewer:**
  - The harness is faithful to wp-starter `bdcd217`; only the two F8 divergences differ, each with its comment.
  - The guard is intact.
  - The boot order equals laika-core's.
  - The move is namespace-only, from blob 82392d8.
  - Quoting is safe.
  - `reinstall` re-dumps the autoloader.
  - `9>&-` keeps the lock out of DDEV.
  - Isolation: its own db container on ephemeral ports, no `.env`, nothing that can deploy.
- **README vs F9:** passes (no how-to).
- **Fidelity differences:**
  - timezone, Dutch labels, overrides and the yootheme opt-in, as named;
  - PHPUnit 9.6.38 / wp-phpunit 6.8.9 against laika's 9.6.35 / 6.8.6;
  - M9, until fixed.
- **For Stefan:** a test-site remote, and the tag stamp (spec Order 9).
- **Older than this branch:** laika's composer-installed atelier holds three worktrees' admin dirs, so a reinstall there would break those worktrees. Branches are pushed, so it is recoverable.

Findings: 0 Blocking · 4 Should fix · 5 Note
