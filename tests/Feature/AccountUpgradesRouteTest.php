<?php

namespace Tests\Feature;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;

/**
 * The account/upgrades route as XenForo serves it: the real controller, the real repository
 * query, and the whole page. AccountUpgradesPageTest renders the template with lists it builds
 * itself; this is what tells whether a member actually reaches that template.
 *
 * Every row created here is rolled back, so nothing is left on the forum.
 */
class AccountUpgradesRouteTest extends TestCase
{
	use UsesDatabaseTransactions;

	private const MEMBER_ID = 7;

	protected function setUp(): void
	{
		parent::setUp();

		$this->setOptions([
			'hampelAccountUpgradesInfoTitleBefore' => '',
			'hampelAccountUpgradesInfoBodyBefore' => 'Before body',
			'hampelAccountUpgradesInfoTitleAfter' => '',
			'hampelAccountUpgradesInfoBodyAfter' => 'After body',
		]);

		$member = $this->actingAsMember(['user_id' => self::MEMBER_ID, 'user_state' => 'valid']);
		$this->setVisitorPermissions($member, ['general' => ['view' => true]]);
	}

	/**
	 * The member the "before" text is written for. The controller has a branch that answers with
	 * XenForo's own message instead of the template when both lists are empty - which would take
	 * this add-on's blocks with it - but the available list arrives as a collection, which is
	 * never falsy, so the branch does not fire and the template renders. That is core behaviour
	 * this add-on depends on and does not control: if XenForo ever tests the count instead, this
	 * is the test that says so.
	 */
	public function test_a_member_with_nothing_on_offer_still_reaches_the_blocks(): void
	{
		\XF::db()->update('xf_user_upgrade', ['can_purchase' => 0], '1 = 1');

		$reply = $this->dispatch('account/upgrades');

		$this->assertReplyTemplate($reply, 'account_upgrades');
		$this->assertCount(0, $this->listParam($reply, 'available'));
		$this->assertCount(0, $this->listParam($reply, 'purchased'));

		$html = $this->renderReplyPage($reply, ['skipSidebarWidgets' => true]);

		$this->assertSeeInOrder($html, ['Before body', 'After body']);
		$this->assertDontSeeText($html, (string) \XF::phrase('no_account_upgrades_can_be_purchased_at_this_time'));
		$this->assertNoTemplateErrors('account_upgrades');
	}

	/**
	 * XenForo's own query decides what is on offer, from a real upgrade and a real payment
	 * profile - the path AccountUpgradesPageTest's unsaved entities step around. The profile is
	 * inactive, which still renders the purchase button for an upgrade naming one profile, and
	 * means nothing could ever be bought through it.
	 */
	public function test_an_upgrade_on_offer_renders_between_the_blocks(): void
	{
		$upgrade = $this->anUpgradeOnOffer('Route test upgrade');

		$reply = $this->dispatch('account/upgrades');

		$this->assertReplyTemplate($reply, 'account_upgrades');
		$this->assertArrayHasKey($upgrade->getEntityId(), $this->listParam($reply, 'available'));

		$html = $this->renderReplyPage($reply, ['skipSidebarWidgets' => true]);

		$this->assertSeeInOrder(
			$html,
			['Before body', 'Available upgrades', 'Route test upgrade', 'After body']
		);
		$this->assertSee($html, 'user_upgrade_id=' . $upgrade->getEntityId(), false);
		$this->assertNoTemplateErrors('account_upgrades');
	}

	private function anUpgradeOnOffer(string $title): Entity
	{
		$profile = $this->createEntity('XF:PaymentProfile', [
			'provider_id' => 'paypal',
			'title' => 'Route test profile',
			'options' => ['primary_account' => 'payments@example.com'],
			'active' => false,
		]);

		return $this->createEntity('XF:UserUpgrade', [
			'title' => $title,
			'description' => '',
			'cost_amount' => 10,
			'cost_currency' => 'USD',
			'length_amount' => 1,
			'length_unit' => 'month',
			'can_purchase' => true,
			'payment_profile_ids' => [$profile->getEntityId()],
		]);
	}

	/** @return array<int|string, mixed> */
	private function listParam(AbstractReply $reply, string $key): array
	{
		$this->assertReplyParam($reply, $key);
		$this->assertInstanceOf(\XF\Mvc\Reply\View::class, $reply);

		$list = $reply->getParam($key);

		return $list instanceof \XF\Mvc\Entity\AbstractCollection ? $list->toArray() : (array) $list;
	}
}
