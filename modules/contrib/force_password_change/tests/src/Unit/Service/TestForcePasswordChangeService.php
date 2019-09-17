<?php

namespace Drupal\Tests\force_password_change\Service;

use Drupal\force_password_change\Service\ForcePasswordChangeService;

/**
 * @coversDefaultClass \Drupal\force_password_change\Service\ForcePasswordChangeService
 * @group force_password_change
 */
class TestForcePasswordChangeService extends ForcePasswordChangeService
{
	protected function getRequestTime()
	{
		return 1000101;
	}

	protected function userLoadMultiple(Array $uids)
	{
		$return = [];
		foreach($uids as $uid)
		{
			$return[$uid] = 'user' . $uid;
		}

		return $return;
	}
}
