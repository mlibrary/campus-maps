<?php

namespace Drupal\geofield_map\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Site\Settings;

/**
 * Implements the GeofieldMapSettingsForm controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class GeofieldMapSettingsForm extends ConfigFormBase {

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * GeofieldMapSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LinkGeneratorInterface $link_generator) {
    parent::__construct($config_factory);
    $this->link = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('geofield_map.settings');

    $form['#tree'] = TRUE;

    $form['#attached']['library'][] = 'geofield_map/geofield_map_settings';

    $form['gmap_api_key'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('gmap_api_key'),
      '#title' => $this->t('Gmap Api Key (@gmap_api_link)', [
        '@gmap_api_link' => $this->link->generate(t('Get a Key/Authentication for Google Maps Javascript Library'), Url::fromUri('https://developers.google.com/maps/documentation/javascript/get-api-key', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#description' => $this->t('A unique Gmap Api Key is required for both Google Mapping and Geocoding operations, all performed client-side by js.<br>@gmap_api_restrictions_link.', [
        '@gmap_api_restrictions_link' => $this->link->generate(t('It might/should be restricted using the Website Domain / HTTP referrers method'), Url::fromUri('https://developers.google.com/maps/documentation/javascript/get-api-key#key-restrictions', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
      '#placeholder' => $this->t('Input a valid Gmap API Key'),
    ];

    $form['gmap_api_localization'] = [
      '#type' => 'select',
      '#default_value' => $config->get('gmap_api_localization') ?: 'default',
      '#title' => $this->t('Gmap Api Localization'),
      '#options' => [
        'default' => t('Default - Normal international Google Maps API load'),
        'china' => t('Chinese - API Load for specific use in China'),
      ],
      '#description' => $this->t('Possible alternative logic for Google Maps Api load, in specific countries (i.e: China).'),
    ];

    $form['theming'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geofield Map Theming Settings'),
    ];

    $form['theming']['markers_location'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Markers Icons Storage location'),
      '#attributes' => [
        'class' => ['markers-location'],
      ],
    ];

    $files_security_opts = ['public://' => 'public://'];

    if (Settings::get('file_private_path')) {
      $files_security_opts['private://'] = 'private://';
    }

    $form['theming']['markers_location']['security'] = [
      '#type' => 'select',
      '#options' => $files_security_opts,
      '#title' => $this->t('Security method'),
      '#default_value' => !empty($config->get('theming.markers_location.security')) ? $config->get('theming.markers_location.security') : 'public://',
    ];

    $form['theming']['markers_location']['rel_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('- Relative Path'),
      '#default_value' => !empty($config->get('theming.markers_location.rel_path')) ? $config->get('theming.markers_location.rel_path') : 'geofieldmap_markers',
      '#placeholder' => $this->t("Don't use any start / end trailing slash"),
    ];

    $form['theming']['markers_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Markers Allowed file extensions'),
      '#default_value' => !empty($config->get('theming.markers_extensions')) ? $config->get('theming.markers_extensions') : 'gif png jpg jpeg',
      '#description' => $this->t('Separate extensions with a space or comma and do not include the leading dot.'),
    ];

    $form['theming']['markers_filesize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum file size'),
      '#default_value' => !empty($config->get('theming.markers_filesize')) ? $config->get('theming.markers_filesize') : '250 KB',
      '#description' => t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes will be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', ['%limit' => format_size(file_upload_max_size())]),
      '#size' => 10,
      '#element_validate' => ['\Drupal\file\Plugin\Field\FieldType\FileItem::validateMaxFilesize'],
    ];

    $form['geocoder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geofield Map Geocoder Settings'),
    ];

    $form['geocoder']['caching'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache Settings'),
      '#description_display' => 'before',
    ];

    $form['geocoder']['caching']['clientside'] = [
      '#type' => 'select',
      '#options' => [
        '_none_' => $this->t('- none -'),
        'session_storage' => $this->t('SessionStorage'),
        'local_storage' => $this->t('LocalStorage'),
      ],
      '#title' => $this->t('Client Side WebStorage'),
      '#default_value' => !empty($config->get('geocoder.caching.clientside')) ? $config->get('geocoder.caching.clientside') : 'session_storage',
      '#description' => $this->t('The following option will activate caching of geocoding results on the client side, as far as possible at the moment (only Reverse Geocoding results).<br>This can highly reduce the amount of payload calls against the Google Maps Geocoder and Google Places webservices used by the module.<br>Please refer to official documentation on @html5_web_storage browsers capabilities and specifications.', [
        '@html5_web_storage' => $this->link->generate(t('HTML5 Web Storage'), Url::fromUri('https://www.w3schools.com/htmL/html5_webstorage.asp', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geofield_map_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'geofield_map.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('geofield_map.settings');
    $config->set('gmap_api_key', $form_state->getValue('gmap_api_key'));
    $config->set('gmap_api_localization', $form_state->getValue('gmap_api_localization'));
    $config->set('theming', $form_state->getValue('theming'));
    $config->set('geocoder', $form_state->getValue('geocoder'));
    $config->save();

    // Confirmation on form submission.
    drupal_set_message($this->t('The Geofield Map configurations have been saved.'));
  }

}
