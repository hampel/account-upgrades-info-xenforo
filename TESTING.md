# Testing

What this add-on touches, what breaks quietly, and which checks a session can settle on its own.

There is no test suite, deliberately. The add-on has no logic to unit test — its entire behaviour
is XenForo substituting four option values into two blocks of template markup — and the failure
that actually happens needs a live install's template table to detect, which PHPUnit cannot reach.
The `Automated` checks below are that coverage.

## Surfaces

| Artifact | Detail |
|---|---|
| template modifications | two, both on the public `account_upgrades` template |
| options | four, all `string`, in one option group |
| phrases | ten — four option titles, four explains, the group title and its description |
| `Setup.php` | no install, upgrade or uninstall steps; `postUpgrade()` only |

No class extensions, no code event listeners, no templates of its own, no routes, no permissions,
no entities, no schema, no JavaScript and no Composer dependencies.

Both modifications are `str_replace` at execution order 10, and both end their replacement with
`$0`, so each block is **prepended** to the text it matches:

| key | anchors on | renders |
|---|---|---|
| `…Above` | `<xf:if is="$available is not empty">` | before the available-upgrades list |
| `…Below` | `<xf:if is="$purchased is not empty">` | after it, before the purchased list |

## Fragile points

**The `find` strings are the whole risk.** A XenForo upgrade that rewords `account_upgrades`
leaves the modification unable to match, and the failure is silent: the upgrade succeeds, the
template modification log records the miss, and the info blocks simply stop appearing. Nothing
errors and no user is told. Check the strings after every XenForo upgrade, not only after a change
to this add-on.

**"Below" is implemented on the purchased block, and that is the only place it could go.** The
available-upgrades list has no closing anchor unique enough to match, so the next block down is the
insertion point. Do not "correct" it to something that looks more like the name.

**Both blocks render outside the `<xf:if>` they anchor on**, so each appears even when the list
beside it is empty. That is intended — the "before" text is what a member with no upgrades
available most needs to read — and it is easy to mistake for a bug when testing on an account that
has no upgrades.

**An empty option hides its block rather than rendering an empty panel.** Each block is wrapped in
`<xf:if contentcheck="true">` with the option inside `<xf:contentcheck>`. The title is checked
separately from the body, so a body with no title deliberately renders a block with no header.

**The body is output `|raw`.** The option explain phrases promise HTML, so the values are trusted
administrator input by design. Escaping it would be a behaviour change and needs the phrases and
the resource description changed to match.

**`postUpgrade()`'s version guard is load-bearing.** `enqueuePostUpgradeCleanUp()` does not exist
on `XF\AddOn\AbstractSetup` before XenForo 2.3, so removing the `\XF::$versionId >= 2030000` test
makes every upgrade on a 2.2 install a fatal.

## Automated

Run from the installation root unless stated otherwise.

**Do the modifications still match the target template, exactly once each?** This is the check that
catches the silent failure above. Run it against every XenForo version the add-on claims to
support, not only the newest.

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

Both lines must report `1`. A `0` means the modification can no longer apply; a number above `1`
means it will apply more than once.

**Did they actually apply?** After any import, upgrade or XenForo upgrade:

```sql
SELECT modification_key, status FROM xf_template_modification_log l
  JOIN xf_template_modification m USING (modification_id)
 WHERE m.addon_id = 'Hampel/AccountUpgradesInfo';
```

Both rows must read `ok`.

**Does the result compile?** A modification can apply and still produce invalid template syntax.
The compiled copies under `internal_data/code_cache/templates/*/*/public/account_upgrades.php`
should each contain the add-on's option references — if the compile failed, they will not.

**Do the artifacts and their metadata agree?** From the add-on directory, a scoped import must
succeed and leave the working tree unchanged:

```bash
php ../../../../cmd.php xf-dev:import --addon=Hampel/AccountUpgradesInfo
git status --short          # want no output
```

**Does the release zip exclude the development files?** `build.json` removes them before the `mv`
that promotes the remaining root `*.md` to the zip root, and an `exec` step cannot fail a build —
`ReleaseBuilderService::execCmds()` discards the exit status — so verify the artifact, never the
exit code:

```bash
unzip -Z1 _releases/<file>.zip | grep -v '^upload/'          # want README, CHANGELOG, LICENSE
unzip -Z1 _releases/<file>.zip | grep -iE 'claude|testing'   # want no output
unzip -Z1 _releases/<file>.zip | grep -E '(^|/)\.[^/]*'      # dotfiles; want no output
```

Use `unzip -Z1` rather than `unzip -l` piped through `awk` — the `awk` form truncates any path
containing a space and carries the header lines through.

## Needs a human

**Whether the blocks actually appear on the page, and read correctly.** The checks above prove the
modification applied and compiled; none of them renders HTML. Visit the route `account/upgrades`
as a logged-in member and confirm the "before" block sits above the available-upgrades list and
the "after" block below it.

**Whether the phrases resolved.** A missing phrase renders as its raw key, which is visible on the
page and invisible to every check above. Look at the options page in the control panel as well as
the front end.

**The empty-option and empty-list combinations.** Four states worth seeing rather than reasoning
about: both options set, only the title set, only the body set, and neither — against an account
with upgrades available and one without.

**The upgrade path from a published release.** Install the previous release's zip, then upgrade to
the new one. Note a standing XenForo limitation while doing it: `ExtractorService::copyFiles()`
uses the changeset only as a skip filter and never acts on its `delete` entries, so **any file the
new version removed will still be on disk afterwards**. A fresh install and an upgraded install
are different filesystems, and nothing offline reveals the difference.

**The support URLs in `addon.json`.** The three xenforo.com links return 403 to `curl`, which is
bot protection rather than a dead link, so they cannot be checked from a script. Open them in a
browser.
