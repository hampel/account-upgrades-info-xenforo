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
}
