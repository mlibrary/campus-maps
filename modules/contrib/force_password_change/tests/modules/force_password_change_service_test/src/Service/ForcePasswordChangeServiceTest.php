<?php

namespace Drupal\force_password_change_service_test\Service;

use Drupal\force_password_change\Service\ForcePasswordChangeService;

/**
 * Test class created to override the ForcePasswordChangeService service.
 */
class ForcePasswordChangeServiceTest extends ForcePasswordChangeService {

  /**
   * {@inheritdoc}
   */
  protected function userLoadMultiple(array $uids) {
    $return = [];
    foreach ($uids as $uid) {
      $return[$uid] = 'user' . $uid;
    }

    return $return;
  }

}
