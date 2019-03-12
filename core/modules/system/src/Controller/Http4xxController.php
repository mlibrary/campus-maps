<?php

namespace Drupal\system\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for default HTTP 4xx responses.
 */
class Http4xxController extends ControllerBase {

  /**
   * The default 4xx error content.
   *
   * @return array
   *   A render array containing the message to display for 4xx errors.
   */
  public function on4xx() {
    return [
      '#markup' => $this->t('A client error happened'),
    ];
  }

  /**
   * The default 401 content.
   *
   * @return array
   *   A render array containing the message to display for 401 pages.
   */
  public function on401() {
    return [
      '#markup' => $this->t('Please log in to access this page.'),
    ];
  }

  /**
   * The default 403 content.
   *
   * @return array
   *   A render array containing the message to display for 403 pages.
   */
  public function on403() {
    $dest = \Drupal::destination()->getAsArray()['destination'];
    $login_path = '/campus_map/user/login?destination='.$dest;
    return [
      '#markup' => $this->t('<p><strong>Library staff and student employees:</strong> <a href="'.$login_path.'">Log In</a> to access the intranet.</p>
 <p>If you need assistance please contact <a maitto="design-discovery@umich.edu">design-discovery@umich.edu</a>.</p>'),
    ];
  }

  /**
   * The default 404 content.
   *
   * @return array
   *   A render array containing the message to display for 404 pages.
   */
  public function on404() {
    return [
      '#markup' => $this->t('The requested page could not be found.'),
    ];
  }

}
