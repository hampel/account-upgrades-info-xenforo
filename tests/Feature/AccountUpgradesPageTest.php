<?php

namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use XF\Mvc\Entity\Entity;

/**
 * Renders account_upgrades for each option shape worth pinning, and for the two cases the
 * ordering claims need: a list with something available, and one with something purchased.
 *
 * **Every shape runs against both blocks.** The two modifications are separate artifacts
 * carrying the same markup against different option names, so a correction applied to one and
 * not the other is the likeliest way this add-on breaks by hand - and a test naming only the
 * "before" block would pass throughout.
 */
class AccountUpgradesPageTest extends TestCase
{
	// a template error is written to xf_error_log; roll it back rather than leave it behind
	use UsesDatabaseTransactions;

	/** @return array<string, array{string}> the option suffix each block's four options carry */
	public static function blocks(): array
	{
		return ['the before block' => ['Before'], 'the after block' => ['After']];
	}

	/** @return array<string, array{string, string}> the same, with the modification behind it */
	public static function blocksWithTheirModification(): array
	{
		return [
			'the before block' => ['Before', 'hampelAccountUpgradesInfoAbove'],
			'the after block' => ['After', 'hampelAccountUpgradesInfoBelow'],
		];
	}

	public function test_with_every_option_empty_the_page_is_left_to_xenforo(): void
	{
		$html = $this->renderPage([]);

		$this->assertDontSee($html, 'class="block"', false);
		$this->assertSee($html, 'class="blockMessage"', false);
	}

	#[DataProvider('blocks')]
	public function test_a_block_renders_its_title_then_its_body(string $which): void
	{
		$html = $this->renderPage(["Title{$which}" => 'A title', "Body{$which}" => 'A body']);

		$this->assertSeeInOrder($html, ['block-header', 'A title', 'A body']);
	}

	public function test_the_before_block_renders_ahead_of_the_after_block(): void
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
	#[DataProvider('blocks')]
	public function test_a_body_suppresses_the_no_purchasable_upgrades_message(string $which): void
	{
		$html = $this->renderPage(["Body{$which}" => 'A body']);

		$this->assertSeeText($html, 'A body');
		$this->assertDontSee($html, 'class="blockMessage"', false);
	}

	/**
	 * The body gates the block and the title does not, so a title on its own renders nothing -
	 * and leaves the core message in place.
	 */
	#[DataProvider('blocks')]
	public function test_a_title_without_a_body_renders_nothing(string $which): void
	{
		$html = $this->renderPage(["Title{$which}" => 'A title']);

		$this->assertDontSeeText($html, 'A title');
		$this->assertSee($html, 'class="blockMessage"', false);
	}

	#[DataProvider('blocks')]
	public function test_a_body_without_a_title_renders_a_block_with_no_header(string $which): void
	{
		$html = $this->renderPage(["Body{$which}" => 'A body']);

		$this->assertSeeText($html, 'A body');
		$this->assertDontSee($html, 'block-header', false);
	}

	/**
	 * The option explain text promises HTML, so the body is output raw by design. Escaping it
	 * would be a behaviour change for every forum using markup in these options.
	 */
	#[DataProvider('blocks')]
	public function test_a_body_is_output_as_html(string $which): void
	{
		$html = $this->renderPage(["Body{$which}" => '<strong>A body</strong>']);

		$this->assertSee($html, '<strong>A body</strong>', false);
	}

	/**
	 * The other half of that decision, and the asymmetry is deliberate: the title is a heading
	 * inside an `h2`, so it is output escaped. Only the body promises HTML.
	 */
	#[DataProvider('blocks')]
	public function test_a_title_is_escaped_rather_than_output_as_html(string $which): void
	{
		$html = $this->renderPage(["Title{$which}" => '<em>A title</em>', "Body{$which}" => 'A body']);

		$this->assertDontSee($html, '<em>A title</em>', false);
		$this->assertSee($html, '&lt;em&gt;A title&lt;/em&gt;', false);
	}

	/**
	 * The control for every assertion above: render the same page with one modification left out
	 * and its block is gone, while the other add-on block - whose options are also set - is
	 * still there. Without that second half the test would pass just as well if the render had
	 * dropped the add-on altogether, or returned nothing at all.
	 *
	 * Nothing is written and no other suite running against this install is disturbed: the
	 * framework applies the remaining modifications to the template's text in memory, rather
	 * than disabling a row and recompiling the forum's templates.
	 */
	#[DataProvider('blocksWithTheirModification')]
	public function test_without_its_modification_a_block_is_absent(string $which, string $modificationKey): void
	{
		$other = $which == 'Before' ? 'After' : 'Before';

		$this->setPageOptions([
			"Title{$which}" => 'A title',
			"Body{$which}" => 'A body',
			"Body{$other}" => 'The other body',
		]);

		$html = $this->renderTemplateWithoutModification($modificationKey, $this->pageParams());

		$this->assertDontSeeText($html, 'A title');
		$this->assertDontSeeText($html, 'A body');
		$this->assertSeeText($html, 'The other body');
	}

	/**
	 * The suppression the add-on is answerable for, shown from the other side: leave the
	 * modification out and XenForo's own "there are currently no purchasable user upgrades"
	 * message is back on a page whose options are set. Every other test can only show the
	 * message is absent, which an install difference could equally explain.
	 */
	public function test_without_the_add_on_xenforo_shows_its_own_no_upgrades_message(): void
	{
		$this->setPageOptions(['BodyBefore' => 'Before body']);

		$html = $this->renderTemplateWithoutModification(
			'hampelAccountUpgradesInfoAbove',
			$this->pageParams()
		);

		$this->assertDontSeeText($html, 'Before body');
		$this->assertSee($html, 'class="blockMessage"', false);
	}

	/**
	 * The one claim the documentation makes that an empty list cannot show: the "before" block
	 * sits above the available-upgrades list and the "after" block below it. Every other test
	 * here renders with nothing available, which is the case the add-on most has to get right
	 * but says nothing about the ordering.
	 */
	public function test_the_blocks_sit_above_and_below_the_available_upgrades_list(): void
	{
		$html = $this->renderPage(
			['BodyBefore' => 'Before body', 'BodyAfter' => 'After body'],
			[1 => $this->anUpgradeToPurchase('Supporter')]
		);

		$this->assertSeeInOrder($html, ['Before body', 'Available upgrades', 'Supporter', 'After body']);
	}

	/**
	 * "Below" is implemented on the purchased block rather than the available one, because the
	 * available list has no closing anchor unique enough to match - so the "after" block lands
	 * above the purchased list, not below everything. That is the documented behaviour and the
	 * reason the option is named for the available list it sits under.
	 */
	public function test_the_after_block_sits_above_the_purchased_upgrades_list(): void
	{
		$userId = 7;
		$this->actingAsMember(['user_id' => $userId]);

		$html = $this->renderPage(
			['BodyBefore' => 'Before body', 'BodyAfter' => 'After body'],
			[],
			[3 => $this->anUpgradeHeldBy($userId, 'Gold member')]
		);

		$this->assertSeeInOrder(
			$html,
			['Before body', 'After body', 'Purchased upgrades', 'Gold member']
		);
	}

	/**
	 * An unsaved entity rather than a seeded row. The template needs a title, a cost, a
	 * description and one payment profile id to render a purchase button, and none of those
	 * needs the upgrade to exist - so this test adds no forum data and runs the same on a
	 * forum that sells upgrades and one that does not.
	 */
	private function anUpgradeToPurchase(string $title): Entity
	{
		$upgrade = $this->makeEntity('XF:UserUpgrade', [
			'title' => $title,
			'description' => '',
			'cost_amount' => 10,
			'cost_currency' => 'usd',
			'length_amount' => 1,
			'length_unit' => 'month',
			'can_purchase' => true,
			'payment_profile_ids' => [1],
		]);

		// the primary key is auto-increment, so an ordinary set refuses it; the template builds
		// the purchase link and the reply container id from it
		$upgrade->setTrusted('user_upgrade_id', 1);

		return $upgrade;
	}

	/**
	 * The purchased branch reads `$upgrade.Active.{$xf.visitor.user_id}` for the expiry date, so
	 * the relation is hydrated rather than queried - this needs no rows either. `recurring` is
	 * left false deliberately: the payment-provider block below the date is guarded on it, and
	 * rendering a provider's cancellation form would need a purchase request behind it.
	 */
	private function anUpgradeHeldBy(int $userId, string $title): Entity
	{
		$upgrade = $this->makeEntity('XF:UserUpgrade', [
			'title' => $title,
			'description' => '',
			'cost_amount' => 20,
			'cost_currency' => 'usd',
			'length_amount' => 1,
			'length_unit' => 'year',
			'recurring' => false,
			'can_purchase' => false,
		]);
		$upgrade->setTrusted('user_upgrade_id', 3);

		$active = $this->makeEntity('XF:UserUpgradeActive', [
			'user_id' => $userId,
			'user_upgrade_id' => 3,
			'start_date' => \XF::$time - 86400,
			'end_date' => \XF::$time + 86400,
		]);

		$upgrade->hydrateRelation(
			'Active',
			$this->app()->em()->getBasicCollection([$userId => $active])
		);

		return $upgrade;
	}

	/**
	 * Takes the option suffixes - `BodyBefore` and the rest - and sets all four every time, so a
	 * value left behind by an earlier test cannot change what this one renders.
	 *
	 * Named for the page rather than the options because the framework's own `setOptions()` is
	 * what it calls. A private helper of the same name is a fatal at class load rather than a
	 * failing test, PHP method names being case-insensitive - the same shape as the `setup()`
	 * collision with PHPUnit's `setUp()` that cost this suite an afternoon when it was written.
	 *
	 * @param array<string, string> $options
	 */
	private function setPageOptions(array $options): void
	{
		$defaults = ['TitleBefore' => '', 'BodyBefore' => '', 'TitleAfter' => '', 'BodyAfter' => ''];
		$values = [];

		foreach (array_merge($defaults, $options) AS $key => $value)
		{
			$values['hampelAccountUpgradesInfo' . $key] = $value;
		}

		$this->setOptions($values);
	}

	/**
	 * @param array<int, Entity> $available
	 * @param array<int, Entity> $purchased
	 *
	 * @return array<string, mixed> the parameters the core template reads
	 */
	private function pageParams(array $available = [], array $purchased = []): array
	{
		return [
			'available' => $this->app()->em()->getBasicCollection($available),
			'purchased' => $this->app()->em()->getBasicCollection($purchased),
			'canPurchase' => true,
		];
	}

	/**
	 * @param array<string, string> $options
	 * @param array<int, Entity> $available
	 * @param array<int, Entity> $purchased
	 */
	private function renderPage(array $options, array $available = [], array $purchased = []): string
	{
		$this->setPageOptions($options);

		$html = $this->renderTemplate(
			'public:account_upgrades',
			$this->pageParams($available, $purchased)
		);

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
