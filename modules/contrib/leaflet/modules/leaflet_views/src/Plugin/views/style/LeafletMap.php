<?php

namespace Drupal\leaflet_views\Plugin\views\style;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Leaflet\LeafletService;
use Drupal\Component\Utility\Html;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\leaflet\LeafletSettingsElementsTrait;
use Drupal\views\Plugin\views\PluginBase;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "leaflet_map",
 *   title = @Translation("Leaflet Map"),
 *   help = @Translation("Displays a View as a Leaflet map."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 */
class LeafletMap extends StylePluginBase implements ContainerFactoryPluginInterface {

  use LeafletSettingsElementsTrait;

  /**
   * The Entity type property.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Entity Info service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityInfo;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

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
   * Leaflet service.
   *
   * @var \Drupal\Leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * Constructs a LeafletMap style instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display
   *   The entity display manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepositoryInterface $entity_display,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    LeafletService $leaflet_service,
    LinkGeneratorInterface $link_generator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplay = $entity_display;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->leafletService = $leaflet_service;
    $this->link = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('leaflet.service'),
      $container->get('link_generator')
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
          $this->entityInfo = $this->entityManager->getDefinition($this->entityType);
        }
      }
    }
  }

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

    $form['#tree'] = TRUE;

    // Get a list of fields and a sublist of geo data fields in this view.
    $fields = [];
    $fields_geo_data = [];
    /* @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $fields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /* @var \Drupal\views\Plugin\views\field\EntityField $handler */
        $field_storage_definitions = $this->entityFieldManager
          ->getFieldStorageDefinitions($handler->getEntityType());
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        if ($field_storage_definition->getType() == 'geofield') {
          $fields_geo_data[$field_id] = $label;
        }
      }
    }

    // Check whether we have a geo data field we can work with.
    if (!count($fields_geo_data)) {
      $form['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Please add at least one Geofield to the View and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['leaflet-warning'],
        ],
        '#attached' => [
          'library' => [
            'leaflet/general',
          ],
        ],
      ];
      return;
    }

    // Map preset.
    $form['data_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which field contains geodata?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
    ];

    // Name field.
    $form['name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#description' => $this->t('Choose the field which will appear as a title on tooltips.'),
      '#options' => array_merge(['' => ''], $fields),
      '#default_value' => $this->options['name_field'],
    ];

    $desc_options = array_merge(['' => ''], $fields);
    // Add an option to render the entire entity using a view mode.
    if ($this->entityType) {
      $desc_options += [
        '#rendered_entity' => $this->t('< @entity entity >', ['@entity' => $this->entityType]),
        '#rendered_entity_ajax' => $this->t('< @entity entity via ajax >', ['@entity' => $this->entityType]),
      ];
    }

    $form['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description Field'),
      '#description' => $this->t('Choose the field or rendering method which will appear as a description on tooltips or popups.'),
      '#required' => FALSE,
      '#options' => $desc_options,
      '#default_value' => $this->options['description_field'],
    ];

    if ($this->entityType) {

      // Get the human readable labels for the entity view modes.
      $view_mode_options = [];
      foreach ($this->entityDisplay->getViewModes($this->entityType) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $form['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View modes are ways of displaying entities.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($this->options['view_mode']) ? $this->options['view_mode'] : 'full',
        '#states' => [
          'visible' => [
            ':input[name="style_options[description_field]"]' => [
              ['value' => '#rendered_entity'],
              ['value' => '#rendered_entity_ajax'],
            ],
          ],
        ],
      ];
    }

    // Generate the Leaflet Map General Settings.
    $this->generateMapGeneralSettings($form, $this->options);

    // Generate the Leaflet Map Position Form Element.
    $map_position_options = $this->options['map_position'];
    $form['map_position'] = $this->generateMapPositionElement($map_position_options);

    // Generate Icon form element.
    $icon_options = $this->options['icon'];
    $form['icon'] = $this->generateIconFormElement($icon_options);

    // Set Map Marker Cluster Element.
    $this->setMapMarkerclusterElement($form, $this->options);

    // Set Map Geometries Options Element.
    $this->setMapPathOptionsElement($form, $this->options);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $style_options = $form_state->getValue('style_options');
    if (!empty($style_options['height']) && (!is_numeric($style_options['height']) || $style_options['height'] <= 0)) {
      $form_state->setError($form['height'], $this->t('Map height needs to be a positive number.'));
    }
    $icon_options = isset($style_options['icon']) ? $style_options['icon'] : [];
    if (!empty($icon_options['iconSize']['x']) && (!is_numeric($icon_options['iconSize']['x']) || $icon_options['iconSize']['x'] <= 0)) {
      $form_state->setError($form['icon']['iconSize']['x'], $this->t('Icon width needs to be a positive number.'));
    }
    if (!empty($icon_options['iconSize']['y']) && (!is_numeric($icon_options['iconSize']['y']) || $icon_options['iconSize']['y'] <= 0)) {
      $form_state->setError($form['icon']['iconSize']['y'], $this->t('Icon height needs to be a positive number.'));
    }
  }

  /**
   * Renders the View.
   */
  public function render() {
    // Performs some preprocess on the leaflet map settings.
    $this->leafletService->preProcessMapSettings($this->options);

    $data = [];

    // Always render the map, otherwise ...
    $leaflet_map_style = !isset($this->options['leaflet_map']) ? $this->options['map'] : $this->options['leaflet_map'];
    $map = leaflet_map_get_info($leaflet_map_style);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $this->options);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("leaflet_map_view_" . $this->view->id() . '_' . $this->view->current_display);

    if ($geofield_name = $this->options['data_source']) {
      $this->renderFields($this->view->result);

      /* @var \Drupal\views\ResultRow $result */
      foreach ($this->view->result as $id => $result) {

        // For proper processing make sure the geofield_value is created as
        // an array, also if single value.
        $geofield_value = (array) $this->getFieldValue($result->index, $geofield_name);

        if (!empty($geofield_value)) {

          $points = $this->leafletService->leafletProcessGeofield($geofield_value);

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

          // Render the entity with the selected view mode.
          if (isset($entity)) {

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

            switch ($this->options['description_field']) {
              case '#rendered_entity':
                $build = $this->entityManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $this->options['view_mode'], $langcode);
                $description = $this->renderer->renderPlain($build);
                break;

              case '#rendered_entity_ajax':
                $parameters = [
                  'entity_type' => $entity->getEntityTypeId(),
                  'entity' => $entity->id(),
                  'view_mode' => $this->options['view_mode'],
                  'langcode' => $langcode,
                ];
                $url = Url::fromRoute('leaflet_views.ajax_popup', $parameters, ['absolute' => TRUE]);
                $description = sprintf('<div class="leaflet-ajax-popup" data-leaflet-ajax-popup="%s"></div>', $url->toString());
                break;

              default:
                // Normal rendering via fields.
                $description = !empty($this->options['description_field']) ? $this->rendered_fields[$result->index][$this->options['description_field']] : '';
            }

          }

          // Attach pop-ups if we have a description field.
          if (isset($description)) {
            foreach ($points as &$point) {
              $point['popup'] = $description;
            }
          }

          // Attach also titles, they might be used later on.
          if ($this->options['name_field']) {
            foreach ($points as &$point) {
              // Decode any entities because JS will encode them again and we
              // don't want double encoding.
              $point['label'] = !empty($this->options['name_field']) ? Html::decodeEntities(($this->rendered_fields[$result->index][$this->options['name_field']])) : '';
            }
          }

          // Add/merge eventual map icon definition from hook_leaflet_map_info.
          if (!empty($map['icon'])) {
            $this->options['icon'] = $this->options['icon'] ?: [];
            // Remove empty icon options so that they might be replaced by the
            // ones set by the hook_leaflet_map_info.
            foreach ($this->options['icon'] as $k => $icon_option) {
              if (empty($icon_option) || (is_array($icon_option) && $this->leafletService::multipleEmpty($icon_option))) {
                unset($this->options['icon'][$k]);
              }
            }
            $this->options['icon'] = array_replace($map['icon'], $this->options['icon']);
          }

          // Attach iconUrl properties to each point.
          if (!empty($this->options['icon']) && !empty($this->options['icon']['iconUrl'])) {
            $tokens = [];
            foreach ($this->rendered_fields[$result->index] as $field_name => $field_value) {
              $tokens[$field_name] = $field_value;
            }
            foreach ($points as &$point) {
              if (!empty($this->options['icon']['iconUrl'])) {
                $point['icon'] = $this->options['icon'];
                $point['icon']['iconUrl'] = $this->viewsTokenReplace($this->options['icon']['iconUrl'], $tokens);
                if (!empty($this->options['icon']['shadowUrl'])) {
                  $point['icon']['shadowUrl'] = $this->viewsTokenReplace($this->options['icon']['shadowUrl'], $tokens);
                }
              }
            }
          }

          foreach ($points as &$point) {
            // Allow modules to adjust the marker.
            \Drupal::moduleHandler()
              ->alter('leaflet_views_feature', $point, $result, $this->view->rowPlugin);
          }
          // Add new points to the whole basket.
          $data = array_merge($data, $points);
        }
      }
    }

    // Don't render the map, if we do not have any data
    // and the hide option is set.
    if (empty($data) && !empty($this->options['hide_empty_map'])) {
      return [];
    }

    $js_settings = [
      'map' => $map,
      'features' => $data,
    ];

    // Allow other modules to add/alter the map js settings.
    $this->moduleHandler->alter('leaflet_map_view_style', $js_settings, $this);

    return $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $this->options['height'] . 'px');
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

    $leaflet_map_default_settings = [];
    foreach (self::getDefaultSettings() as $k => $setting) {
      $leaflet_map_default_settings[$k] = ['default' => $setting];
    }
    return $options + $leaflet_map_default_settings;
  }

}
