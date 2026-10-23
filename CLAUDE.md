# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Scope

This is one XenForo add-on — `Hampel/AccountUpgradesInfo`, "Account Upgrades Info". It sits at
`src/addons/Hampel/AccountUpgradesInfo/` inside a XenForo install and is its own git repository;
the install around it is not, and the sibling add-ons beside it are other repositories.

Where the install has an `AGENTS.md` at its root, read it first — it carries the XF conventions,
the `_output`/`_data` boundary, the `cmd.php` command signatures and the per-add-on git layout.
This file covers only what is specific to this add-on.

## What this add-on is

**Four admin options and two template modifications. Nothing in this repository executes when a
page is served.** The whole feature is rendered by XenForo's template modification system from
option values. The only PHP that ever runs is `Setup.php` during an install or upgrade, and a
one-line listener fired by another add-on if that add-on is installed.

The entire surface:

- `_output/options/` — `hampelAccountUpgradesInfo{Title,Body}{Before,After}`, all `string`,
  `Title*` a one-line textbox and `Body*` a `rows=3` textbox.
- `_output/option_groups/hampelAccountUpgradesInfo.json` — the group they live in.
- `_output/template_modifications/public/hampelAccountUpgradesInfo{Above,Below}.json` — the two
  `str_replace` modifications against the `account_upgrades` template.
- `_output/phrases/` — the option titles, explains, and the group title and description.
- `Setup.php` — no install, upgrade or uninstall steps; `postUpgrade()` only calls
  `enqueuePostUpgradeCleanUp()`, guarded to XF 2.3+.
- `Listener.php` and `_output/code_event_listeners/` — one static method answering
  `hampel_admin_api_redaction`, which declares that this add-on has no secrets. See *Declaring
  no secrets* below.

No class extensions, entities, repositories, routes, permissions, JS or LESS. Adding any of those
means creating `_output/<type>/` with `xf-make:*`, which writes the database record, the
`_output/` file and its metadata entry together — so nothing follows it, neither an import nor an
export.

## Declaring no secrets

`Listener::adminApiRedaction()` answers the `hampel_admin_api_redaction` code event with
`$declarations['Hampel/AccountUpgradesInfo'] = []`. All four options hold display text that
administrators type and members read, and the add-on reads no `config.php` key, so the empty list
is the whole declaration.

**The empty list is not a no-op.** An add-on that has not declared has each of its *text* options
reported only as set or not, so without this the add-on tells a monitoring consumer nothing about
how a forum is configured. Declaring moves those four from "set" to shown.

**It creates no dependency, and must not.** A code event that no installed add-on defines is never
fired, so on a forum without the add-on that defines this one the listener costs nothing. Nothing
goes in `addon.json`'s `require`, and the method stays an array of strings with nothing from
another add-on's namespace in it.

`tests/Feature/SecretsDeclarationTest.php` checks the declaration against `_output/options/`
rather than against itself, because it otherwise goes stale silently when an option is added or
renamed. The method carries no `declare(strict_types=1)` and no `void` return type: the add-on's
floor is XenForo 2.2, which enforces PHP 7.0, and `void` needs 7.1.

## The two template modifications are the whole implementation

Both target the public `account_upgrades` template, both are `str_replace`, both have execution
order 10, and both prepend their block to the match by ending the replacement with `$0`.

| modification | anchors on | renders |
|---|---|---|
| `…Above` | `<xf:if is="$available is not empty">` | before the available-upgrades list |
| `…Below` | `<xf:if is="$purchased is not empty">` | after it, before the purchased list |

**"Below" is implemented on the *purchased* block, not the available one, and that is the only
place it could go.** The available-upgrades list has no closing anchor unique enough to match, so
the next block down is the insertion point. The consequence is the thing to know before changing
either one: because each block is prepended *outside* the `<xf:if>` it anchors on, both info
blocks render even when the list they sit against is empty. That is intended — the "before" text
is the one a member with no upgrades available most needs to see.

**Both insertion points are inside the core template's own outer `<xf:contentcheck>`, so the
add-on suppresses XenForo's "there are currently no purchasable user upgrades" message.** Anything
the add-on renders satisfies that contentcheck, and the core's `<xf:else />` branch never fires — a
member with nothing available and nothing purchased sees the info blocks and no list. There is no
earlier anchor outside that contentcheck, so this is inherent rather than a choice, and it is why
the shipped default for the "before" body does not refer to a list below it.
`tests/Feature/AccountUpgradesPageTest.php` pins it.

**Each block is wrapped in `<xf:if contentcheck="true">` with the option inside `<xf:contentcheck>`,
so an empty option hides the block rather than rendering an empty bordered panel.** The title is
checked separately from the body, which is why a body with no title renders a headerless block
instead of an empty `block-header`. **The reverse does not hold: a title with no body renders
nothing at all**, because only the body's contentcheck belongs to the outer
conditional. The body is output `|raw` — the option explain phrases say
"You may use HTML", so the values are trusted admin input by design. Do not add escaping without
changing the phrases and the option documentation to match.

**Before editing either `find` string, confirm it still matches the target version's template
exactly once.** A modification that stops matching does not fail the upgrade — the feature
silently disappears:

```bash
php -r '
require("src/XF.php"); XF::start(__DIR__); XF::setupApp("XF\\Cli\\App");
$t = XF::db()->fetchOne(
    "SELECT template FROM xf_template WHERE title=? AND style_id=0 AND type=?",
    ["account_upgrades", "public"]
);
foreach (["<xf:if is=\"\$available is not empty\">", "<xf:if is=\"\$purchased is not empty\">"] as $f)
{
    printf("%d  %s\n", substr_count($t, $f), $f);
}'
```

Run it from the install root. After an import, check the modifications applied rather than
assuming — **`applied` must be at least 1 for both, and `status` proves nothing.** XenForo logs a
`str_replace` that matched nothing as `status = 'ok'` with an `apply_count` of 0:
`TemplateModificationRepository::applyTemplateModifications()` records the `substr_count()` as an
integer, and `Template::updateTemplateModificationLog()` writes any integer as `ok`.

```sql
SELECT modification_key, status, SUM(apply_count) AS applied FROM xf_template_modification_log l
  JOIN xf_template_modification m USING (modification_id)
 WHERE m.addon_id = 'Hampel/AccountUpgradesInfo'
 GROUP BY modification_key, status;
```

## The XF 2.3 guard in `Setup.php` is load-bearing

`postUpgrade()` calls `enqueuePostUpgradeCleanUp()` only when `\XF::$versionId >= 2030000`.
**That method does not exist on `XF\AddOn\AbstractSetup` before 2.3** — verified absent in 2.2.19,
added in 2.3 — so removing the guard makes every upgrade on an older install a fatal. Do not
"tidy" it away.

**Nor does the job it queues.** `XF\Job\FileCleanUp` arrived with the method: `src/XF/Job/` holds
no `FileCleanUp.php` in 2.2.19. So there is no older route to the same behaviour to be clever
about, and the guard is the whole answer rather than one of several. `SetupTest.php` imports that
class to assert the queueing, which is safe because `::class` on an imported name is resolved by
the compiler to a string and autoloads nothing.

## The floor is XenForo 2.2.0, and it is set by what can be tested

`addon.json` declares `"XF": ["2020070", "XenForo 2.2.0+"]` and no `php` key — the add-on uses
nothing above whatever PHP floor the XenForo version in use already enforces. The README states
the same thing; the two drift independently, because nothing validates one against the other.

**2.2 is a tested floor rather than an assumed one.** Both template modifications anchor on
`find` strings that occur exactly once in the `account_upgrades` template of 2.2 and of 2.3
alike, so the add-on's entire runtime surface works on both. Below 2.2 is untested, which is the
whole reason the floor is not lower.

**Raising the floor to 2.3 would make the guard above dead code**, and the two changes belong
together if that is ever done. Note the guard is not itself an argument for raising it: it is
house boilerplate that most of the sibling add-ons carry at floors from 2.0 upwards, not a shim
written because this add-on needed one.

## Never upgrade this add-on in place over the checkout

`postUpgrade()` queues `XF\Job\FileCleanUp`, and for an add-on that job's allowed deletion path is
the **whole add-on directory** — it removes every file `hashes.json` does not list. XenForo
protects only `_output/`, `hashes.json`, `addon.json`, `build.json`, `_files/` and `_releases/`,
so the README, CHANGELOG, LICENSE, `TESTING.md`, the dotfiles and `.git` are all eligible.

It is inert today only because `hashes.json` is generated into the build output and never exists
in a checkout, and the job returns immediately without one. Unzipping a release over the working
copy creates that file and arms it. Use a throwaway installation for any upgrade test;
`TESTING.md` has the detail.

## Commands

Run from this directory — it is the git repo. `cmd.php` resolves the install from its own
location rather than the working directory, so the relative path below works unchanged.

```bash
composer install                                                           # first time; vendor/ is gitignored
vendor/bin/phpunit                                                         # the whole suite
vendor/bin/phpunit --filter AccountUpgradesPageTest                        # one class
vendor/bin/phpstan analyse                                                 # static analysis, level max
vendor/bin/xenforo-phpstan generate --check                                # is the forum's set current?
vendor/bin/xenforo-phpstan generate                                        # after a XenForo upgrade
php ../../../../cmd.php xf-dev:import --addon=Hampel/AccountUpgradesInfo   # after editing _output/
php ../../../../cmd.php xf-addon:sync-json Hampel/AccountUpgradesInfo      # after editing addon.json
php ../../../../cmd.php xf:addon-upgrade Hampel/AccountUpgradesInfo        # apply a version bump
php ../../../../cmd.php xf-addon:build-release Hampel/AccountUpgradesInfo  # release only; writes _releases/
```

**Never run `xf-dev:export`.** It writes the database over `_output/`, and its `--addon` option is
optional — omitting it exports every add-on in the install, not just this one.
`xf-addon:build-release` calls the scoped `xf-addon:export` internally, which is safe.

## No `config` or `validate` commands, because core already reports the only failure

An add-on can declare a pair of self-check CLI commands — one that prints what the forum is
configured to do, and one that proves it can. This add-on has neither, and the reason is written
down because the obvious argument points the other way: it does depend on something it does not
own — two literal strings in a template belonging to XenForo — so it looks like a candidate for a
`validate` command that checks they still match.

**XenForo already reports that, on screen, without being asked.**
`XF\Entity\TemplateModification::getLogSummary()` counts a log row as `not_found` on exactly
`status === 'ok' && apply_count === 0`, which is the condition such a command would have tested,
and the administrator's template-modification list renders that summary. A command here would
restate it.

The three questions that decide whether an add-on needs the pair are all "no" for this one. No
setting is opaque — the four options hold display text, so the value *is* the output. Nothing runs
unattended. And `config` would report nothing the control panel does not already assemble on one
options page. Against that sits a real cost: every command an add-on declares appears in
`cmd.php list` on every forum that installs it, for good.

Decided 2026-10-10. An option that holds a credential, anything that runs on a schedule, or a
dependency on a service outside the forum would each reopen it.

## Tests read the forum, not the working copy

The suite is Feature tests only, on `hampel/xenforo-test-framework` `^5.33`. **That constraint is a
floor set by what the suite calls**, not by what was current —
`assertAddOnTemplateModificationsApplyTimes()` and `assertAddOnTemplateModificationFindsMatch()`
arrived in 5.33. Raise it when the suite starts calling something newer, and not otherwise:
`composer update` already takes every minor it admits.

**Most of it reads the installed add-on out of the forum** — the modification log, the compiled
`account_upgrades` template, the option values, the phrase table. **An edit to `_output/` is
invisible to those until `xf-dev:import` has run**, so a red run after editing a modification or a
phrase usually means the import was skipped rather than that the change is wrong.

**Three tests read the shipped artifacts instead, deliberately**, because that caveat is itself a
failure nothing else catches: the `find`-string match and the check that the forum holds every
modification the add-on ships read `_output/template_modifications/`, and the secrets declaration
reads `_output/options/`. A modification committed without an import passes every forum-reading
test against the version the forum still holds.

**`TESTING.md`'s *Automated* section lists what the suite covers**, and every item was proved by
breaking the behaviour it names and watching the test fail. Prove a new test the same way before
trusting its green run — and **mutate a template with a whitelisted method name** (`can…`): XenForo
rejects any other method call at compile time, so the modification never applies, the test stays
green for want of a mutation, and only the import's output says so.

`$addonsToLoad` names only this add-on, so another add-on's vendored PHPUnit cannot resolve in its
place. It does not remove other add-ons' template modifications, which are compiled into the
database rather than applied at render time — which is why the template-error assertion is scoped
to `account_upgrades`: a modification another add-on makes to the core `account_wrapper` this
template wraps is not this add-on's to answer for. `composer.json` holds dev dependencies only, so
`vendor/` must never reach a release.

`TESTING.md` carries what the suite cannot do.

## Static analysis runs at level max, and nothing is ignored

`hampel/xenforo-phpstan` gives PHPStan what it needs to see a XenForo install without analysing it,
and that package's README is the reference. `phpstan.neon` here supplies only the level, the paths
and the generated directories that are not source. **There is no baseline and no `ignoreErrors`,
so a new report is a new problem.** Level max is attainable because the add-on is a handful of PHP
files with no class extension and no entity of its own — a fact about this add-on, not a house
default.

**`vendor/bin/xenforo-phpstan generate` writes into the forum's `internal_data/phpstan/`, not into
this repository**, and every add-on analysed against that forum reads the same files. Ask
`generate --check` first: it writes nothing, and exits 0 when the set is current or 1 naming why it
is missing or stale. Re-run `generate` after a XenForo upgrade, or after a class extension is added
or removed anywhere on the forum.

**`crates.neon` is deliberately not included.** It accepts any property name on entity columns,
options and route parameters — a mistyped column as readily as a correct one — and nothing here
needs it.

**Type the suite's own helpers.** PHPStan does not infer a return type across a method boundary,
so an untyped helper returns `mixed` to every caller; at adoption one untyped `renderPage()`
accounted for 64 of 131 reports. **Where a value genuinely arrives untyped** — the redaction
event's `array<string, mixed>`, `json_decode()` — **narrow it with an assertion, not a cast**:
`assertIsArray()`, `assertIsString()` and `assertContainsOnlyString()` check and narrow at once, so
a malformed artifact is reported rather than read as an empty one. `SetupTest`'s
`assertInstanceOf()` consumes the `null` that `AddOn\Manager::getById()` can return; it asserts a
real precondition and is not always-true.

`Setup.php`'s three empty steps `return null` explicitly. `AbstractSetup` documents them as
`@return null|StepResult`, and an explicit `null` tells a reader the step is empty on purpose.

## Versioning

Open the development cycle **before** starting work on a released version, not at the end: bump to
an alpha of the next patch (`1.0.1` → `1000211` / `"1.0.2a1"`) and commit that on its own. Until
that lands, the tree and any zip built from it still claim to be the last release, and XenForo
derives the release zip filename from the version string alone and renames over whatever is
already there — so a trial build silently destroys the shipped artifact.

Take the smallest bump that is not the last release. Raising later is free; lowering is not
available at all, because `AddOn::canUpgrade()` requires a strictly greater `version_id` and
`AddOnController::actionUpgrade()` has no override.

`CHANGELOG.md` is stable-only, so an open alpha correctly has no entry in it.

## Build and release

`build.json` has no `additional_files` and no Composer step. Its `exec` lines delete the dev-only
files from the upload tree — Composer's files, `phpunit.xml`, `tests/`, the whole `vendor/`, both
PHPUnit cache names and the four dev-only root documents — and then move every remaining root
`*.md` out of it, so `README`, `CHANGELOG` and `LICENSE` land at the zip root rather than being
uploaded to a user's server.

**The `rm` must stay before the `mv`.** Placed after it, it silently does nothing: the file has
already been renamed into `_build/`, and the error that would have told you is discarded anyway —
`exec` steps **cannot fail a build**, because `ReleaseBuilderService::execCmds()` ends in
`passthru()` and throws the exit status away.

**Every `rm` names a dev-only file or directory, and anything else added to the repository root
that should not reach a user's server has to be added to one of them.** The `mv` after them sweeps
up every remaining root `*.md` regardless, and the release builder copies `vendor/` along with
everything else — a build run from a checkout with the dev dependencies installed carries all of
PHPUnit into the zip unless `rm` removes it.

So verify the artifact, never the exit code:

```bash
unzip -Z1 _releases/<file>.zip | grep -v '^upload/'          # zip root: README, CHANGELOG, LICENSE
unzip -Z1 _releases/<file>.zip | grep -iE 'claude|testing|vendor/|tests/|phpunit|phpstan|composer\.'  # want no output
unzip -Z1 _releases/<file>.zip | grep -E '(^|/)\.[^/]*'      # dotfiles; want no output
```

Use `unzip -Z1`, not `unzip -l | awk '{print $4}'` — the awk form truncates any path containing a
space and carries the header lines through.

## Three exclusion surfaces, and none of them covers the others

Dev-only files must be kept out of three different places by three different mechanisms. Getting
one does not get you the others:

| kept out of | by | covers |
|---|---|---|
| the git repository | `.gitignore` | `_data`, `_releases/`, `vendor/`, both PHPUnit caches, `CLAUDE.local.md`, `HISTORY.local.md` |
| `git archive` output (GitHub "Download ZIP") | `.gitattributes` `export-ignore` | both Claude files, `HISTORY.local.md`, `TESTING.md`, `tests/`, `phpunit.xml`, `phpstan.neon`, Composer's files, the dotfiles |
| the XenForo release zip | `build.json` `exec` `rm` | both Claude files, `HISTORY.local.md`, `TESTING.md`, `tests/`, `phpunit.xml`, `phpstan.neon`, Composer's files, `vendor/`, both PHPUnit caches |

The release builder walks the filesystem and knows nothing about git, so gitignoring a file does
**not** keep it out of the zip. A new dev-only file needs adding to all three.

`CLAUDE.md` is committed and public; anything specific to one machine — install paths, host names,
release history, working notes — belongs in the gitignored `CLAUDE.local.md` instead, and the
running record of how things got that way in `HISTORY.local.md`, which is gitignored too and is not
loaded into a session automatically. The test is whether it would still be true for someone who
cloned only this repository.

## Line endings

`.gitattributes` pins `* text=auto eol=lf`, so the repository stores LF and checks LF back out on
every platform. If you commit from a Windows client with `core.autocrlf=true`, that setting can no
longer rewrite this tree.

**If a whole-tree diff appears anyway — every tracked file modified, identical content on both
sides — do not commit it, and do not `git add -A`.** The two artefacts look the same in
`git status` and have opposite fixes; `git diff --summary` tells them apart. Prove nothing real is
buried before discarding anything:

```bash
git diff --summary                  # non-empty => executable-bit artefact, a different fix
git diff --ignore-cr-at-eol --stat  # empty => pure CRLF, nothing real in it
for f in $(git diff --name-only); do
  [ -f "$f" ] || continue
  a=$(tr -d '\r' < "$f" | git hash-object --stdin); b=$(git rev-parse ":$f")
  [ "$a" != "$b" ] && echo "REAL CHANGE: $f"
done
```

Then restore the worktree and run `git add --renormalize .`. It stages nothing while the committed
blobs are clean, which they are — every blob in `HEAD` has been CR-free since the add-on was
written. Anything it does stage is a committed-CRLF file being corrected, and belongs in a commit
of its own so the diff is never mistaken for content later.
