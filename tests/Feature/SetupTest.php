<?php

namespace Tests\Feature;

use Hampel\AccountUpgradesInfo\Setup;
use Tests\TestCase;
use XF\Job\FileCleanUp;

/**
 * The job is only ever queued into the framework's fake manager here, never run. Running it
 * against a development checkout is the one thing never to do: see TESTING.md.
 */
class SetupTest extends TestCase
{
	public function test_post_upgrade_queues_the_file_clean_up_on_xenforo_2_3()
	{
		$this->fakesJobs();

		$stateChanges = [];
		$this->addOnSetup()->postUpgrade(1000070, $stateChanges);

		$this->assertJobQueued(FileCleanUp::class, function (array $job)
		{
			return $job['execute_data']['addon_id'] === 'Hampel/AccountUpgradesInfo';
		});
	}

	/**
	 * This install is 2.3, so the method exists either way and this cannot prove that 2.2 does
	 * not fatal. What it does prove is that the guard is still there: delete it and the job is
	 * queued regardless of version.
	 */
	public function test_post_upgrade_queues_nothing_before_xenforo_2_3()
	{
		$this->fakesJobs();

		$versionId = \XF::$versionId;
		\XF::$versionId = 2021970;

		try
		{
			$stateChanges = [];
			$this->addOnSetup()->postUpgrade(1000070, $stateChanges);
		}
		finally
		{
			\XF::$versionId = $versionId;
		}

		$this->assertNoJobsQueued();
	}

	/**
	 * `TESTING.md` skips the upgrade test on the grounds that this class declares no version
	 * gated step at all, so an install that works and an upgrade that fails cannot come apart.
	 * That is an assumption about the file rather than about XenForo, and it stops being true
	 * the moment someone adds a step - at which point the skip has to be revisited rather than
	 * inherited.
	 */
	public function test_it_declares_no_version_gated_steps()
	{
		$steps = [];

		foreach (get_class_methods(Setup::class) AS $method)
		{
			if (preg_match('/Step\d+$/', $method))
			{
				$steps[] = $method;
			}
		}

		$this->assertSame([], $steps, 'the upgrade test can no longer be skipped - see TESTING.md');
	}

	/** Guards the test above: the pattern proves nothing if it is reading the wrong class. */
	public function test_it_reads_this_add_on_s_setup_class()
	{
		$this->assertContains('postUpgrade', get_class_methods(Setup::class));
	}

	private function addOnSetup()
	{
		$addOn = $this->app()->addOnManager()->getById('Hampel/AccountUpgradesInfo');

		return new Setup($addOn, $this->app());
	}
}
