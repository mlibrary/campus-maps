<?php

namespace Drupal\geofield_map;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\geofield_map\Services\MarkerIconService;

/**
 * A base class for MapThemer plugins.
 */
abstract class MapThemerBase extends PluginBase implements MapThemerInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Icon Managed File Service.
   *
   * @var \Drupal\geofield_map\Services\MarkerIconService
   */
  protected $markerIcon;

  /**
   * Returns the default Icon output for the Legend.
   *
   * @return array
   *   The DefaultLegendIcon render array.
   */
  protected function getDefaultLegendIcon() {
    return [
      '#type' => 'container',
      'markup' => [
        '#markup' => $this->t('[default-icon]'),
      ],
      '#attributes' => [
        'class' => ['default-icon'],
      ],
    ];
  }

  /**
   * Generate Label Alias Help Message.
   *
   * @return array
   *   The label alias help render array..
   */
  public function getLabelAliasHelp() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('If not empty, this will be used in the legend as label alias.'),
      '#attributes' => [
        'style' => ['style' => 'font-size:0.8em; color: gray; font-weight: normal'],
      ],
    ];
  }

  /**
   * Define if to return the default Legend Icon.
   *
   * This might act on values to which no image/Managed_file has been input.
   * This might be overridden by MapThemer plugins to alter this default
   * behaviour.
   *
   * @return bool
   *   If the default Legend Icon cases/values should be listed in the legend.
   */
  protected function renderDefaultLegendIcon() {
    return TRUE;
  }

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\geofield_map\Services\MarkerIconService $marker_icon_service
   *   The Marker Icon Service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $translation_manager,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_manager,
    MarkerIconService $marker_icon_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->setStringTranslation($translation_manager);
    $this->renderer = $renderer;
    $this->entityManager = $entity_manager;
    $this->markerIcon = $marker_icon_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('geofield_map.marker_icon')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings($k = NULL) {
    $default_settings = $this->pluginDefinition['defaultSettings'];
    if (!empty($k)) {
      return $default_settings[$k];
    }
    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultThemerElement(array $defaults) {
    $default_value = !empty($defaults['map_marker_and_infowindow']['theming'][$this->pluginId]['values']) ? $defaults['map_marker_and_infowindow']['theming'][$this->pluginId]['values'] : $this->defaultSettings('values');
    return $default_value;
  }

}
