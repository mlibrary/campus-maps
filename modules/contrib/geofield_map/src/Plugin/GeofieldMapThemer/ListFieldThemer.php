<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\geofield_map\Services\MarkerIconService;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "geofieldmap_list_fields",
 *   name = @Translation("List Type Field (Geofield Map)"),
 *   description = "This Geofield Map Themer allows the definition of different Marker Icons based on List (Options) Type fields in View.",
 *   type = "key_value",
 *   context = {"ViewStyle"},
 *   defaultSettings = {
 *    "values": {}
 *   },
 * )
 */
class ListFieldThemer extends MapThemerBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
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
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_manager,
    MarkerIconService $marker_icon_service,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $translation_manager, $renderer, $entity_manager, $marker_icon_service);
    $this->config = $config_factory;
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
      $container->get('config.factory'),
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
    $view_fields = $geofieldMapView->getViewFields();

    // Get the field_storage_definitions.
    $field_storage_definitions = $geofieldMapView->getEntityFieldManager()->getFieldStorageDefinitions($entity_type);

    $list_fields = [];
    foreach ($view_fields as $field_id => $field_label) {
      /* @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
      if (isset($field_storage_definitions[$field_id])
        && $field_storage_definitions[$field_id] instanceof FieldStorageConfig
        && in_array($field_storage_definitions[$field_id]->getType(), [
          'list_string',
          'list_integer',
          'list_float',
        ])
        && $field_storage_definitions[$field_id]->getCardinality() == 1
      ) {
        $list_fields[$field_id] = ['options' => $field_storage_definitions[$field_id]->getSetting('allowed_values')];
      }
    }

    foreach ($list_fields as $field_id => $field_label) {
      // Reorder the field_id options on existing (Default) Element settings.
      if (!empty($default_element)) {
        $weighted_options[$field_id] = [];
        foreach ($list_fields[$field_id]['options'] as $key => $option) {
          $weighted_options[$field_id][$key] = [
            'weight' => isset($default_element['fields'][$field_id]['options'][$key]) ? $default_element['fields'][$field_id]['options'][$key]['weight'] : 0,
          ];
        }
        uasort($weighted_options[$field_id], 'Drupal\Component\Utility\SortArray::sortByWeightElement');
        $list_fields[$field_id]['options'] = array_replace(array_flip(array_keys($weighted_options[$field_id])), $list_fields[$field_id]['options']);
      }
    }

    if (!count($list_fields) > 0) {
      $element['list_field'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('At least a List Type field (<u>with a cardinality of 1</u>) should be added to the View to use this Map Theming option.'),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ];
    }
    else {
      $element['list_field'] = [
        '#type' => 'select',
        '#title' => $this->t('List Type Field'),
        '#description' => $this->t('Chose the List type field to base the Map Theming upon.'),
        '#options' => array_combine(array_keys($list_fields), array_keys($list_fields)),
        '#default_value' => !empty($default_element['list_field']) ? $default_element['list_field'] : array_shift(array_keys($list_fields)),
      ];

      $element['list_field']['fields'] = [];
      foreach ($list_fields as $k => $field) {

        $caption = [
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#value' => $this->t('Options from  @field field', [
              '@field' => $k,
            ]),
            'notes' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => $this->t('The - Default Value - will be used as fallback Value/Marker for unset Options'),
              '#attributes' => [
                'style' => ['style' => 'font-size:0.8em; color: gray; font-weight: normal'],
              ],
            ],
          ],
        ];

        $label_alias_upload_help = $this->getLabelAliasHelp();
        $file_upload_help = $this->markerIcon->getFileUploadHelp();

        $element['fields'][$k] = [
          '#type' => 'container',
          'options' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Option'),
              $this->t('Weight'),
              Markup::create($this->t('Option Alias @description', [
                '@description' => $this->renderer->renderPlain($label_alias_upload_help),
              ])),
              Markup::create($this->t('Marker Icon @file_upload_help', [
                '@file_upload_help' => $this->renderer->renderPlain($file_upload_help),
              ])),
              $this->t('Icon Image Style'),
              $this->t('Hide from Legend'),
            ],
            '#tabledrag' => [
              [
                'action' => 'order',
                'relationship' => 'sibling',
                'group' => 'options-order-weight',
              ],
            ],
            '#caption' => $this->renderer->renderPlain($caption),
          ],
          '#states' => [
            'visible' => [
              'select[name="style_options[map_marker_and_infowindow][theming][geofieldmap_list_fields][values][list_field]"]' => ['value' => $k],
            ],
          ],
        ];

        // Add a Default Value to be used as possible fallback Value/Marker.
        $field['options']['__default_value__'] = '- Default Value - ';

        $i = 0;
        foreach ($field['options'] as $key => $value) {
          $fid = (integer) !empty($default_element['fields'][$k]['options'][$key]['icon_file']['fids']) ? $default_element['fields'][$k]['options'][$key]['icon_file']['fids'] : NULL;
          $element['fields'][$k]['options'][$key] = [
            'label' => [
              '#type' => 'value',
              '#value' => $value,
              'markup' => [
                '#markup' => $value,
              ],
            ],
            'weight' => [
              '#type' => 'weight',
              '#title_display' => 'invisible',
              '#default_value' => isset($default_element['fields'][$k]['options'][$key]['weight']) ? $default_element['fields'][$k]['options'][$key]['weight'] : $i,
              '#delta' => 20,
              '#attributes' => ['class' => ['options-order-weight']],
            ],
            'label_alias' => [
              '#type' => 'textfield',
              '#default_value' => isset($default_element['fields'][$k]['options'][$key]['label_alias']) ? $default_element['fields'][$k]['options'][$key]['label_alias'] : '',
              '#size' => 30,
              '#maxlength' => 128,
            ],
            'icon_file' => $this->markerIcon->getIconFileManagedElement($fid),
            'image_style' => [
              '#type' => 'select',
              '#title' => t('Image style'),
              '#title_display' => 'invisible',
              '#options' => $this->markerIcon->getImageStyleOptions(),
              '#default_value' => isset($default_element['fields'][$k]['options'][$key]['image_style']) ? $default_element['fields'][$k]['options'][$key]['image_style'] : 'geofield_map_default_icon_style',
            ],
            'legend_exclude' => [
              '#type' => 'checkbox',
              '#default_value' => isset($default_element['fields'][$k]['options'][$key]['legend_exclude']) ? $default_element['fields'][$k]['options'][$key]['legend_exclude'] : '0',
            ],
            '#attributes' => ['class' => ['draggable']],
          ];
          $i++;
        }

      }
    }
    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {

    $list_field = $map_theming_values['list_field'];
    $fallback_icon_style = isset($map_theming_values['fields'][$list_field]['options']['__default_value__']['image_style']) ? $map_theming_values['fields'][$list_field]['options']['__default_value__']['image_style'] : NULL;
    $fallback_icon = isset($map_theming_values['fields'][$list_field]['options']['__default_value__']['icon_file']) ? $map_theming_values['fields'][$list_field]['options']['__default_value__']['icon_file']['fids'] : NULL;
    $image_style = $fallback_icon_style;
    $fid = $fallback_icon;
    if (isset($entity->{$list_field})) {
      $list_field_option = $entity->{$list_field}->value;
      $image_style = isset($map_theming_values['fields'][$list_field]['options'][$list_field_option]['image_style']) ? $map_theming_values['fields'][$list_field]['options'][$list_field_option]['image_style'] : $fallback_icon_style;
      $fid = isset($map_theming_values['fields'][$list_field]['options'][$list_field_option]['icon_file']) && !empty($map_theming_values['fields'][$list_field]['options'][$list_field_option]['icon_file']['fids']) ? $map_theming_values['fields'][$list_field]['options'][$list_field_option]['icon_file']['fids'] : $fallback_icon;
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
        isset($configuration['values_label']) ? $configuration['values_label'] : $this->t('Option'),
        isset($configuration['markers_label']) ? $configuration['markers_label'] : $this->t('Marker/Icon'),
      ],
      '#caption' => isset($configuration['legend_caption']) ? $configuration['legend_caption'] : '',
      '#attributes' => [
        'class' => ['geofield-map-legend', 'option'],
      ],
    ];

    $list_field = $map_theming_values['list_field'];

    foreach ($map_theming_values['fields'][$list_field]['options'] as $key => $value) {

      // Get the icon image style, as result of the Legend configuration.
      $image_style = isset($configuration['markers_image_style']) ? $configuration['markers_image_style'] : 'none';
      // Get the map_theming_image_style, is so set.
      if (isset($configuration['markers_image_style']) && $configuration['markers_image_style'] == '_map_theming_image_style_') {
        $image_style = isset($map_theming_values['fields'][$list_field]['options'][$key]['image_style']) ? $map_theming_values['fields'][$list_field]['options'][$key]['image_style'] : 'none';
      }
      $fid = (integer) !empty($value['icon_file']['fids']) ? $value['icon_file']['fids'] : NULL;

      // Don't render legend row in case:
      // - the specific value is flagged as excluded from the Legend, or
      // - no image is associated and the plugin denies to render the
      // DefaultLegendIcon definition.
      if (!empty($value['legend_exclude']) || (empty($fid) && !$this->renderDefaultLegendIcon())) {
        continue;
      }
      $label = isset($value['label']) ? $value['label'] : $key;
      $legend[$key] = [
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
