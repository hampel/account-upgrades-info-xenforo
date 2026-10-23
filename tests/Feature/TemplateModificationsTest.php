<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The failure that actually happens to this add-on: a XenForo release rewords the account_upgrades
 * template, a find string stops matching, and the info blocks disappear. Nothing errors, and the
 * modification log still reads status 'ok' - only the apply count shows it, which is what these
 * assertions read. Run the suite after every XenForo upgrade, not only after a change here.
 */
class TemplateModificationsTest extends TestCase
{
	public function test_the_block_before_the_available_list_applies()
	{
		$this->assertTemplateModificationApplied('hampelAccountUpgradesInfoAbove');
	}

	public function test_the_block_after_the_available_list_applies()
	{
		$this->assertTemplateModificationApplied('hampelAccountUpgradesInfoBelow');
	}

	/**
	 * The two above name their modification, so neither covers one added later. This covers
	 * whatever the add-on registers, and it asserts exactly once rather than at least once: a
	 * find that matches twice inserts the block twice and still reads as applied. The count is
	 * checked per log row, so a forum carrying several styles is not read as several matches.
	 */
	public function test_every_modification_this_add_on_registers_applies_once()
	{
		$applied = $this->assertAddOnTemplateModificationsApplyTimes(1);

		$this->assertSame(
			['hampelAccountUpgradesInfoAbove', 'hampelAccountUpgradesInfoBelow'],
			array_keys($applied),
			'a modification was added or removed - give it a test, or take one away'
		);
	}

	/**
	 * The check `TESTING.md` used to ask a human to run, and the add-on's sharpest edge: a find
	 * string that matches nothing silently drops the block, and one that matches twice renders it
	 * twice. This reads the shipped artifacts and the master template text rather than the forum's
	 * rows, so it fails on an edit to `_output/` before the import that would make it real, and it
	 * refuses rather than passes where there is no artifact to read.
	 */
	public function test_each_find_string_matches_the_target_template_exactly_once()
	{
		$this->assertAddOnTemplateModificationFindsMatch();
	}

	/**
	 * Everything above reads the forum or the working copy, never the two against each other, so
	 * an artifact committed without an import is invisible to all of them: the forum still holds
	 * the previous version and every assertion passes. This is the other half, and it is the one
	 * a commit can break on its own.
	 */
	public function test_the_forum_holds_every_modification_the_add_on_ships()
	{
		$shipped = array_keys($this->shippedModifications());

		$installed = $this->app()->db()->fetchAllColumn(
			"SELECT modification_key FROM xf_template_modification
			  WHERE addon_id = 'Hampel/AccountUpgradesInfo' ORDER BY modification_key"
		);

		sort($shipped);

		$this->assertSame($shipped, $installed, 'run xf-dev:import - the forum is out of date');
	}

	/** Guards the test above: it asserts nothing if the glob finds no artifacts. */
	public function test_it_reads_this_add_on_s_modification_artifacts()
	{
		$this->assertCount(2, $this->shippedModifications());
	}

	/** @return array<string, array> modification key => its artifact, as committed */
	private function shippedModifications()
	{
		$modifications = [];
		$pattern = dirname(__DIR__, 2) . '/_output/template_modifications/*/*.json';

		foreach (glob($pattern) ?: [] AS $file)
		{
			if (basename($file) == '_metadata.json')
			{
				continue;
			}

			$modifications[basename($file, '.json')] = json_decode(file_get_contents($file), true);
		}

		return $modifications;
	}
}
