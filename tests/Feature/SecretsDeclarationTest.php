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
    /**
     * Options that read like a credential and are not one, with the reason.
     *
     * @var list<string>
     */
    private const NOT_SECRETS = [];

    private const CREDENTIAL = '/key|token|secret|password|webhook/i';

    public function test_it_declares_this_add_on_only(): void
    {
        $this->assertSame(['Hampel/AccountUpgradesInfo'], array_keys($this->declarations()));
    }

    public function test_it_declares_no_secrets_and_no_config_keys(): void
    {
        $this->assertSame(['options' => [], 'config' => []], $this->declared());
    }

    public function test_every_declared_secret_is_an_option_this_add_on_has(): void
    {
        $problems = [];

        foreach ($this->declared()['options'] AS $path)
        {
            $parts = explode('.', $path);

            if (!is_file($this->addOnRoot() . "/_output/options/{$parts[0]}.json"))
            {
                $problems[] = "{$path}: no such option";
                continue;
            }

            if (isset($parts[1]) && !in_array($parts[1], $this->optionMembers($parts[0]), true))
            {
                $problems[] = "{$path}: not a member of {$parts[0]}";
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_an_option_or_member_reading_like_a_credential_is_declared_or_decided(): void
    {
        $decided = array_merge(self::NOT_SECRETS, $this->declared()['options']);
        $undecided = [];

        foreach ($this->optionIds() AS $optionId)
        {
            if (!in_array($optionId, $decided, true) && preg_match(self::CREDENTIAL, $optionId))
            {
                $undecided[] = $optionId;
            }

            foreach ($this->optionMembers($optionId) AS $member)
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

    /**
     * Every other test here calls the method directly, which proves the declaration and nothing
     * about whether the forum ever calls it. This fires the event the way the add-on defining it
     * does, so it fails when the listener row is missing, inactive or names the wrong callback -
     * and that row arrives on an existing forum only through an import, not with the code.
     */
    public function test_the_forum_calls_the_listener_when_the_event_fires(): void
    {
        $declarations = [];
        \XF::fire('hampel_admin_api_redaction', [&$declarations]);

        $this->assertSame($this->declarations(), $declarations);
    }

    /** Guards the test above: it asserts nothing useful if it never sees this add-on's options. */
    public function test_the_credential_check_reads_this_add_on_s_options(): void
    {
        $this->assertCount(4, $this->optionIds());
    }

    /** @return array<string, mixed> what the event collected, keyed by add-on id */
    private function declarations(): array
    {
        $declarations = [];
        Listener::adminApiRedaction($declarations);

        return $declarations;
    }

    /**
     * This add-on's own declaration, with both sections present whether it named them or not: an
     * add-on with no secrets declares the empty array, and every test above reads a section.
     *
     * The assertions check and narrow at once. The event collects `array<string, mixed>`, so
     * without them nothing read back out of it has a type, and a declaration of the wrong shape
     * would be read as an empty one rather than reported.
     *
     * @return array{options: list<string>, config: array<mixed>}
     */
    private function declared(): array
    {
        $declaration = $this->declarations()['Hampel/AccountUpgradesInfo'] ?? null;
        $this->assertIsArray($declaration, 'this add-on declared nothing at all');

        $options = $declaration['options'] ?? [];
        $config = $declaration['config'] ?? [];

        $this->assertIsArray($options, 'the options section is not a list of option paths');
        $this->assertContainsOnlyString($options, 'an option path is not a string');
        $this->assertIsArray($config, 'the config section is not an array of config.php keys');

        return ['options' => array_values($options), 'config' => $config];
    }

    /** @return list<string> the option ids this add-on ships, from _output/options/ */
    private function optionIds(): array
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

    /**
     * An option's members, named by `sub_options` in its shipped artifact. An option with none
     * leaves the key out, which is the empty list rather than a failure.
     *
     * @return list<string>
     */
    private function optionMembers(string $optionId): array
    {
        $file = $this->addOnRoot() . "/_output/options/{$optionId}.json";

        $json = file_get_contents($file);
        $this->assertIsString($json, "could not read {$file}");

        $artifact = json_decode($json, true);
        $this->assertIsArray($artifact, "{$file} does not hold an option");

        $members = $artifact['sub_options'] ?? [];
        $this->assertIsArray($members, "{$optionId}: sub_options is not a list of members");
        $this->assertContainsOnlyString($members, "{$optionId}: a member is not a string");

        return array_values($members);
    }

    private function addOnRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
