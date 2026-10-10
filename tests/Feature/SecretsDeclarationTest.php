<?php

namespace Tests\Feature;

use Hampel\AccountUpgradesInfo\Listener;
use Tests\TestCase;

/**
 * The declaration goes stale silently when an option is added or renamed, so it is checked
 * against _output/options/ rather than against itself. Neither assertion needs Hampel/AdminApi
 * installed - the listener is an array of strings and nothing else.
 *
 * Every test asserts once, outside any loop: this add-on declares no secrets, so a test whose
 * only assertions sat inside a loop over that empty list would asserts nothing and be reported
 * as risky rather than as passing.
 */
class SecretsDeclarationTest extends TestCase
{
    /** Options that read like a credential and are not one, with the reason. */
    private const NOT_SECRETS = [];

    private const CREDENTIAL = '/key|token|secret|password|webhook/i';

    public function test_it_declares_this_add_on_only()
    {
        $this->assertSame(['Hampel/AccountUpgradesInfo'], array_keys($this->declarations()));
    }

    public function test_it_declares_no_secrets_and_no_config_keys()
    {
        $this->assertSame(['options' => [], 'config' => []], $this->declared());
    }

    public function test_every_declared_secret_is_an_option_this_add_on_has()
    {
        $problems = [];

        foreach ($this->declared()['options'] AS $path)
        {
            $parts = explode('.', $path);
            $file = $this->addOnRoot() . "/_output/options/{$parts[0]}.json";

            if (!is_file($file))
            {
                $problems[] = "{$path}: no such option";
                continue;
            }

            $members = json_decode(file_get_contents($file), true)['sub_options'] ?? [];
            if (isset($parts[1]) && !in_array($parts[1], $members, true))
            {
                $problems[] = "{$path}: not a member of {$parts[0]}";
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_an_option_or_member_reading_like_a_credential_is_declared_or_decided()
    {
        $decided = array_merge(self::NOT_SECRETS, $this->declared()['options']);
        $undecided = [];

        foreach ($this->optionIds() AS $optionId)
        {
            if (!in_array($optionId, $decided, true) && preg_match(self::CREDENTIAL, $optionId))
            {
                $undecided[] = $optionId;
            }

            $file = $this->addOnRoot() . "/_output/options/{$optionId}.json";
            foreach (json_decode(file_get_contents($file), true)['sub_options'] ?? [] AS $member)
            {
                $path = "{$optionId}.{$member}";
                if (preg_match(self::CREDENTIAL, $member) && !in_array($path, $decided, true))
                {
                    $undecided[] = $path;
                }
            }
        }

        $this->assertSame([], $undecided, 'declare each as a secret, or add it to NOT_SECRETS');
    }

    /** Guards the test above: it asserts nothing useful if it never sees this add-on's options. */
    public function test_the_credential_check_reads_this_add_on_s_options()
    {
        $this->assertCount(4, $this->optionIds());
    }

    private function declarations()
    {
        $declarations = [];
        Listener::adminApiRedaction($declarations);

        return $declarations;
    }

    private function declared()
    {
        return $this->declarations()['Hampel/AccountUpgradesInfo'] + ['options' => [], 'config' => []];
    }

    private function optionIds()
    {
        $ids = [];

        foreach (glob($this->addOnRoot() . '/_output/options/*.json') ?: [] AS $file)
        {
            if (basename($file) != '_metadata.json')
            {
                $ids[] = basename($file, '.json');
            }
        }

        return $ids;
    }

    private function addOnRoot()
    {
        return dirname(__DIR__, 2);
    }
}
