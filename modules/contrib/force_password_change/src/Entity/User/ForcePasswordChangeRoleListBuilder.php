<?php

namespace Drupal\force_password_change\Entity\User;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\user\RoleListBuilder;

class ForcePasswordChangeRoleListBuilder extends RoleListBuilder
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefaultOperations(EntityInterface $entity)
	{
		$operations = parent::getDefaultOperations($entity);

		if($entity->id() != 'anonymous' && \Drupal::currentUser()->hasPermission('administer force password change'))
		{
			$url = Url::fromRoute('force_password_change.admin.role.list', array('rid' => $entity->id()));
			$operations['force_password_change'] = array
			(
				'title' => t('Force password change options'),
				'weight' => 20,
				'url' => $url,
			);
		}

		return $operations;
	}
}
