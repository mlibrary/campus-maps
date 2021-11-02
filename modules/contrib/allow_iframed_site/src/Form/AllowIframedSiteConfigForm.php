<?php

namespace Drupal\allow_iframed_site\Form;

use Drupal\Component\Plugin\Factory\FactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AllowIframedSiteConfigForm extends ConfigFormBase {

  /**
   * The conditions.
   *
   * @var array $conditions
   */
  protected $conditions;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, FactoryInterface $plugin_factory) {
    parent::__construct($config_factory);
    $this->conditions['request_path'] = $plugin_factory->createInstance('request_path');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.condition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'allow_iframed_site_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load our default configuration.
    $config = $this->config('allow_iframed_site.settings');

    // Set the default condition configuration.
    foreach ($this->conditions as $key => $condition) {
      $condition->setConfiguration($config->get($key) ?? []);
      $form += $condition->buildConfigurationForm($form, $form_state);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('allow_iframed_site.settings');

    foreach ($this->conditions as $key => $condition) {
      $condition->submitConfigurationForm($form, $form_state);
      $config->set($key, $condition->getConfiguration());
    }
    $config->save();
    parent::submitForm($form, $form_state);
    // @TODO there should be a better way to invalidate the page cache.
    // but chances are this config will change rarely and be used on smaller sites.
    drupal_flush_all_caches();
  }


  protected function getEditableConfigNames() {
    return [
      'allow_iframed_site.settings',
    ];
  }
}
