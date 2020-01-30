<?php

namespace Drupal\Tests\disable_field\Traits;

/**
 * Provides methods to simplify checking if a field is disabled or not.
 *
 * This trait is meant to be used only by test classes.
 */
trait DisableFieldTestTrait {

  /**
   * Check if the given field exists, but is not disabled.
   *
   * @param string $field_name
   *   The field name to check.
   */
  protected function checkIfFieldIsNotDisabledByFieldName(string $field_name) {
    $this->checkIfFieldIsNotDisabledById(sprintf('edit-%s-0-value', str_replace('_', '-', $field_name)));
  }

  /**
   * Check if the given field with the given ID exists, but is not disabled.
   *
   * @param string $id
   *   The ID to check.
   */
  protected function checkIfFieldIsNotDisabledById(string $id) {
    $this->assertSession()->elementExists('css', sprintf('#%s', $id));
    $this->assertSession()->elementNotExists('css', sprintf('#%s[disabled]', $id));
  }

  /**
   * Check if the given field is disabled.
   *
   * @param string $field_name
   *   The field name to check.
   */
  protected function checkIfFieldIsDisabledByFieldName(string $field_name) {
    $this->checkIfFieldIsDisabledById(sprintf('edit-%s-0-value', str_replace('_', '-', $field_name)));
  }

  /**
   * Check if the element with the given ID is disabled.
   *
   * @param string $id
   *   The ID to check.
   */
  protected function checkIfFieldIsDisabledById(string $id) {
    $this->assertSession()->elementExists('css', sprintf('#%s[disabled]', $id));
  }

}
