<?php

namespace Tests;

use Hampel\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	/**
	 * Four levels up, because the add-on id carries a vendor: src/addons/Vendor/AddOn. No
	 * trailing slash.
	 */
	protected $rootDir = '../../../..';

	/**
	 * Load only this add-on. Other add-ons on the same forum may vendor their own PHPUnit, and
	 * booting with all of them registers those trees onto XenForo's class loader, so PHPUnit's
	 * classes can resolve out of someone else's vendor directory and the run fatals before the
	 * first test.
	 *
	 * Template modifications are unaffected: they are compiled into the templates in the
	 * database, not applied by a listener or a class extension, so the isolation does not
	 * remove them.
	 */
	protected $addonsToLoad = ['Hampel/AccountUpgradesInfo'];
}
