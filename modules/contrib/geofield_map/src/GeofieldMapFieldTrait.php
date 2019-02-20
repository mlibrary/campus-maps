<?php

namespace Drupal\geofield_map;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GeofieldMapFieldTrait.
 *
 * Provide common functions for Geofield Map fields.
 *
 * @package Drupal\geofield_map
 */
trait GeofieldMapFieldTrait {

  /**
   * Google Map Types Options.
   *
   * @var array
   */
  protected $gMapTypesOptions = [
    'roadmap' => 'Roadmap',
    'satellite' => 'Satellite',
    'hybrid' => 'Hybrid',
    'terrain' => 'Terrain',
  ];

  /**
   * Google Map Types Options.
   *
   * @var array
   */
  protected $infowindowFieldTypesOptions = [
    'string_long',
    'string',
    'text',
    'text_long',
    "text_with_summary",
  ];

  protected $customMapStylePlaceholder = '[{"elementType":"geometry","stylers":[{"color":"#1d2c4d"}]},{"elementType":"labels.text.fill","stylers":[{"color":"#8ec3b9"}]},{"elementType":"labels.text.stroke","stylers":[{"color":"#1a3646"}]},{"featureType":"administrative.country","elementType":"geometry.stroke","stylers":[{"color":"#4b6878"}]},{"featureType":"administrative.province","elementType":"geometry.stroke","stylers":[{"color":"#4b6878"}]},{"featureType":"water","elementType":"geometry","stylers":[{"color":"#0e1626"}]},{"featureType":"water","elementType":"labels.text.fill","stylers":[{"color":"#4e6d70"}]},{"featureType":"poi","stylers":[{"visibility":"off"}]}]';

  /**
   * Get the GMap Api Key from the geofield_map.google_maps service.
   *
   * @return string
   *   The GMap Api Key
   */
  private function getGmapApiKey() {
    return \Drupal::service('geofield_map.google_maps')->getGmapApiKey();
  }

  /**
   * Get the Default Settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getDefaultSettings() {
    return [
      'gmap_api_key' => '',
      'map_dimensions' => [
        'width' => '100%',
        'height' => '450px',
      ],
      'map_empty' => [
        'empty_behaviour' => '0',
        'empty_message' => t('No Geofield Value entered for this field'),
      ],
      'map_center' => [
        'lat' => '42',
        'lon' => '12.5',
        'center_force' => 0,
      ],
      'map_zoom_and_pan' => [
        'zoom' => [
          'initial' => 6,
          'force' => 0,
          'min' => 1,
          'max' => 22,
        ],
        'scrollwheel' => 1,
        'draggable' => 1,
        'map_reset' => 0,
      ],
      'map_controls' => [
        'disable_default_ui' => 0,
        'zoom_control' => 1,
        'map_type_id' => 'roadmap',
        'map_type_control' => 1,
        'map_type_control_options_type_ids' => [
          'roadmap' => 'roadmap',
          'satellite' => 'satellite',
          'hybrid' => 'hybrid',
          'terrain' => 'terrain',
        ],
        'scale_control' => 1,
        'street_view_control' => 1,
        'fullscreen_control' => 1,
      ],
      'map_marker_and_infowindow' => [
        'icon_image_mode' => 'icon_file',
        'icon_image_path' => '',
        'icon_file_wrapper' => [
          'icon_file' => '',
        ],
        'infowindow_field' => 'title',
        'multivalue_split' => 0,
        'force_open' => 0,
      ],
      'map_oms' => [
        'map_oms_control' => 1,
        'map_oms_options' => '{"markersWontMove": "true", "markersWontHide": "true", "basicFormatEvents": "true", "nearbyDistance": 3}',
      ],
      'map_additional_options' => '',
      'map_geometries_options' => '{"strokeColor":"black","strokeOpacity":"0.8","strokeWeight":2,"fillColor":"blue","fillOpacity":"0.1", "clickable": false}',
      'custom_style_map' => [
        'custom_style_control' => 0,
        'custom_style_name' => '',
        'custom_style_options' => '',
        'custom_style_default' => 0,
      ],
      'map_markercluster' => [
        'markercluster_control' => 0,
        'markercluster_additional_options' => '{"maxZoom":12, "gridSize":50}',
      ],
    ];
  }

  /**
   * Generate the Google Map Settings Form.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $settings
   *   Form settings.
   * @param array $default_settings
   *   Default settings.
   *
   * @return array
   *   The GMap Settings Form*/
  public function generateGmapSettingsForm(array $form, FormStateInterface $form_state, array $settings, array $default_settings) {

    $elements['#attached'] = [
      'library' => [
        'geofield_map/geofield_map_view_display_settings',
      ],
    ];

    $elements = [];

    // Attach Geofield Map Library.
    $elements['#attached']['library'] = [
      'geofield_map/geofield_map_general',
    ];

    // Set Google Api Key Element.
    $this->setMapGoogleApiKeyElement($elements);

    // Set Map Dimension Element.
    $this->setMapDimensionsElement($settings, $elements);

    // Set Map Empty Options Element.
    $this->setMapEmptyElement($settings, $elements);

    $elements['gmaps_api_link_markup'] = [
      '#markup' => $this->t('The following settings comply with the @gmaps_api_link.', [
        '@gmaps_api_link' => $this->link->generate($this->t('Google Maps JavaScript API Library'), Url::fromUri('https://developers.google.com/maps/documentation/javascript', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];

    // Set Map Center Element.
    $this->setMapCenterElement($settings, $elements);

    // Set Map Zoom and Pan Element.
    $this->setMapZoomAndPanElement($settings, $default_settings, $elements);

    // Set Map Control Element.
    $this->setMapControlsElement($settings, $elements);

    // Set Map Marker and Infowindow Element.
    $this->setMapMarkerAndInfowindowElement($form, $settings, $elements);

    // Set Map Additional Options Element.
    $this->setMapAdditionalOptionsElement($settings, $elements);

    // Set Map Geometries Options Element.
    $this->setGeometriesAdditionalOptionsElement($settings, $elements);

    // Set Overlapping Marker Spiderfier Element.
    $this->setMapOmsElement($settings, $default_settings, $elements);

    // Set Custom Map Style Element.
    $this->setCustomStyleMapElement($settings, $elements);

    // Set Map Marker Cluster Element.
    $this->setMapMarkerclusterElement($settings, $elements);

    return $elements;

  }

  /**
   * Pre Process the MapSettings.
   *
   * Performs some preprocess on the maps settings before sending to js.
   *
   * @param array $map_settings
   *   The map settings.
   */
  protected function preProcessMapSettings(array &$map_settings) {
    /* @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = $this->config;
    $geofield_map_settings = $config->getEditable('geofield_map.settings');

    // Set the gmap_api_key as map settings.
    $map_settings['gmap_api_key'] = $this->getGmapApiKey();

    // Geofield Map Google Maps and Geocoder Settings.
    $map_settings['gmap_api_localization'] = $this->googleMapsService->getGmapApiLocalization($geofield_map_settings->get('gmap_api_localization'));

    // Transform into simple array values the map_type_control_options_type_ids.
    $map_settings['map_controls']['map_type_control_options_type_ids'] = array_keys(array_filter($map_settings['map_controls']['map_type_control_options_type_ids'], function ($value) {
      return $value !== 0;
    }));

    // Generate Absolute icon_image_path, if it is not.
    $icon_image_path = $map_settings['map_marker_and_infowindow']['icon_image_path'];
    if (!empty($icon_image_path) && !UrlHelper::isExternal($map_settings['map_marker_and_infowindow']['icon_image_path'])) {
      $map_settings['map_marker_and_infowindow']['icon_image_path'] = Url::fromUri('base:' . $icon_image_path, ['absolute' => TRUE])
        ->toString();
    }
  }

  /**
   * Transform Geofield data into Geojson features.
   *
   * @param mixed $items
   *   The Geofield Data Values.
   * @param string $description
   *   The description value.
   * @param array $additional_data
   *   Additional data to be added to the feature properties, i.e.
   *   GeofieldGoogleMapViewStyle will add row fields (already rendered).
   *
   * @return array
   *   The data array for the current feature, including Geojson and additional
   *   data.
   */
  protected function getGeoJsonData($items, $description = NULL, array $additional_data = NULL) {
    $data = [];
    foreach ($items as $delta => $item) {

      /* @var \Point $geometry */
      if (is_a($item, '\Drupal\geofield\Plugin\Field\FieldType\GeofieldItem') && isset($item->value)) {
        $geometry = $this->geoPhpWrapper->load($item->value);
      }
      elseif (preg_match('/^(POINT|LINESTRING|POLYGON|MULTIPOINT|MULTILINESTRING|MULTIPOLYGON|GEOMETRYCOLLECTION).*\(.*.*\)$/', $item)) {
        $geometry = $this->geoPhpWrapper->load($item);
      }
      if (isset($geometry)) {
        $datum = [
          "type" => "Feature",
          "geometry" => json_decode($geometry->out('json')),
        ];
        $datum['properties'] = [
          // If a multivalue field value with the same index exist, use this,
          // else use the first item as fallback.
          'description' => isset($description[$delta]) ? $description[$delta] : (isset($description[0]) ? $description[0] : NULL),
          'data' => $additional_data,
        ];

        $data[] = $datum;
      }
    }
    return $data;
  }

  /**
   * Set Map Google Api Key Element.
   *
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapGoogleApiKeyElement(array &$elements) {
    $gmap_api_key = $this->getGmapApiKey();

    $query = [];
    if (isset($this->fieldDefinition)) {
      $query['destination'] = Url::fromRoute('<current>')->toString();
    }

    // Define the Google Maps API Key value message markup.
    if (!empty($gmap_api_key)) {
      $map_google_api_key_value = $this->t('<strong>Gmap Api Key:</strong> @gmaps_api_key_link<br><div class="description">A valid Gmap Api Key is needed anyway for the Widget Geocode and ReverseGeocode functionalities (provided by the Google Map Geocoder)</div>', [
        '@gmaps_api_key_link' => $this->link->generate($gmap_api_key, Url::fromRoute('geofield_map.settings', [], [
          'query' => $query,
        ])),
      ]);
    }
    else {
      $map_google_api_key_value = $this->t("<span class='geofield-map-warning'>Gmap Api Key missing - @settings_page_link<br>Google Maps functionalities not available. </span>", [
        '@settings_page_link' => $this->link->generate($this->t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
          'query' => [
            'destination' => Url::fromRoute('<current>')
              ->toString(),
          ],
        ])),
      ]);
    }

    $elements['map_google_api_key'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $map_google_api_key_value,
    ];
  }

  /**
   * Set Map Dimension Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapDimensionsElement(array $settings, array &$elements) {
    $elements['map_dimensions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Dimensions'),
    ];

    $elements['map_dimensions']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map width'),
      '#default_value' => $settings['map_dimensions']['width'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default width of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];
    $elements['map_dimensions']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map height'),
      '#default_value' => $settings['map_dimensions']['height'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default height of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];
  }

  /**
   * Set Map Empty Options Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapEmptyElement(array $settings, array &$elements) {
    $elements['map_empty'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Which behaviour for the empty map?'),
      '#description' => $this->t('If there are no entries on the map, what should be the output of field?'),
    ];

    if (isset($this->fieldDefinition)) {
      $elements['map_empty']['empty_behaviour'] = [
        '#type' => 'select',
        '#title' => $this->t('Behaviour'),
        '#default_value' => $settings['map_empty']['empty_behaviour'],
        '#options' => $this->emptyMapOptions,
      ];
      $elements['map_empty']['empty_message'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Empty Map Message'),
        '#description' => $this->t('The message that should be rendered instead on an empty map.'),
        '#default_value' => $settings['map_empty']['empty_message'],
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_empty][empty_behaviour]"]' => ['value' => '1'],
          ],
        ],
      ];
    }
    else {
      $elements['map_empty']['empty_behaviour'] = [
        '#type' => 'select',
        '#title' => $this->t('Behaviour'),
        '#default_value' => $settings['map_empty']['empty_behaviour'],
        '#options' => $this->emptyMapOptions,
      ];
    }
  }

  /**
   * Set Map Center Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapCenterElement(array $settings, array &$elements) {
    $elements['map_center'] = [
      '#type' => 'geofield_latlon',
      '#title' => $this->t('Default Center'),
      '#default_value' => $settings['map_center'],
      '#size' => 25,
      '#description' => $this->t('If there are no entries on the map, where should the map be centered?'),
      '#geolocation' => TRUE,
      'center_force' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force the Map Center'),
        '#description' => $this->t('The Map will generally focus center on the input Geofields.<br>This option will instead force the Map Center notwithstanding the Geofield Values'),
        '#default_value' => $settings['map_center']['center_force'],
        '#return_value' => 1,
      ],
    ];
  }

  /**
   * Set Map Zoom and Pan Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $default_settings
   *   The default_settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapZoomAndPanElement(array $settings, array $default_settings, array &$elements) {

    if (isset($this->fieldDefinition)) {
      $force_center_selector = ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_center][center_force]"]';
    }
    else {
      $force_center_selector = ':input[name="style_options[map_center][center_force]"]';
    }

    $elements['map_zoom_and_pan'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Zoom and Pan'),
    ];
    $elements['map_zoom_and_pan']['zoom'] = [
      'initial' => [
        '#type' => 'number',
        '#min' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Start Zoom'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['initial'],
        '#description' => $this->t('The Initial Zoom level of the Google Map.'),
        '#element_validate' => [[get_class($this), 'zoomLevelValidate']],
      ],
      'force' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force the Start Zoom'),
        '#description' => $this->t('In case of multiple GeoMarkers, the Map will naturally focus zoom on the input Geofields bounds.<br>This option will instead force the Map Zoom on the input Start Zoom value'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['force'],
        '#return_value' => 1,
        '#states' => [
          'visible' => [
            $force_center_selector => ['checked' => FALSE],
          ],
        ],
      ],
      'min' => [
        '#type' => 'number',
        '#min' => isset($default_settings['map_zoom_and_pan']['default']) ? $default_settings['map_zoom_and_pan']['default']['zoom']['min'] : $default_settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Min Zoom Level'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#description' => $this->t('The Minimum Zoom level for the Map.'),
      ],
      'max' => [
        '#type' => 'number',
        '#min' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => isset($default_settings['map_zoom_and_pan']['default']) ? $default_settings['map_zoom_and_pan']['default']['zoom']['max'] : $default_settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Max Zoom Level'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#description' => $this->t('The Maximum Zoom level for the Map.'),
        '#element_validate' => [[get_class($this), 'maxZoomLevelValidate']],
      ],
    ];
    $elements['map_zoom_and_pan']['gestureHandling'] = [
      '#type' => 'select',
      '#title' => $this->t('Gesture Handling (Controlling Zoom and Pan)'),
      '#options' => [
        'auto' => 'auto',
        'greedy' => 'greedy',
        'cooperative' => 'cooperative' ,
        'none' => 'none',
      ],
      '#default_value' => isset($settings['map_zoom_and_pan']['gestureHandling']) ? $settings['map_zoom_and_pan']['gestureHandling'] : 'auto',
      '#description' => $this->t("This control sets how users can zoom and pan the map, and also whether the user's page scrolling actions take priority over the map's zooming and panning.<br>Visit the @google_map_page to inspect and learn the corresponding behaviours of the different options.", [
        '@google_map_page' => $this->link->generate(t("Official Google Maps Javascript API 'Controlling Zoom and Pan' page"), Url::fromUri('https://developers.google.com/maps/documentation/javascript/interaction', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];
    $elements['map_zoom_and_pan']['scrollwheel'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['map_zoom_and_pan']['scrollwheel'],
    ];
    $elements['map_zoom_and_pan']['draggable'] = [
      '#type' => 'hidden',
      '#default_value' => $settings['map_zoom_and_pan']['draggable'],
    ];

    $elements['map_zoom_and_pan']['map_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Map Reset Control'),
      '#description' => $this->t('This will show a "Reset Map" button to reset the Map to its initial center & zoom state'),
      '#default_value' => isset($settings['map_zoom_and_pan']['map_reset']) ? $settings['map_zoom_and_pan']['map_reset'] : FALSE,
      '#return_value' => 1,
    ];
  }

  /**
   * Set Map Control Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapControlsElement(array $settings, array &$elements) {
    if (isset($this->fieldDefinition)) {
      $disable_default_ui_selector = ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_controls][disable_default_ui]"]';
    }
    else {
      $disable_default_ui_selector = ':input[name="style_options[map_controls][disable_default_ui]"]';
    }

    $elements['map_controls'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Controls'),
    ];
    $elements['map_controls']['disable_default_ui'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Default UI'),
      '#description' => $this->t('This property disables any automatic UI behavior and Control from the Google Map'),
      '#default_value' => $settings['map_controls']['disable_default_ui'],
      '#return_value' => 1,
    ];
    $elements['map_controls']['zoom_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Zoom Control'),
      '#description' => $this->t('The enabled/disabled state of the Zoom control.'),
      '#default_value' => $settings['map_controls']['zoom_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['map_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Map Type'),
      '#default_value' => $settings['map_controls']['map_type_id'],
      '#options' => $this->gMapTypesOptions,
    ];
    $elements['map_controls']['map_type_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled Map Type Control'),
      '#description' => $this->t('The initial enabled/disabled state of the Map type control.'),
      '#default_value' => $settings['map_controls']['map_type_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['map_type_control_options_type_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('The enabled Map Types'),
      '#description' => $this->t('The Map Types that will be available in the Map Type Control.'),
      '#default_value' => $settings['map_controls']['map_type_control_options_type_ids'],
      '#options' => $this->gMapTypesOptions,
      '#return_value' => 1,
    ];

    if (isset($this->fieldDefinition)) {
      $elements['map_controls']['map_type_control_options_type_ids']['#states'] = [
        'invisible' => [
          [':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_controls][map_type_control]"]' => ['checked' => FALSE]],
          [$disable_default_ui_selector => ['checked' => TRUE]],
        ],
      ];
    }
    else {
      $elements['map_controls']['map_type_control_options_type_ids']['#states'] = [
        'invisible' => [
          [':input[name="style_options[map_controls][map_type_control]"]' => ['checked' => FALSE]],
          [$disable_default_ui_selector => ['checked' => TRUE]],
        ],
      ];
    }

    $elements['map_controls']['scale_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scale Control'),
      '#description' => $this->t('Show map scale'),
      '#default_value' => $settings['map_controls']['scale_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['street_view_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Streetview Control'),
      '#description' => $this->t('Enable the Street View functionality on the Map.'),
      '#default_value' => $settings['map_controls']['street_view_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['fullscreen_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fullscreen Control'),
      '#description' => $this->t('Enable the Fullscreen View of the Map.'),
      '#default_value' => $settings['map_controls']['fullscreen_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
  }

  /**
   * Set Map Marker and Infowindow Element.
   *
   * @param array $form
   *   The Form array.
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapMarkerAndInfowindowElement(array $form, array $settings, array &$elements) {

    // Get the configurations of possible entity fields.
    $fields_configurations = $this->entityFieldManager->getFieldStorageDefinitions('node');

    $elements['map_marker_and_infowindow'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Marker and Infowindow'),
      '#prefix' => '<div id="map-marker-and-infowindow-wrapper">',
      '#suffix' => '</div>',
    ];
    $elements['map_marker_and_infowindow']['icon_image_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Image Path'),
      '#size' => '120',
      '#description' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root prefixed with a trailing hash). If not set, or not found/loadable, the Default Google Marker will be used.'),
      '#default_value' => $settings['map_marker_and_infowindow']['icon_image_path'],
      '#placeholder' => 'modules/custom/geofield_map/images/beachflag.png',
      '#element_validate' => [[get_class($this), 'urlValidate']],
      '#weight' => -10,
    ];

    // Add SVG UI file support.
    $elements['map_marker_and_infowindow']['icon_image_path']['#description'] .= !$this->moduleHandler->moduleExists('svg_image') ? '<br>' . $this->t('SVG Files support is disabled. Enabled it with @svg_image_link', [
      '@svg_image_link' => $this->link->generate('SVG Image Module', Url::fromUri('https://www.drupal.org/project/svg_image', [
        'absolute' => TRUE,
        'attributes' => ['target' => 'blank'],
      ])),
    ]) : '<br>' . $this->t('SVG Files support enabled.');

    $multivalue_fields_states = [];

    $infowindow_fields_options = [];
    foreach ($this->infowindowFieldTypesOptions as $field_type) {
      $infowindow_fields_options = array_merge_recursive($infowindow_fields_options, $this->entityFieldManager->getFieldMapByFieldType($field_type));
    }

    // In case it is a Field Formatter.
    if (isset($this->fieldDefinition)) {
      $desc_options = [
        '0' => $this->t('- Any - No Infowindow'),
        'title' => $this->t('- Title -'),
      ];

      // Get the Cardinality set for the Formatter Field.
      $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()
        ->getCardinality();

      foreach ($infowindow_fields_options[$form['#entity_type']] as $k => $field) {
        if (!empty(array_intersect($field['bundles'], [$form['#bundle']])) &&
          !in_array($k, ['title', 'revision_log'])) {
          $desc_options[$k] = $k;
          /* @var \\Drupal\Core\Field\BaseFieldDefinition $fields_configurations[$k] */
          if ($field_cardinality !== 1 && (isset($fields_configurations[$k]) && $fields_configurations[$k]->getCardinality() !== 1)) {
            $multivalue_fields_states[] = ['value' => $k];
          }
        }
      }

      $desc_options['#rendered_entity'] = $this->t('- Rendered @entity entity -', ['@entity' => $form['#entity_type']]);

      $info_window_source_options = $desc_options;

    }
    // Else it is a Geofield View Style Format Settings.
    else {
      $info_window_source_options = isset($settings['infowindow_content_options']) ? $settings['infowindow_content_options'] : [];

      foreach ($info_window_source_options as $k => $field) {
        /* @var \\Drupal\Core\Field\BaseFieldDefinition $fields_configurations[$k] */
        if (array_key_exists($k, $fields_configurations) && $fields_configurations[$k]->getCardinality() !== 1) {
          $multivalue_fields_states[] = ['value' => $k];
        }

        if (array_key_exists($k, $fields_configurations) && $fields_configurations[$k]->getCardinality() !== 1) {
          $multivalue_fields_states[] = ['value' => $k];
        }

        // Remove the fields options that are not string/text type fields
        // of the view entity type.
        if (isset($this->entityType) && substr($k, 0, 5) == 'field' && !array_key_exists($k, $infowindow_fields_options[$this->entityType])) {
          unset($info_window_source_options[$k]);
        }

      }

    }

    if (!empty($info_window_source_options)) {
      $elements['map_marker_and_infowindow']['infowindow_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Marker Infowindow Content from'),
        '#description' => $this->t('Choose an existing string/text type field from which populate the Marker Infowindow.'),
        '#options' => $info_window_source_options,
        '#default_value' => $settings['map_marker_and_infowindow']['infowindow_field'],
      ];
    }

    $elements['map_marker_and_infowindow']['multivalue_split'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multivalue Field Split (<u>A Multivalue Field as been selected for the Infowindow Content)</u>'),
      '#description' => $this->t('If checked, each field value will be split into each matching infowindow, following the same progressive order<br>(the first value of the field will be used otherwise, or as fallback in case of no match)'),
      '#default_value' => !empty($settings['map_marker_and_infowindow']['multivalue_split']) ? $settings['map_marker_and_infowindow']['multivalue_split'] : 0,
      '#return_value' => 1,
    ];

    if (isset($this->fieldDefinition)) {
      $elements['map_marker_and_infowindow']['multivalue_split']['#description'] = $this->t('If checked, each field value will be split into each matching infowindow / geofield, following the same progressive order<br>(the first value of the field will be used otherwise, or as fallback in case of no match)');
      $elements['map_marker_and_infowindow']['multivalue_split']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_marker_and_infowindow][infowindow_field]"]' => $multivalue_fields_states,
        ],
      ];
    }
    else {
      $elements['map_marker_and_infowindow']['multivalue_split']['#description'] = $this->t('If checked, each field value will be split into each matching infowindow /geofield value (as simple text), following the same progressive order. Note: No rewrite, links or replacements patterns might be applied.<br>(The Multiple Field settings from the View Display will be used otherwise).');
      $elements['map_marker_and_infowindow']['multivalue_split']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_marker_and_infowindow][infowindow_field]"]' => $multivalue_fields_states,
        ],
      ];
    }

    if (isset($this->fieldDefinition)) {
      // Get the human readable labels for the entity view modes.
      $view_mode_options = [];
      foreach ($this->entityDisplayRepository->getViewModes($form['#entity_type']) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $elements['map_marker_and_infowindow']['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Infowindow.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($settings['map_marker_and_infowindow']['view_mode']) ? $settings['map_marker_and_infowindow']['view_mode'] : NULL,
        '#states' => [
          'visible' => [
            ':input[name$="[settings][map_marker_and_infowindow][infowindow_field]"]' => [
              'value' => '#rendered_entity',
            ],
          ],
        ],
      ];
    }

    if (isset($this->fieldDefinition)) {
      $elements['map_marker_and_infowindow']['force_open'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Open Infowindow on Load'),
        '#description' => $this->t('If checked the Infowindow will automatically open on page load.<br><b>Note:</b> in case of multivalue Geofield, the Infowindow will be opened (and the Map centered) on the first item.'),
        '#default_value' => !empty($settings['map_marker_and_infowindow']['force_open']) ? $settings['map_marker_and_infowindow']['force_open'] : 0,
        '#return_value' => 1,
      ];
    }
  }

  /**
   * Set Map Additional Options Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapAdditionalOptionsElement(array $settings, array &$elements) {
    $elements['map_additional_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Map Additional Options'),
      '#description' => $this->t('<strong>These will override the above settings</strong><br>An object literal of additional map options, that comply with the Google Maps JavaScript API.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>It is even possible to input Map Control Positions. For this use the numeric values of the google.maps.ControlPosition, otherwise the option will be passed as incomprehensible string to Google Maps API.'),
      '#default_value' => $settings['map_additional_options'],
      '#placeholder' => '{"disableDoubleClickZoom": "cooperative",
"gestureHandling": "none",
"streetViewControlOptions": {"position": 5}
}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];
  }

  /**
   * Set Map Geometries Options Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setGeometriesAdditionalOptionsElement(array $settings, array &$elements) {
    $elements['map_geometries_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Map Geometries Options'),
      '#description' => $this->t('Set here options that will be applied to the rendering of Map Geometries (Lines & Polylines, Polygons, Multipolygons, etc.).<br>Refer to the @polygons_documentation.', [
        '@polygons_documentation' => $this->link->generate($this->t('Google Maps Polygons Documentation'), Url::fromUri('https://developers.google.com/maps/documentation/javascript/reference/polygon#PolylineOptions', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#default_value' => $settings['map_geometries_options'],
      '#placeholder' => self::getDefaultSettings()['map_geometries_options'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];
  }

  /**
   * Set Overlapping Marker Spiderfier Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $default_settings
   *   The default settings array.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapOmsElement(array $settings, array $default_settings, array &$elements) {
    $elements['map_oms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Overlapping Markers'),
      '#description' => $this->t('<b>Note: </b>To make this working in conjunction with the Markercluster Option (see below) a "maxZoom" property should be set in the Marker Cluster Additional Options.'),
      '#description_display' => 'before',
    ];
    $elements['map_oms']['map_oms_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Spiderfy overlapping markers'),
      '#description' => $this->t('Use the standard setup of the @overlapping_marker_spiderfier to manage Overlapping Markers located in the exact same position.', [
        '@overlapping_marker_spiderfier' => $this->link->generate(t('Overlapping Marker Spiderfier Library (for Google Maps)'), Url::fromUri('https://github.com/jawj/OverlappingMarkerSpiderfier#overlapping-marker-spiderfier-for-google-maps-api-v3', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#default_value' => isset($settings['map_oms']['map_oms_control']) ? $settings['map_oms']['map_oms_control'] : $default_settings['map_oms']['map_oms_control'],
      '#return_value' => 1,
    ];
    $elements['map_oms']['map_oms_options'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Markers Spiderfy Options'),
      '#description' => $this->t('An object literal of Spiderfy options, that comply with the Overlapping Marker Spiderfier Library (see link above).<br>The syntax should respect the javascript object notation (json) format.<br>Always use double quotes (") both for the indexes and the string values.<br><b>Note: </b>This first three default options are the library ones suggested to save memory and CPU (in the simplest/standard implementation).'),
      '#default_value' => isset($settings['map_oms']['map_oms_options']) ? $settings['map_oms']['map_oms_options'] : $default_settings['map_oms']['map_oms_options'],
      '#placeholder' => '{"markersWontMove": "true", "markersWontHide": "true", "basicFormatEvents": "true", "nearbyDistance": 3}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    if (isset($this->fieldDefinition)) {
      $elements['map_oms']['map_oms_options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $elements['map_oms']['map_oms_options']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
      ];
    }
  }

  /**
   * Set Custom Map Style Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setCustomStyleMapElement(array $settings, array &$elements) {
    $elements['custom_style_map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom Styled Map'),
    ];
    $elements['custom_style_map']['custom_style_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create a @custom_google_map_style_link.', [
        '@custom_google_map_style_link' => $this->link->generate($this->t('Custom Google Map Style'), Url::fromUri('https://developers.google.com/maps/documentation/javascript/examples/maptype-styled-simple', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#description' => $this->t('This option allows to create a new map type, which the user can select from the map type control. The map type includes custom styles.'),
      '#default_value' => $settings['custom_style_map']['custom_style_control'],
      '#return_value' => 1,
    ];
    $elements['custom_style_map']['custom_style_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Map Style Name'),
      '#description' => $this->t('Input the Name of the Custom Map Style you want to create.'),
      '#default_value' => $settings['custom_style_map']['custom_style_name'],
      '#placeholder' => $this->t('My Custom Map Style'),
      '#element_validate' => [[get_class($this), 'customMapStyleValidate']],
    ];
    $elements['custom_style_map']['custom_style_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Custom Map Style Options'),
      '#description' => $this->t('An object literal of map style options, that comply with the Google Maps JavaScript API.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>(As a useful reference consider using @snappy_maps).', [
        '@snappy_maps' => $this->link->generate($this->t('Snappy Maps'), Url::fromUri('https://snazzymaps.com', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#default_value' => $settings['custom_style_map']['custom_style_options'],
      '#placeholder' => $this->customMapStylePlaceholder,
      '#element_validate' => [
        [get_class($this), 'jsonValidate'],
        [get_class($this), 'customMapStyleValidate'],
      ],
    ];
    $elements['custom_style_map']['custom_style_hint'] = [
      '#type' => 'container',
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Hint: Use the following json text to disable the default Google Pois from the Map'),
      ],
      'json' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('<b>[{"featureType":"poi","stylers":[{"visibility":"off"}]}]</b>'),
      ],
    ];
    $elements['custom_style_map']['custom_style_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force the Custom Map Style as Default'),
      '#description' => $this->t('The Custom Map Style will be the Default starting one.'),
      '#default_value' => $settings['custom_style_map']['custom_style_default'],
      '#return_value' => 1,
    ];

    if (isset($this->fieldDefinition)) {
      $custom_style_map_control_selector = ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][custom_style_map][custom_style_control]"]';
    }
    else {
      $custom_style_map_control_selector = ':input[name="style_options[custom_style_map][custom_style_control]"]';
    }
    $elements['custom_style_map']['custom_style_name']['#states'] = [
      'visible' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
      'required' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
    ];
    $elements['custom_style_map']['custom_style_options']['#states'] = [
      'visible' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
    ];
    $elements['custom_style_map']['custom_style_default']['#states'] = [
      'visible' => [
        $custom_style_map_control_selector => ['checked' => TRUE],
      ],
    ];
  }

  /**
   * Set Map Marker Cluster Element.
   *
   * @param array $settings
   *   The Form Settings.
   * @param array $elements
   *   The Form element to alter.
   */
  private function setMapMarkerclusterElement(array $settings, array &$elements) {
    $elements['map_markercluster'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Marker Clustering'),
    ];
    $elements['map_markercluster']['markup'] = [
      '#markup' => $this->t('Enable the functionality of the @markeclusterer_api_link.', [
        '@markeclusterer_api_link' => $this->link->generate($this->t('Marker Clusterer Google Maps JavaScript Library'), Url::fromUri('https://github.com/googlemaps/js-marker-clusterer', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];
    $elements['map_markercluster']['markercluster_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Marker Clustering'),
      '#default_value' => $settings['map_markercluster']['markercluster_control'],
      '#return_value' => 1,
    ];
    $elements['map_markercluster']['markercluster_additional_options'] = [
      '#type' => 'textarea',
      '#rows' => 4,
      '#title' => $this->t('Marker Cluster Additional Options'),
      '#description' => $this->t('An object literal of additional marker cluster options, that comply with the Marker Clusterer Google Maps JavaScript Library.<br>The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['map_markercluster']['markercluster_additional_options'],
      '#placeholder' => '{"maxZoom":12,"gridSize":50}',
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    $elements['map_markercluster']['markercluster_warning'] = [
      '#type' => 'container',
      'warning' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('WARNING:') . " ",
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ],
      'warning_text' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Markers Spiderfy is Active ! | A "maxZoom" property should be set in the Marker Cluster Options to output the Spiderfy effect.'),
      ],
    ];

    if (isset($this->fieldDefinition)) {
      $elements['map_markercluster']['markercluster_additional_options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_markercluster][markercluster_control]"]' => ['checked' => TRUE],
        ],
      ];
      $elements['map_markercluster']['markercluster_warning']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_markercluster][markercluster_control]"]' => ['checked' => FALSE],
        ],
      ];
    }
    else {
      $elements['map_markercluster']['markercluster_additional_options']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_markercluster][markercluster_control]"]' => ['checked' => TRUE],
        ],
      ];
      $elements['map_markercluster']['markercluster_warning']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_oms][map_oms_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          ':input[name="style_options[map_markercluster][markercluster_control]"]' => ['checked' => FALSE],
        ],
      ];
    }
  }

}
