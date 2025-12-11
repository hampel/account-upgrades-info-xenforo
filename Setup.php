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
        if (\XF::$versionId >= 2030000) { // XF 2.3+
            $this->enqueuePostUpgradeCleanUp();
        }
    }

    public function uninstall(array $stepParams = [])
    {
        // nothing to do
    }
}