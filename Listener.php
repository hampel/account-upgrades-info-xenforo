<?php namespace Hampel\AccountUpgradesInfo;

class Listener
{
    /**
     * Declares this add-on's secrets to Hampel/AdminApi, so that the rest of its settings can be
     * reported rather than withheld. This add-on has none: all four of its options hold display
     * text, and it reads no config.php key. The empty entry is the declaration.
     *
     * No dependency on that add-on is created or wanted - a code event no installed add-on
     * defines is never fired, so this costs nothing on a forum without it.
     *
     * @param array<string, mixed> $declarations keyed by add-on id
     *
     * @return void
     */
    public static function adminApiRedaction(array &$declarations)
    {
        $declarations['Hampel/AccountUpgradesInfo'] = [];
    }
}
