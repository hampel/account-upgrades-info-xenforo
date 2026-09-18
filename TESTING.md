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

**The body gates its block; the title does not.** Setting only a title — and no body — renders
nothing at all, silently. That is documented on the body options' explain text ("Leave blank to
hide the block") and not on the titles', which is the more surprising direction.

**The add-on suppresses XenForo's "there are currently no purchasable user upgrades" message.**
Both modifications insert *inside* the core template's outer `<xf:contentcheck>`, so anything they
render satisfies it and the `<xf:else />` branch never fires. A member with nothing available and
nothing purchased therefore sees the info blocks and no list, with no indication that there is
nothing to choose. This is inherent to where the modifications can anchor — there is no earlier
insertion point outside that contentcheck — and it is why the shipped default for the "before"
body does not refer to a list below it.

**The body is output `|raw`.** The option explain phrases promise HTML, so the values are trusted
administrator input by design. Escaping it would be a behaviour change and needs the phrases and
the resource description changed to match.

**`postUpgrade()`'s version guard is load-bearing.** `enqueuePostUpgradeCleanUp()` does not exist
on `XF\AddOn\AbstractSetup` before XenForo 2.3, so removing the `\XF::$versionId >= 2030000` test
makes every upgrade on a 2.2 install a fatal.

**Never upgrade this add-on in place over a development checkout.** `enqueuePostUpgradeCleanUp()`
queues `XF\Job\FileCleanUp`, whose allowed deletion path for an add-on is the *entire* add-on
directory: it deletes every file there that `hashes.json` does not list. XenForo protects
`_output/`, `hashes.json`, `addon.json`, `build.json`, `_files/` and `_releases/` — and nothing
else, so `README.md`, `CHANGELOG.md`, `LICENSE.md`, `TESTING.md`, the dotfiles and `.git` itself
are all eligible. `getRecursiveDirectoryIterator` passes `SKIP_DOTS`, which skips `.` and `..`,
not dot-directories.

What prevents this in practice is incidental rather than designed: `hashes.json` is generated into
the build output and never exists in a checkout, and the job returns immediately when it is
missing. On a user's installed copy the manifest is present and none of those files are, so the
cleanup does its intended work safely.

**The hazard is the gap between those two states, and the obvious way to test an upgrade walks
into it.** `ExtractorService::copyFiles()` writes a release zip over the target file by file,
which puts `hashes.json` into the directory. Do the upgrade test on a throwaway installation, not
on a working copy.

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

**Do the option combinations render as intended?** The page can be rendered in-process, without a
browser and without writing anything, which covers the combination matrix that would otherwise be
a manual pass. The `addDefaultParam("xf", ...)` line is the non-obvious part — without it
`$xf.options` resolves to null, every `contentcheck` sees empty content, and the blocks silently
do not render, which looks exactly like a broken add-on.

```bash
php -r '
require("src/XF.php"); XF::start(__DIR__);
$app = XF::setupApp("XF\\Pub\\App");
$app->templater()->setStyle($app->style(1) ?: $app->style(0));
XF::setLanguage($app->language(0));
$app->templater()->addDefaultParam("xf", $app->getGlobalTemplateData(null));
$o = XF::options(); $P = "hampelAccountUpgradesInfo";
$params = [
    "available" => $app->em()->getBasicCollection([]),
    "purchased" => $app->em()->getBasicCollection([]),
    "canPurchase" => true,
];
foreach ([["", "", "", ""], ["About upgrades", "Some text", "", ""], ["", "", "Refund policy", ""]] as $set)
{
    [$o->{$P."TitleBefore"}, $o->{$P."BodyBefore"}, $o->{$P."TitleAfter"}, $o->{$P."BodyAfter"}] = $set;
    $h = $app->templater()->renderTemplate("public:account_upgrades", $params);
    printf("blocks=%d  no-upgrades-message=%d\n",
        substr_count($h, "class=\"block\""), substr_count($h, "blockMessage"));
}'
```

Expected, against an empty upgrade list: `0/1`, then `1/0`, then `0/1`. The middle row is the
message-suppression characteristic above; the last is a title with no body rendering nothing.

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

**The case with upgrades actually available.** Everything above is exercised against an empty
upgrade list, because that is what an in-process render can build without creating forum data. A
forum that has purchasable upgrades should be checked with a real one defined, confirming the
"before" block sits above the list and the "after" block below it.

**The upgrade path from a published release** — on a throwaway installation, for the reason in
*Fragile points*, never over a working copy. Install the previous release's zip, then upgrade to
the new one.

Two XenForo behaviours to watch while doing it. `ExtractorService::copyFiles()` uses the changeset
only as a skip filter and never acts on its `delete` entries, so **any file the new version
removed will still be on disk afterwards** — a fresh install and an upgraded install are different
filesystems, and nothing offline reveals the difference. And on an installation with development
mode enabled the upgrade imports the working copy's `_output/` instead of the zip's data, silently;
the tell is `All data imported.` in the output where `Importing add-on data` belongs.

**When this test can be skipped, and why it can be here.** `Setup.php` declares no version-gated
`upgrade<versionId>Step<n>()` methods at all, so the install-works-but-upgrade-fails failure
cannot occur — that is structural rather than untested. And nothing has ever been removed from
this add-on's shipped file set, so `FileCleanUp` has nothing to act on either. Both statements
need rechecking the moment a gated step or a deleted file appears.

**The support URLs in `addon.json`.** The three xenforo.com links return 403 to `curl`, which is
bot protection rather than a dead link, so they cannot be checked from a script. Open them in a
browser.
