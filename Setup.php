<?php namespace Hampel\AccountUpgradesInfo;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
    /**
     * @param array<string, mixed> $stepParams
     *
     * @return null no step to run, rather than a step that did nothing
     */
    public function install(array $stepParams = [])
    {
        return null;
    }

    /**
     * @param array<string, mixed> $stepParams
     *
     * @return null
     */
    public function upgrade(array $stepParams = [])
    {
        return null;
    }

    /**
     * @param int $previousVersion
     * @param array<string, mixed> $stateChanges
     *
     * @return void
     */
    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if (\XF::$versionId >= 2030000) { // XF 2.3+
            $this->enqueuePostUpgradeCleanUp();
        }
    }

    /**
     * @param array<string, mixed> $stepParams
     *
     * @return null
     */
    public function uninstall(array $stepParams = [])
    {
        return null;
    }
}