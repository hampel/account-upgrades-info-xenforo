<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A phrase XenForo cannot find renders as its own key, which is visible wherever it appears and
 * invisible to every other check in this suite.
 *
 * These read the forum's phrase table, not _output/phrases/ - a render resolves from the
 * database, so editing a file here proves nothing until xf-dev:import has run. A failure
 * therefore usually means the import was skipped rather than that the phrase is wrong.
 */
class PhrasesTest extends TestCase
{
    public function test_every_phrase_this_add_on_ships_resolves(): void
    {
        $unresolved = [];

        foreach ($this->phraseTitles() AS $title)
        {
            $rendered = (string) \XF::phrase($title);

            if ($rendered === '' || $rendered === $title)
            {
                $unresolved[] = $title;
            }
        }

        $this->assertSame([], $unresolved, 'these render as their own key - import the add-on');
    }

    /** Guards the test above: it asserts nothing if it never finds the phrase files. */
    public function test_it_reads_this_add_on_s_phrases(): void
    {
        $this->assertCount(10, $this->phraseTitles());
    }

    /** @return list<string> */
    private function phraseTitles(): array
    {
        $titles = [];

        foreach (glob(dirname(__DIR__, 2) . '/_output/phrases/*.txt') ?: [] AS $file)
        {
            $titles[] = basename($file, '.txt');
        }

        return $titles;
    }
}
