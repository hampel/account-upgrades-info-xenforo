<?php namespace Hampel\AccountUpgradesInfo;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        // nothing to do
    }

    public function upgrade(array $stepParams = [])
    {
        // nothing to do
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        $this->enqueuePostUpgradeCleanUp();
    }

    public function uninstall(array $stepParams = [])
    {
        // nothing to do
    }
}