<?php

namespace Drupal\force_password_change\Controller;

interface ForcePasswordChangeControllerInterface
{
	public function adminPage();

	public function roleListPage($rid);
}
