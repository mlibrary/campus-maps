<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\geofield_map\Services\MarkerIconService;
use Drupal\Core\Entity\EntityInterface;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "geofieldmap_entity_type",
 *   name = @Translation("Entity Type (Geofield Map)"),
 *   description = "This Geofield Map Themer allows the definition of different Marker Icons based on the View filtered Entity Types/Bundles.",
 *   type = "key_value",
 *   context = {"ViewStyle"},
 *   defaultSettings = {
 *    "values": {}
 *   },
 * )
 */
class EntityTypeThemer extends MapThemerBase {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

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
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $translation_manager,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_manager,
    MarkerIconService $marker_icon_service,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $translation_manager, $renderer, $entity_manager, $marker_icon_service);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('geofield_map.marker_icon'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, array &$form, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the existing (Default) Element settings.
    $default_element = $this->getDefaultThemerElement($defaults);

    // Get the View Filtered entity bundles.
    $entity_type = $geofieldMapView->getViewEntityType();
    $entity_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $view_bundles = !empty($geofieldMapView->getViewFilteredBundles()) ? array_intersect(array_keys($entity_bundles), $geofieldMapView->getViewFilteredBundles()) : array_keys($entity_bundles);

    // Reorder the entity bundles based on existing (Default) Element settings.
    if (!empty($default_element)) {
      $weighted_bundles = [];
      foreach ($view_bundles as $bundle) {
        $weighted_bundles[$bundle] = [
          'weight' => isset($default_element[$bundle]) ? $default_element[$bundle]['weight'] : 0,
        ];
      }
      uasort($weighted_bundles, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
      $view_bundles = array_keys($weighted_bundles);
    }

    $caption = [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'label',
        '#value' => $this->t('Icon Urls, per Entity Types'),
      ],
    ];

    $label_alias_upload_help = $this->getLabelAliasHelp();
    $file_upload_help = $this->markerIcon->getFileUploadHelp();

    $element = [
      '#type' => 'table',
      '#header' => [
        $this->t('@entity type Type/Bundle', ['@entity type' => $entity_type]),
        $this->t('Weight'),
        Markup::create($this->t('Label Alias @description', [
          '@description' => $this->renderer->renderPlain($label_alias_upload_help),
        ])),
        Markup::create($this->t('Marker Icon @file_upload_help', [
          '@file_upload_help' => $this->renderer->renderPlain($file_upload_help),
        ])),
        $this->t('Icon Image Style'),
        $this->t('Exclude from Legend'),
      ],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'bundles-order-weight',
      ],
      ],
      '#caption' => $this->renderer->renderPlain($caption),
    ];

    foreach ($view_bundles as $k => $bundle) {

      $fid = (integer) !empty($default_element[$bundle]['icon_file']['fids']) ? $default_element[$bundle]['icon_file']['fids'] : NULL;
      $element[$bundle] = [
        'label' => [
          '#type' => 'value',
          '#value' => $entity_bundles[$bundle]['label'],
          'markup' => [
            '#markup' => $entity_bundles[$bundle]['label'],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @bundle', ['@bundle' => $bundle]),
          '#title_display' => 'invisible',
          '#default_value' => isset($default_element[$bundle]['weight']) ? $default_element[$bundle]['weight'] : $k,
          '#delta' => 20,
          '#attributes' => ['class' => ['bundles-order-weight']],
        ],
        'label_alias' => [
          '#type' => 'textfield',
          '#default_value' => isset($default_element[$bundle]['label_alias']) ? $default_element[$bundle]['label_alias'] : '',
          '#size' => 30,
          '#maxlength' => 128,
        ],
        'icon_file' => $this->markerIcon->getIconFileManagedElement($fid),
        'image_style' => [
          '#type' => 'select',
          '#title' => t('Image style'),
          '#title_display' => 'invisible',
          '#options' => $this->markerIcon->getImageStyleOptions(),
          '#default_value' => isset($default_element[$bundle]['image_style']) ? $default_element[$bundle]['image_style'] : 'geofield_map_default_icon_style',
        ],
        'legend_exclude' => [
          '#type' => 'checkbox',
          '#default_value' => isset($default_element[$bundle]['legend_exclude']) ? $default_element[$bundle]['legend_exclude'] : '0',
        ],
        '#attributes' => ['class' => ['draggable']],
      ];

    }

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {
    $fid = NULL;
    $image_style = isset($map_theming_values[$entity->bundle()]['image_style']) ? $map_theming_values[$entity->bundle()]['image_style'] : 'none';
    if (method_exists($entity, 'bundle')) {
      $fid = isset($map_theming_values[$entity->bundle()]['icon_file']) ? $map_theming_values[$entity->bundle()]['icon_file']['fids'] : NULL;
    }
    return $this->markerIcon->getFileManagedUrl($fid, $image_style);
  }

  /**
   * {@inheritdoc}
   */
  public function getLegend(array $map_theming_values, array $configuration = []) {
    $legend = [
      '#type' => 'table',
      '#header' => [
        isset($configuration['values_label']) ? $configuration['values_label'] : $this->t('Type/Bundle'),
        isset($configuration['markers_label']) ? $configuration['markers_label'] : $this->t('Marker'),
      ],
      '#caption' => isset($configuration['legend_notes']) ? $configuration['legend_notes'] : '',
      '#attributes' => [
        'class' => ['geofield-map-legend', 'entity-type'],
      ],
    ];

    foreach ($map_theming_values as $bundle => $value) {

      // Get the icon image style, as result of the Legend configuration.
      $image_style = isset($configuration['markers_image_style']) ? $configuration['markers_image_style'] : 'none';
      // Get the map_theming_image_style, is so set.
      if (isset($configuration['markers_image_style']) && $configuration['markers_image_style'] == '_map_theming_image_style_') {
        $image_style = isset($map_theming_values[$bundle]['image_style']) ? $map_theming_values[$bundle]['image_style'] : 'none';
      }
      $fid = (integer) !empty($value['icon_file']['fids']) ? $value['icon_file']['fids'] : NULL;

      // Don't render legend row in case no image is associated and the plugin
      // denies to render the DefaultLegendIcon definition.
      if (!empty($value['legend_exclude']) || (empty($fid) && !$this->renderDefaultLegendIcon())) {
        continue;
      }
      $label = isset($value['label']) ? $value['label'] : $bundle;
      $legend[$bundle] = [
        'value' => [
          '#type' => 'container',
          'label' => [
            '#markup' => !empty($value['label_alias']) ? $value['label_alias'] : $label,
          ],
          '#attributes' => [
            'class' => ['value'],
          ],
        ],
        'marker' => [
          '#type' => 'container',
          'icon_file' => !empty($fid) ? $this->markerIcon->getLegendIcon($fid, $image_style) : $this->getDefaultLegendIcon(),
          '#attributes' => [
            'class' => ['marker'],
          ],
        ],
      ];
    }

    return $legend;
  }

}
