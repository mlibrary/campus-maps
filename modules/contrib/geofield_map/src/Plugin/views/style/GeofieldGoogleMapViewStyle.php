<?php

namespace Drupal\geofield_map\Plugin\views\style;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\geofield_map\GeofieldMapFormElementsValidationTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Entity\Index;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\DefaultStyle;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\geofield_map\Services\GoogleMapsService;
use Drupal\geofield_map\MapThemerPluginManager;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "geofield_google_map",
 *   title = @Translation("Geofield Google Map"),
 *   help = @Translation("Displays a View as a Geofield Google Map."),
 *   display_types = {"normal"},
 *   theme = "geofield-google-map"
 * )
 */
class GeofieldGoogleMapViewStyle extends DefaultStyle implements ContainerFactoryPluginInterface {

  use GeofieldMapFieldTrait;
  use GeofieldMapFormElementsValidationTrait;

  /**
   * Empty Map Options.
   *
   * @var array
   */
  protected $emptyMapOptions = [
    '0' => 'View No Results Behaviour',
    '1' => 'Empty Map Centered at the Default Center',
  ];

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Entity type property.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Entity Info service property.
   *
   * @var string
   */
  protected $entityInfo;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Entity Field manager service property.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity Display Repository service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The geofieldMapGoogleMaps service.
   *
   * @var \Drupal\geofield_map\Services\GoogleMapsService
   */
  protected $googleMapsService;

  /**
   * The list of fields added to the view.
   *
   * @var array
   */
  protected $viewFields = [];

  /**
   * The MapThemer Manager service .
   *
   * @var \Drupal\geofield_map\MapThemerPluginManager
   */
  protected $mapThemerManager;

  /**
   * The list of available MapThemers Plugins.
   *
   * @var array
   */
  protected $mapThemersList;

  /**
   * Constructs a GeofieldGoogleMapView style instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display
   *   The entity display manager.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The The geoPhpWrapper.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\geofield_map\Services\GoogleMapsService $google_maps_service
   *   The Google Maps service.
   * @param \Drupal\geofield_map\MapThemerPluginManager $map_themer_manager
   *   The mapThemerManager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepositoryInterface $entity_display,
    LinkGeneratorInterface $link_generator,
    GeoPHPInterface $geophp_wrapper,
    AccountInterface $current_user,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    GoogleMapsService $google_maps_service,
    MapThemerPluginManager $map_themer_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplay = $entity_display;
    $this->config = $config_factory;
    $this->link = $link_generator;
    $this->geoPhpWrapper = $geophp_wrapper;
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->googleMapsService = $google_maps_service;
    $this->mapThemerManager = $map_themer_manager;
    $this->mapThemersList = $this->mapThemerManager->getMapThemersList();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('link_generator'),
      $container->get('geofield.geophp'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('geofield_map.google_maps'),
      $container->get('plugin.manager.geofield_map.themer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // For later use, set entity info related to the View's base table.
    $base_tables = array_keys($view->getBaseTables());
    $base_table = reset($base_tables);
    foreach ($this->entityManager->getDefinitions() as $key => $info) {
      if ($info->getDataTable() == $base_table) {
        $this->entityType = $key;
        $this->entityInfo = $info;
        return;
      }
    }
    // Set entity info for Search API views.
    if ($this->moduleHandler->moduleExists('search_api') && substr($base_table, 0, 17) === 'search_api_index_') {
      $index_id = substr($base_table, 17);
      $index = Index::load($index_id);
      foreach ($index->getDatasources() as $datasource) {
        if ($datasource instanceof DatasourceInterface) {
          $this->entityType = $datasource->getEntityTypeId();
          try {
            $this->entityInfo = $this->entityManager->getDefinition($this->entityType);
          }
          catch (\Exception $e) {
            watchdog_exception('geofield_map', $e);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = FALSE;

  /**
   * Does the style plugin support grouping of rows.
   *
   * @var bool
   */
  protected $usesGrouping = FALSE;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * Should field labels be enabled by default.
   *
   * @var bool
   */
  protected $defaultFieldLabels = TRUE;

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    // Render map even if there is no data.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $default_settings = self::defineOptions();

    $bundles = $this->getViewFilteredBundles();

    // Define the $form_state storage.
    $form_state_storage = ['entityType' => $this->entityType];
    // Add specific filtered entity types/bundles (if existing) into the
    // form_state storage.
    if (isset($bundles)) {
      $form_state_storage['bundles'] = $bundles;
    }

    $form['#attached'] = [
      'library' => [
        'geofield_map/geofield_map_general',
        'geofield_map/geofield_map_view_display_settings',
      ],
    ];

    // Set the form 'view_settings' property to be used later.
    // In the map themer ajax callback.
    $form['view_settings'] = [
      '#type' => 'value',
      '#value' => $form_state_storage,
    ];

    // Get a list of fields and a sublist of geo data fields in this view.
    $fields_geo_data = [];
    /* @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $this->viewFields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /* @var \Drupal\views\Plugin\views\field\EntityField $handler */
        try {
          $entity_type = $handler->getEntityType();
        }
        catch (\Exception $e) {
          $entity_type = NULL;
        }
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        if ($field_storage_definition->getType() == 'geofield') {
          $fields_geo_data[$field_id] = $label;
        }
      }
    }

    // Check whether we have a geo data field we can work with.
    if (empty($fields_geo_data)) {
      $form['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Please add at least one Geofield to the View and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ];
      return;
    }

    // Map data source.
    $form['data_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which field contains geodata?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
    ];

    $desc_options = array_merge(['0' => $this->t('- Any - No Infowindow')], $this->viewFields);
    // Add an option to render the entire entity using a view mode.
    if ($this->entityType) {
      $desc_options += [
        '#rendered_entity' => $this->t('- Rendered @entity entity -', ['@entity' => $this->entityType]),
        '#rendered_entity_ajax' => $this->t('- Rendered @entity entity via Ajax (Quicker Map start / Slower Infowindow show) -', ['@entity' => $this->entityType]),
      ];
    }

    $this->options['infowindow_content_options'] = $desc_options;

    if ($this->entityType) {

      // Get the human readable labels for the entity view modes.
      $view_mode_options = [];
      foreach ($this->entityDisplay->getViewModes($this->entityType) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $form['view_mode'] = [
        '#fieldset' => 'map_marker_and_infowindow',
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Infowindow.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($this->options['view_mode']) ? $this->options['view_mode'] : 'full',
        '#states' => [
          'visible' => [
            ':input[name="style_options[map_marker_and_infowindow][infowindow_field]"]' => [
              ['value' => '#rendered_entity'],
              ['value' => '#rendered_entity_ajax'],
            ],
          ],
        ],
      ];
    }

    $form += $this->generateGmapSettingsForm($form, $form_state, $this->options, $default_settings);

    $form['map_marker_and_infowindow']['infowindow_field']['#description'] .= $this->t("<br>'Rendered @entity entity' option: quick infowindow, but slower map start in case of many map features (> 50 / 100) and large infowindows content.<br>'Rendered @entity entity via ajax' option: quicker map start in case of many map features and large infowindows content.", [
      '@entity' => $this->entityType,
    ]);

    // Implement Map Theming based on available GeofieldMapThemers.
    $form['map_marker_and_infowindow']['theming'] = [
      '#type' => 'fieldset',
      '#title' => 'Map Theming Options',
      '#weight' => isset($form['map_marker_and_infowindow']['icon_image_path']['#weight']) ? $form['map_marker_and_infowindow']['icon_image_path']['#weight'] - 5 : -15,
      '#attributes' => ['id' => 'map-theming-container'],
    ];

    $map_themers_definitions = $this->mapThemerManager->getDefinitions();
    uasort($map_themers_definitions, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    $map_themers_options = array_merge(['none' => 'None'], $this->mapThemerManager->getMapThemersList('ViewStyle'));

    $user_input = $form_state->getUserInput();
    $map_themer_id = isset($user_input['style_options']['map_marker_and_infowindow']['theming']['plugin_id']) ? $user_input['style_options']['map_marker_and_infowindow']['theming']['plugin_id'] : NULL;
    $default_map_themer = isset($this->options['map_marker_and_infowindow']['theming']['plugin_id']) ? $this->options['map_marker_and_infowindow']['theming']['plugin_id'] : 'none';
    $selected_map_themer = !empty($map_themer_id) ? $map_themer_id : $default_map_themer;

    $plugin_id_warning = [
      'deprecated' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Deprecated.'),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ],
      'message'  => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Note: This is not compatible with D8 configuration management & sync.'),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ],
    ];

    $form['map_marker_and_infowindow']['theming']['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Map Theming'),
      '#default_value' => $selected_map_themer,
      '#options' => $map_themers_options,
      '#ajax' => [
        'callback' => [get_class($this), 'mapThemingOptionsUpdate'],
        'effect' => 'fade',
      ],
    ];

    if ($selected_map_themer != 'none') {
      $form['map_marker_and_infowindow']['theming']['plugin_id_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $map_themers_definitions[$selected_map_themer]['description'],
      ];
      if (!$map_themers_definitions[$selected_map_themer]['markerIconSelection']['configSyncCompatibility']) {
        $form['map_marker_and_infowindow']['theming']['plugin_id_warning'] = $plugin_id_warning['message'];
      }

      try {
        $map_themer_plugin = $this->mapThemerManager->createInstance($selected_map_themer);
        $form['map_marker_and_infowindow']['theming'][$map_themer_plugin->pluginId] = [
          '#type' => 'container',
          'id' => [
            '#type' => 'value',
            '#value' => $map_themer_plugin->getPluginId(),
          ],
          'values' => $map_themer_plugin->buildMapThemerElement($this->options, $form, $form_state, $this),
          'description' => [
            '#type' => 'value',
            '#value' => $map_themer_plugin->getDescription(),
          ],
        ];
      }
      catch (PluginException $e) {
        $form['map_marker_and_infowindow']['theming']['plugin_id']['#default_value'] = $map_themers_options['none'];
      }

    }

    $form['map_marker_and_infowindow']['theming']['plugins_descriptions'] = [
      '#type' => 'container',
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Available Map Themers & Descriptions:'),
      ],
    ];

    foreach ($map_themers_definitions as $k => $map_themer) {
      $form['map_marker_and_infowindow']['theming']['plugins_descriptions']['table'][$k] = [
        'td1' => [
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $map_themers_options[$k],
          ],
          'warning' => !$map_themers_definitions[$k]['markerIconSelection']['configSyncCompatibility'] ? $plugin_id_warning['deprecated'] : [],
        ],
        'td2' => [
          '#type' => 'container',
          'description' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $map_themer['description'],
          ],
          'warning' => !$map_themers_definitions[$k]['markerIconSelection']['configSyncCompatibility'] ? $plugin_id_warning['message'] : [],
        ],
      ];
    }

    // Hide fall-backs in case the user choose a map themer.
    if ('none' != $selected_map_themer) {
      // Hide the Map Themers Plugins Descriptions.
      $form['map_marker_and_infowindow']['theming']['plugins_descriptions']['#attributes']['class'] = ['hidden'];

      // Hide the icon_image_path element, with prefix/suffix (as hidden would
      // hide just the textfield and not label/title and description wrappers).
      $form['map_marker_and_infowindow']['icon_image_path']['#prefix'] = '<div id="icon-image-path" class="visually-hidden">';
      $form['map_marker_and_infowindow']['icon_image_path']['#suffix'] = '</div>';
    }

  }

  /**
   * Ajax callback triggered Map Theming Option Selection.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response with updated form element.
   */
  public static function mapThemingOptionsUpdate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(
      '#map-marker-and-infowindow-wrapper',
      $form['options']['style_options']['map_marker_and_infowindow']
    ));
    return $response;
  }

  /**
   * Renders the View.
   */
  public function render() {

    $map_settings = $this->options;
    $element = [];

    // Performs some preprocess on the maps settings before sending to js.
    $this->preProcessMapSettings($map_settings);

    $js_settings = [
      'mapid' => Html::getUniqueId("geofield_map_view_" . $this->view->id() . '_' . $this->view->current_display),
      'map_settings' => $map_settings,
      'data' => [],
    ];

    $data = [];

    // Get the Geofield field.
    $geofield_name = $map_settings['data_source'];

    // If the Geofield field is null, output a warning
    // to the Geofield Map administrator.
    if (empty($geofield_name) && $this->currentUser->hasPermission('configure geofield_map')) {
      $element = [
        '#markup' => '<div class="geofield-map-warning">' . $this->t("The Geofield field hasn't not been correctly set for this View. <br>Add at least one Geofield to the View and set it as Data Source in the Geofield Google Map View Display Settings.") . "</div>",
        '#attached' => [
          'library' => ['geofield_map/geofield_map_general'],
        ],
      ];
    }

    // It the Geofield field is not null, and there are results or a not null
    // empty behaviour has been set, render the results.
    if (!empty($geofield_name) && (!empty($this->view->result) || $map_settings['map_empty']['empty_behaviour'] == '1')) {
      $this->renderFields($this->view->result);

      /* @var \Drupal\views\ResultRow  $result */
      foreach ($this->view->result as $id => $result) {

        // For proper processing make sure the geofield_value is created as an
        // array, also if single value.
        $geofield_value = (array) $this->getFieldValue($id, $geofield_name);

        // In case the result is not null.
        if (!empty($geofield_value)) {

          if (!empty($result->_entity)) {
            // Entity API provides a plain entity object.
            $entity = $result->_entity;
          }
          elseif (isset($result->_object)) {
            // Search API provides a TypedData EntityAdapter.
            $entity_adapter = $result->_object;
            if ($entity_adapter instanceof EntityAdapter) {
              $entity = $entity_adapter->getValue();
            }
          }

          $description = [];
          $description_field = isset($map_settings['map_marker_and_infowindow']['infowindow_field']) ? $map_settings['map_marker_and_infowindow']['infowindow_field'] : NULL;

          // Render the entity with the selected view mode.
          if (isset($entity) && isset($description_field)) {

            /* @var \Drupal\Core\Field\FieldItemList $description_field_entity */
            $description_field_entity = $entity->$description_field;

            $entity_type = $entity->getEntityTypeId();
            $entity_type_langcode_attribute = $entity_type . '_field_data_langcode';

            $view = $this->view;

            // Set the langcode to be used for rendering the entity.
            $rendering_language = $view->display_handler->getOption('rendering_language');
            $dynamic_renderers = [
              '***LANGUAGE_entity_translation***' => 'TranslationLanguageRenderer',
              '***LANGUAGE_entity_default***' => 'DefaultLanguageRenderer',
            ];
            if (isset($dynamic_renderers[$rendering_language])) {
              /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
              $langcode = isset($result->$entity_type_langcode_attribute) ? $result->$entity_type_langcode_attribute : $entity->language()->getId();
            }
            else {
              if (strpos($rendering_language, '***LANGUAGE_') !== FALSE) {
                $langcode = PluginBase::queryLanguageSubstitutions()[$rendering_language];
              }
              else {
                // Specific langcode set.
                $langcode = $rendering_language;
              }
            }

            switch ($description_field) {
              case '#rendered_entity':
                $build = $this->entityManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $this->options['view_mode'], $langcode);
                $description[] = $this->renderer->renderPlain($build);
                break;

              case '#rendered_entity_ajax':
                $parameters = [
                  'entity_type' => $entity->getEntityTypeId(),
                  'entity' => $entity->id(),
                  'view_mode' => $this->options['view_mode'],
                  'langcode' => $langcode,
                ];
                $url = Url::fromRoute('geofield_map.ajax_popup', $parameters, ['absolute' => TRUE]);
                $build = [
                  '#type' => 'html_tag',
                  '#tag' => 'div',
                  '#value' => '',
                  '#attributes' => [
                    'class' => ['geofield-google-map-ajax-popup'],
                    'data-geofield-google-map-ajax-popup' => $url->toString(),
                  ],
                ];
                $description[] = $this->renderer->renderRoot($build);
                break;

              default:
                // Check if the entity has a $description_field field.
                if (isset($description_field_entity)) {
                  $description_field_cardinality = $description_field_entity->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
                  foreach ($description_field_entity->getValue() as $value) {
                    if ($description_field_cardinality == 1 || $map_settings['map_marker_and_infowindow']['multivalue_split'] == FALSE) {
                      $description[] = $this->rendered_fields[$id][$description_field];
                      break;
                    }
                    $description[] = isset($value['value']) ? $value['value'] : NULL;
                  }
                }
                // Else get the views field value.
                elseif (isset($this->rendered_fields[$id][$description_field])) {
                  $description[] = $this->rendered_fields[$id][$description_field];
                }
            }
          }

          // Add Views fields to the Json output as additional_data property.
          $view_data = [];
          foreach ($this->rendered_fields[$id] as $field_name => $rendered_field) {
            if (!empty($rendered_field) && !$this->view->field[$field_name]->options['exclude']) {
              /* @var \Drupal\Core\Render\Markup $rendered_field */
              $view_data[$field_name] = $rendered_field->__toString();
            }
          }
          // Generate GeoJsonData.
          $geojson_data = $this->getGeoJsonData($geofield_value, $description, $view_data);

          // Add Theming Icon based on the $theming plugin.
          $theming = NULL;
          if (isset($map_settings['map_marker_and_infowindow']['theming']) && $map_settings['map_marker_and_infowindow']['theming']['plugin_id'] != 'none') {
            $theming = $map_settings['map_marker_and_infowindow']['theming'];
            try {
              /* @var \Drupal\geofield_map\MapThemerInterface $map_themer */
              $map_themer = $this->mapThemerManager->createInstance($theming['plugin_id'], ['geofieldMapView' => $this]);
              $map_theming = $theming[$map_themer->getPluginId()]['values'];
              foreach ($geojson_data as $k => $datum) {
                $geojson_data[$k]['properties']['icon'] = $map_themer->getIcon($datum, $this, $entity, $map_theming);
                // Flag the data with theming, for later rendering logic.
                $geojson_data[$k]['properties']['theming'] = TRUE;
              }
            }
            catch (PluginException $e) {
            }
          }

          // Generate incremental GeoJsonData.
          $data = array_merge($data, $geojson_data);

        }
      }

      $js_settings['data'] = [
        'type' => 'FeatureCollection',
        'features' => $data,
      ];

      // Allow other modules to add/alter the map js settings.
      $this->moduleHandler->alter('geofield_map_googlemap_view_style', $js_settings, $this);

      $element = geofield_map_googlemap_render($js_settings);

    }
    return $element;
  }

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = ['default' => ''];
    $options['name_field'] = ['default' => ''];
    $options['description_field'] = ['default' => ''];
    $options['view_mode'] = ['default' => 'full'];

    $geofield_google_map_default_settings = [];
    foreach (self::getDefaultSettings() as $k => $setting) {
      $geofield_google_map_default_settings[$k] = ['default' => $setting];

      // Define defaults for existing map themers.
      if ($k == 'map_marker_and_infowindow') {
        $geofield_google_map_default_settings[$k]['default']['theming']['plugin_id'] = NULL;
        foreach ($this->mapThemersList as $id => $map_themer) {
          $geofield_google_map_default_settings[$k]['default']['theming'][$id]['values'] = [];
        }
      }
    }

    return $options + $geofield_google_map_default_settings;
  }

  /**
   * Get the defined list of Fields added to the View.
   *
   * @return array
   *   The list of Fields names.
   */
  public function getViewFields() {
    return $this->viewFields;
  }

  /**
   * Get View Entity Type.
   *
   * @return string
   *   The entity type name.
   */
  public function getViewEntityType() {
    return $this->entityType;
  }

  /**
   * Returns the Entity Field manager service property.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The Entity Field manager service property.
   */
  public function getEntityFieldManager() {
    return $this->entityFieldManager;
  }

  /**
   * Get the bundles defined as View Filter.
   */
  public function getViewFilteredBundles() {

    // Get the Views filters.
    $views_filters = $this->view->display_handler->getOption('filters');
    // Set the specific filtered entity types/bundles.
    if (!empty($views_filters['type'])) {
      $bundles = array_keys($views_filters['type']['value']);
    }

    return isset($bundles) ? $bundles : [];

  }

}
