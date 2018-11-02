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
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "geofieldmap_taxonomy_term",
 *   name = @Translation("Taxonomy Term (Geofield Map)"),
 *   description = "This Geofield Map Themer allows the definition of different Marker Icons based on Taxonomy Terms reference field in View.",
 *   type = "key_value",
 *   context = {"ViewStyle"},
 *   defaultSettings = {
 *    "values": {}
 *   },
 * )
 */
class TaxonomyTermThemer extends MapThemerBase {

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

    $taxonomy_ref_fields = [];
    foreach ($view_fields as $field_id => $field_label) {
      /* @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
      if (isset($field_storage_definitions[$field_id])
        && $field_storage_definitions[$field_id] instanceof FieldStorageConfig
        && $field_storage_definitions[$field_id]->getType() == 'entity_reference'
        && $field_storage_definitions[$field_id]->getSetting('target_type') == 'taxonomy_term'
        && $field_storage_definitions[$field_id]->getCardinality() == 1) {
        $taxonomy_ref_fields[$field_id] = [];
      }
    }

    $entity_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $view_bundles = !empty($geofieldMapView->getViewFilteredBundles()) ? $geofieldMapView->getViewFilteredBundles() : array_keys($entity_bundles);

    foreach ($taxonomy_ref_fields as $field_id => $data) {
      $taxonomy_ref_fields[$field_id]['target_bundles'] = [];
      foreach ($view_bundles as $bundle) {
        $target_bundles = $this->config->get('field.field.' . $entity_type . '.' . $bundle . '.' . $field_id)->get('settings.handler_settings.target_bundles');
        $target_bundles = is_array($target_bundles) ? array_keys($target_bundles) : [];
        if (!empty($target_bundles)) {
          $taxonomy_ref_fields[$field_id]['target_bundles'] = array_unique(array_merge($taxonomy_ref_fields[$field_id]['target_bundles'], $target_bundles));
        }
      }
    }

    foreach ($taxonomy_ref_fields as $field_id => $data) {
      $taxonomy_ref_fields[$field_id]['terms'] = [];
      foreach ($data['target_bundles'] as $vid) {
        try {
          $taxonomy_terms = [];
          /* @var \Drupal\taxonomy\TermStorageInterface $taxonomy_term_storage */
          $taxonomy_term_storage = $this->entityManager->getStorage('taxonomy_term');
          /* @var \stdClass $term */
          foreach ($taxonomy_term_storage->loadTree($vid) as $term) {
            $taxonomy_terms[$term->tid] = $term->name . (count($data['target_bundles']) > 1 ? ' (vid: ' . $vid . ')' : '');
          }
          $taxonomy_ref_fields[$field_id]['terms'] += $taxonomy_terms;
        }
        catch (InvalidPluginDefinitionException $e) {
        }
        catch (PluginNotFoundException $e) {

        }
      }

      // Reorder the field_id referenceable terms on existing (Default)
      // Element settings.
      if (!empty($default_element)) {
        $weighted_terms[$field_id] = [];
        foreach ($taxonomy_ref_fields[$field_id]['terms'] as $tid => $term) {
          $weighted_terms[$field_id][$tid] = [
            'weight' => isset($default_element['fields'][$field_id]['terms'][$tid]) ? $default_element['fields'][$field_id]['terms'][$tid]['weight'] : 0,
          ];
        }
        uasort($weighted_terms[$field_id], 'Drupal\Component\Utility\SortArray::sortByWeightElement');
        $taxonomy_ref_fields[$field_id]['terms'] = array_replace(array_flip(array_keys($weighted_terms[$field_id])), $taxonomy_ref_fields[$field_id]['terms']);
      }

    }

    // Define a default taxonomy_field.
    $default_taxonomy_field = !empty($default_element['taxonomy_field']) ? $default_element['taxonomy_field'] : array_shift(array_keys($taxonomy_ref_fields));

    // Get the eventual ajax user input of the specific taxonomy field.
    $user_input = $form_state->getUserInput();
    $user_input_taxonomy_field = isset($user_input['style_options']) && isset($user_input['style_options']['map_marker_and_infowindow']['theming']['geofieldmap_taxonomy_term']['values']['taxonomy_field']) ?
      $user_input['style_options']['map_marker_and_infowindow']['theming']['geofieldmap_taxonomy_term']['values']['taxonomy_field'] : NULL;

    $selected_taxonomy_field = isset($user_input_taxonomy_field) ? $user_input_taxonomy_field : $default_taxonomy_field;

    $element = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="taxonomy-themer-wrapper">',
      '#suffix' => '</div>',
    ];

    if (!count($taxonomy_ref_fields) > 0) {
      $element['taxonomy_field'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('At least a Taxonomy Term reference field (<u>with a cardinality of 1</u>) should be added to the View to use this Map Theming option.'),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ];
    }
    else {
      $element['taxonomy_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Taxonomy Field'),
        '#description' => $this->t('Chose the Taxonomy Term reference field to base the Map Theming upon <br>(only the ones <u>with a cardinality of 1</u> are available for theming).'),
        '#options' => array_combine(array_keys($taxonomy_ref_fields), array_keys($taxonomy_ref_fields)),
        '#default_value' => $selected_taxonomy_field,
        '#ajax' => [
          'callback' => [static::class, 'taxonomyFieldOptionsUpdate'],
          'effect' => 'fade',
        ],
      ];

      $element['taxonomy_field']['fields'] = [];
      foreach ($taxonomy_ref_fields as $k => $field) {

        $caption = [
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#value' => $this->t('Taxonomy terms from @vocabularies', [
              '@vocabularies' => implode(', ', $field['target_bundles']),
            ]),
            'notes' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => $this->t('The - Default Value - will be used as fallback Value/Marker for unset Terms'),
              '#attributes' => [
                'style' => ['style' => 'font-size:0.8em; color: gray; font-weight: normal'],
              ],
            ],
          ],
        ];

        $label_alias_upload_help = $this->getLabelAliasHelp();
        $file_upload_help = $this->markerIcon->getFileUploadHelp();

        // Define the Table header.
        $element['fields'][$k] = [
          '#type' => 'container',
          'terms' => [
            '#type' => 'table',
            '#header' => [
              $this->t('Taxonomy term'),
              $this->t('Weight'),
              Markup::create($this->t('Term Alias @description', [
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
                'group' => 'terms-order-weight',
              ],
            ],
            '#caption' => $this->renderer->renderPlain($caption),
          ],
        ];

        // Add a Default Value to be used as possible fallback Value/Marker.
        $field['terms']['__default_value__'] = '- Default Value - ';

        // Reorder the taxonomy terms options, if already set.
        if (!empty($default_element) && isset($default_element['fields'][$k]['terms'])) {
          foreach ($default_element['fields'][$k]['terms'] as $id => $value) {
            if (isset($field['terms'][$id])) {
              $ordered_terms_options[$id] = $field['terms'][$id];
            }
          }
        }
        else {
          $ordered_terms_options = $field['terms'];
        }

        $i = 0;
        foreach ($ordered_terms_options as $tid => $term) {
          $fid = (integer) !empty($default_element['fields'][$k]['terms'][$tid]['icon_file']['fids']) ? $default_element['fields'][$k]['terms'][$tid]['icon_file']['fids'] : NULL;
          $element['fields'][$k]['terms'][$tid] = [
            'label' => [
              '#type' => 'value',
              '#value' => $term,
              'markup' => [
                '#markup' => $term,
              ],
            ],
            'weight' => [
              '#type' => 'weight',
              '#title_display' => 'invisible',
              '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['weight']) ? $default_element['fields'][$k]['terms'][$tid]['weight'] : $i,
              '#delta' => 20,
              '#attributes' => ['class' => ['terms-order-weight']],
            ],
            'label_alias' => [
              '#type' => 'textfield',
              '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['label_alias']) ? $default_element['fields'][$k]['terms'][$tid]['label_alias'] : '',
              '#size' => 30,
              '#maxlength' => 128,
            ],
            'icon_file' => $this->markerIcon->getIconFileManagedElement($fid),
            'image_style' => [
              '#type' => 'select',
              '#title' => t('Image style'),
              '#title_display' => 'invisible',
              '#options' => $this->markerIcon->getImageStyleOptions(),
              '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['image_style']) ? $default_element['fields'][$k]['terms'][$tid]['image_style'] : 'geofield_map_default_icon_style',
            ],
            'legend_exclude' => [
              '#type' => 'checkbox',
              '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['legend_exclude']) ? $default_element['fields'][$k]['terms'][$tid]['legend_exclude'] : '0',
            ],
            '#attributes' => ['class' => ['draggable']],
          ];

          $i++;
        }

        // Hide the un-selected Taxonomy Term Field options.
        if ($k != $selected_taxonomy_field) {
          $element['fields'][$k]['#attributes']['class'] = ['hidden'];
        }

      }
    }
    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {
    $taxonomy_field = isset($map_theming_values['taxonomy_field']) ? $map_theming_values['taxonomy_field'] : NULL;
    $fallback_icon_style = isset($map_theming_values['fields'][$taxonomy_field]['terms']['__default_value__']['image_style']) ? $map_theming_values['fields'][$taxonomy_field]['terms']['__default_value__']['image_style'] : NULL;
    $fallback_icon = isset($map_theming_values['fields'][$taxonomy_field]['terms']['__default_value__']['icon_file']) ? $map_theming_values['fields'][$taxonomy_field]['terms']['__default_value__']['icon_file']['fids'] : NULL;
    $image_style = $fallback_icon_style;
    $fid = $fallback_icon;
    if (isset($entity->{$taxonomy_field}) && !empty($entity->{$taxonomy_field}->target_id)) {
      $taxonomy_field_term = $entity->{$taxonomy_field}->target_id;
      $image_style = isset($map_theming_values['fields'][$taxonomy_field]['terms'][$taxonomy_field_term]['image_style']) ? $map_theming_values['fields'][$taxonomy_field]['terms'][$taxonomy_field_term]['image_style'] : $fallback_icon_style;
      $fid = isset($map_theming_values['fields'][$taxonomy_field]['terms'][$taxonomy_field_term]['icon_file']) && !empty($map_theming_values['fields'][$taxonomy_field]['terms'][$taxonomy_field_term]['icon_file']['fids']) ? $map_theming_values['fields'][$taxonomy_field]['terms'][$taxonomy_field_term]['icon_file']['fids'] : $fallback_icon;
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
        isset($configuration['values_label']) ? $configuration['values_label'] : $this->t('Term'),
        isset($configuration['markers_label']) ? $configuration['markers_label'] : $this->t('Marker/Icon'),
      ],
      '#caption' => isset($configuration['legend_caption']) ? $configuration['legend_caption'] : '',
      '#attributes' => [
        'class' => ['geofield-map-legend', 'taxonomy-term'],
      ],
    ];

    $taxonomy_field = $map_theming_values['taxonomy_field'];

    foreach ($map_theming_values['fields'][$taxonomy_field]['terms'] as $vid => $term) {

      // Get the icon image style, as result of the Legend configuration.
      $image_style = isset($configuration['markers_image_style']) ? $configuration['markers_image_style'] : 'none';
      // Get the map_theming_image_style, is so set.
      if (isset($configuration['markers_image_style']) && $configuration['markers_image_style'] == '_map_theming_image_style_') {
        $image_style = isset($map_theming_values['fields'][$taxonomy_field]['terms'][$vid]['image_style']) ? $map_theming_values['fields'][$taxonomy_field]['terms'][$vid]['image_style'] : 'none';
      }
      $fid = (integer) !empty($term['icon_file']['fids']) ? $term['icon_file']['fids'] : NULL;

      // Don't render legend row in case:
      // - the specific value is flagged as excluded from the Legend, or
      // - no image is associated and the plugin denies to render the
      // DefaultLegendIcon definition.
      if (!empty($term['legend_exclude']) || (empty($fid) && !$this->renderDefaultLegendIcon())) {
        continue;
      }
      $label = isset($term['label']) ? $term['label'] : $vid;
      $legend[$vid] = [
        'value' => [
          '#type' => 'container',
          'label' => [
            '#markup' => !empty($term['label_alias']) ? $term['label_alias'] : $label,
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

  /**
   * Ajax callback triggered Taxonomy Field Options Selection.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response with updated form element.
   */
  public static function taxonomyFieldOptionsUpdate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#taxonomy-themer-wrapper',
      $form['options']['style_options']['map_marker_and_infowindow']['theming']['geofieldmap_taxonomy_term']['values']
    ));
    return $response;
  }

}
