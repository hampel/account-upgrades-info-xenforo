<?php

namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;

/**
 * Renders account_upgrades with no upgrades available and none purchased, which is the case the
 * add-on's behaviour depends on most: both blocks render outside the conditionals they anchor
 * on, and inside the core template's own contentcheck.
 */
class AccountUpgradesPageTest extends TestCase
{
	// a template error is written to xf_error_log; roll it back rather than leave it behind
	use UsesDatabaseTransactions;

	public function test_with_every_option_empty_the_page_is_left_to_xenforo()
	{
		$html = $this->renderPage([]);

		$this->assertDontSee($html, 'class="block"', false);
		$this->assertSee($html, 'class="blockMessage"', false);
	}

	public function test_the_before_block_renders_its_title_then_its_body()
	{
		$html = $this->renderPage(['TitleBefore' => 'Before title', 'BodyBefore' => 'Before body']);

		$this->assertSeeInOrder($html, ['block-header', 'Before title', 'Before body']);
	}

	public function test_the_before_block_renders_ahead_of_the_after_block()
	{
		$html = $this->renderPage(['BodyBefore' => 'Before body', 'BodyAfter' => 'After body']);

		$this->assertSeeInOrder($html, ['Before body', 'After body']);
	}

	/**
	 * Inherent to where the modifications can anchor: both insert inside the core template's
	 * outer contentcheck, so anything they render satisfies it and "there are currently no
	 * purchasable user upgrades" never appears. Documented behaviour - this test is here so a
	 * change to the anchors cannot alter it unnoticed.
	 */
	public function test_a_body_suppresses_the_no_purchasable_upgrades_message()
	{
		$html = $this->renderPage(['BodyBefore' => 'Before body']);

		$this->assertSeeText($html, 'Before body');
		$this->assertDontSee($html, 'class="blockMessage"', false);
	}

	/**
	 * The body gates the block and the title does not, so a title on its own renders nothing -
	 * and leaves the core message in place.
	 */
	public function test_a_title_without_a_body_renders_nothing()
	{
		$html = $this->renderPage(['TitleAfter' => 'After title']);

		$this->assertDontSeeText($html, 'After title');
		$this->assertSee($html, 'class="blockMessage"', false);
	}

	public function test_a_body_without_a_title_renders_a_block_with_no_header()
	{
		$html = $this->renderPage(['BodyAfter' => 'After body']);

		$this->assertSeeText($html, 'After body');
		$this->assertDontSee($html, 'block-header', false);
	}

	/**
	 * The option explain text promises HTML, so the body is output raw by design. Escaping it
	 * would be a behaviour change for every forum using markup in these options.
	 */
	public function test_the_body_is_output_as_html()
	{
		$html = $this->renderPage(['BodyBefore' => '<strong>Before body</strong>']);

		$this->assertSee($html, '<strong>Before body</strong>', false);
	}

	private function renderPage(array $options)
	{
		$defaults = ['TitleBefore' => '', 'BodyBefore' => '', 'TitleAfter' => '', 'BodyAfter' => ''];

		foreach (array_merge($defaults, $options) AS $key => $value)
		{
			$this->setOption('hampelAccountUpgradesInfo' . $key, $value);
		}

		$html = $this->renderTemplate('public:account_upgrades', [
			'available' => $this->app()->em()->getBasicCollection([]),
			'purchased' => $this->app()->em()->getBasicCollection([]),
			'canPurchase' => true,
		]);

		$this->assertNoTemplateErrors();

		return $html;
	}
}
