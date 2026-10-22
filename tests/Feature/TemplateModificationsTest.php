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
	 * whatever the add-on registers, which is the assertion that keeps working.
	 */
	public function test_every_modification_this_add_on_registers_applies()
	{
		$applied = $this->assertAddOnTemplateModificationsApply();

		$this->assertSame(
			['hampelAccountUpgradesInfoAbove', 'hampelAccountUpgradesInfoBelow'],
			array_keys($applied),
			'a modification was added or removed - give it a test, or take one away'
		);
	}

	/**
	 * Everything above reads the forum, so an artifact committed without an import is invisible
	 * to it: the forum still holds the previous version and every assertion passes. This is the
	 * other half, and it is the one a commit can break on its own.
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

	/**
	 * The check `TESTING.md` asks a human to run, which is the add-on's sharpest edge: a find
	 * string that matches nothing silently drops the block, and one that matches twice renders
	 * it twice. Reading the shipped artifacts rather than the forum's rows means this fails on
	 * an edit to `_output/` before the import that would make it real.
	 */
	public function test_each_find_string_matches_the_target_template_exactly_once()
	{
		$counts = [];

		foreach ($this->shippedModifications() AS $key => $modification)
		{
			$template = $this->app()->db()->fetchOne(
				'SELECT template FROM xf_template WHERE title = ? AND type = ? AND style_id = 0',
				[$modification['template'], 'public']
			);

			$this->assertNotNull(
				$template,
				"{$key} modifies public:{$modification['template']}, which this forum does not have"
			);

			$counts[$key] = substr_count($template, $modification['find']);
		}

		$this->assertSame(
			[
				'hampelAccountUpgradesInfoAbove' => 1,
				'hampelAccountUpgradesInfoBelow' => 1,
			],
			$counts,
			'0 means the modification can no longer apply; above 1 means it applies more than once'
		);
	}

	/** Guards the two tests above: both assert nothing if the glob finds no artifacts. */
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
