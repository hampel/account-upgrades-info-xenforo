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

	private function addOnSetup()
	{
		$addOn = $this->app()->addOnManager()->getById('Hampel/AccountUpgradesInfo');

		return new Setup($addOn, $this->app());
	}
}
