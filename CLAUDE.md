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

**Four admin options and two template modifications. There is no runtime PHP at all.** The whole
feature is rendered by XenForo's template modification system from option values; nothing in this
repository executes when a page is served.

The entire surface:

- `_output/options/` — `hampelAccountUpgradesInfo{Title,Body}{Before,After}`, all `string`,
  `Title*` a one-line textbox and `Body*` a `rows=3` textbox.
- `_output/option_groups/hampelAccountUpgradesInfo.json` — the group they live in.
- `_output/template_modifications/public/hampelAccountUpgradesInfo{Above,Below}.json` — the two
  `str_replace` modifications against the `account_upgrades` template.
- `_output/phrases/` — the option titles, explains, and the group title and description.
- `Setup.php` — no install, upgrade or uninstall steps; `postUpgrade()` only calls
  `enqueuePostUpgradeCleanUp()`, guarded to XF 2.3+.

No classes, entities, repositories, routes, permissions, JS or LESS. Adding any of those means
creating `_output/<type>/` with `xf-make:*` and importing.

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

**Each block is wrapped in `<xf:if contentcheck="true">` with the option inside `<xf:contentcheck>`,
so an empty option hides the block rather than rendering an empty bordered panel.** The title is
checked separately from the body, which is why a body with no title renders a headerless block
instead of an empty `block-header`. The body is output `|raw` — the option explain phrases say
"You may use HTML", so the values are trusted admin input by design. Do not add escaping without
changing the phrases and the option documentation to match.

**Before editing either `find` string, confirm it still matches the target version's template
exactly once.** A modification that stops matching does not fail the upgrade — it logs and the
feature silently disappears:

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
assuming — `status` should be `ok` for both:

```sql
SELECT modification_key, status FROM xf_template_modification_log l
  JOIN xf_template_modification m USING (modification_id)
 WHERE m.addon_id = 'Hampel/AccountUpgradesInfo';
```

## The XF 2.3 guard in `Setup.php` is load-bearing

`postUpgrade()` calls `enqueuePostUpgradeCleanUp()` only when `\XF::$versionId >= 2030000`.
**That method does not exist on `XF\AddOn\AbstractSetup` before 2.3** — verified absent in 2.2.19,
added in 2.3 — so removing the guard makes every upgrade on an older install a fatal. Do not
"tidy" it away.

## No XenForo version floor is declared

`addon.json` has `"require": []` — no `XF` entry, no `php` entry. So the add-on installs on any
XF 2.x the install itself allows, which is consistent with the 2.3 guard above but means nothing
declares what it is actually tested against. **If a floor is ever set, it has to be set in
`addon.json` and the README together**, and setting one at 2.3 or above would make the guard dead
code that should go with it.

## Commands

Run from this directory — it is the git repo. `cmd.php` resolves the install from its own
location rather than the working directory, so the relative path below works unchanged.

```bash
php ../../../../cmd.php xf-dev:import --addon=Hampel/AccountUpgradesInfo   # after editing _output/
php ../../../../cmd.php xf-addon:sync-json Hampel/AccountUpgradesInfo      # after editing addon.json
php ../../../../cmd.php xf:addon-upgrade Hampel/AccountUpgradesInfo        # apply a version bump
php ../../../../cmd.php xf-addon:build-release Hampel/AccountUpgradesInfo  # release only; writes _releases/
```

**Never run `xf-dev:export`.** It writes the database over `_output/`, and its `--addon` option is
optional — omitting it exports every add-on in the install, not just this one.
`xf-addon:build-release` calls the scoped `xf-addon:export` internally, which is safe.

There is no test suite and no `composer.json`: nothing here is unit-testable, because there is no
code to test. Verification is the two checks above plus loading `/account/upgrades` and confirming
both blocks render. `TESTING.md` carries the manual checks.

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

`build.json` has no `additional_files` and no Composer step — two `exec` lines that delete the
dev-only files from the upload tree and then move every remaining root `*.md` out of it, so
`README`, `CHANGELOG` and `LICENSE` land at the zip root rather than being uploaded to a user's
server.

**The `rm` must stay before the `mv`.** Placed after it, it silently does nothing: the file has
already been renamed into `_build/`, and the error that would have told you is discarded anyway —
`exec` steps **cannot fail a build**, because `ReleaseBuilderService::execCmds()` ends in
`passthru()` and throws the exit status away.

**The `rm` line names all three dev-only files — `TESTING.md`, `CLAUDE.md`, `CLAUDE.local.md`.**
Anything else added to the repository root that should not reach a user's server has to be added
there too; the `mv` below it sweeps up every remaining root `*.md` regardless.

So verify the artifact, never the exit code:

```bash
unzip -Z1 _releases/<file>.zip | grep -v '^upload/'          # zip root: README, CHANGELOG, LICENSE
unzip -Z1 _releases/<file>.zip | grep -iE 'claude|testing'   # want no output
unzip -Z1 _releases/<file>.zip | grep -E '(^|/)\.[^/]*'      # dotfiles; want no output
```

Use `unzip -Z1`, not `unzip -l | awk '{print $4}'` — the awk form truncates any path containing a
space and carries the header lines through.

## Three exclusion surfaces, and none of them covers the others

Dev-only files must be kept out of three different places by three different mechanisms. Getting
one does not get you the others:

| kept out of | by | covers |
|---|---|---|
| the git repository | `.gitignore` | `_data`, `_releases/`, `CLAUDE.local.md` |
| `git archive` output (GitHub "Download ZIP") | `.gitattributes` `export-ignore` | both Claude files, `TESTING.md`, `.gitattributes`, `.gitignore` |
| the XenForo release zip | `build.json` `exec` `rm` | both Claude files, `TESTING.md` |

The release builder walks the filesystem and knows nothing about git, so gitignoring a file does
**not** keep it out of the zip. A new dev-only file needs adding to all three.

`CLAUDE.md` is committed and public; anything specific to one machine — install paths, host names,
release history, working notes — belongs in the gitignored `CLAUDE.local.md` instead. The test is
whether it would still be true for someone who cloned only this repository.

## Line endings

`.gitattributes` pins `* text=auto eol=lf`, so the repository stores LF and checks LF back out on
every platform. If you commit from a Windows client with `core.autocrlf=true`, that setting can no
longer rewrite this tree.

**If a whole-tree diff appears anyway — every tracked file modified, identical content on both
sides — do not commit it, and do not `git add -A`.** The two artefacts look the same in `git status` and
have opposite fixes; `git diff --summary` tells them apart. Prove nothing real is buried before
discarding anything:

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
