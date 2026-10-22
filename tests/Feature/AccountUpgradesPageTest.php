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

	/**
	 * The one claim the documentation makes that an empty list cannot show: the "before" block
	 * sits above the available-upgrades list and the "after" block below it. Every other test
	 * here renders with nothing available, which is the case the add-on most has to get right
	 * but says nothing about the ordering.
	 */
	public function test_the_blocks_sit_above_and_below_the_available_upgrades_list()
	{
		$html = $this->renderPage(
			['BodyBefore' => 'Before body', 'BodyAfter' => 'After body'],
			[1 => $this->anUpgradeToPurchase('Supporter')]
		);

		$this->assertSeeInOrder($html, ['Before body', 'Available upgrades', 'Supporter', 'After body']);
	}

	/**
	 * An unsaved entity rather than a seeded row. The template needs a title, a cost, a
	 * description and one payment profile id to render a purchase button, and none of those
	 * needs the upgrade to exist - so this test adds no forum data and runs the same on a
	 * forum that sells upgrades and one that does not.
	 */
	private function anUpgradeToPurchase($title)
	{
		$upgrade = $this->app()->em()->create('XF:UserUpgrade');
		$upgrade->setTrusted('user_upgrade_id', 1);
		$upgrade->title = $title;
		$upgrade->description = '';
		$upgrade->cost_amount = 10;
		$upgrade->cost_currency = 'usd';
		$upgrade->length_amount = 1;
		$upgrade->length_unit = 'month';
		$upgrade->can_purchase = true;
		$upgrade->payment_profile_ids = [1];

		return $upgrade;
	}

	private function renderPage(array $options, array $available = [])
	{
		$defaults = ['TitleBefore' => '', 'BodyBefore' => '', 'TitleAfter' => '', 'BodyAfter' => ''];

		foreach (array_merge($defaults, $options) AS $key => $value)
		{
			$this->setOption('hampelAccountUpgradesInfo' . $key, $value);
		}

		$html = $this->renderTemplate('public:account_upgrades', [
			'available' => $this->app()->em()->getBasicCollection($available),
			'purchased' => $this->app()->em()->getBasicCollection([]),
			'canPurchase' => true,
		]);

		// Scoped to the template this add-on modifies, not to every template the render
		// touched. account_upgrades wraps the core account_wrapper, and a template modification
		// compiles into the install's shared template cache whatever $addonsToLoad says - so a
		// sibling add-on modifying account_wrapper to read something its own class extension
		// declares raises an error on every render here, which this suite is not answerable for.
		//
		// A name filter rather than a closure, which the framework suggests for an add-on that
		// owns no templates: this add-on is the only one modifying account_upgrades, so the name
		// does identify its markup. If that stops being true the filter will start reporting a
		// sibling's errors against this suite - which is the right way round, since an error
		// raised rendering this template is worth seeing either way.
		$this->assertNoTemplateErrors('account_upgrades');

		return $html;
	}
}
