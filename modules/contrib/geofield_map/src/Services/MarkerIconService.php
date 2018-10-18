<?php

namespace Drupal\geofield_map\Services;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\file\FileInterface;
use Drupal\file\Entity\File;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Url;
use Drupal\Core\Config\Config;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\Yaml\Exception\ParseException;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Provides an Icon Managed File Service.
 */
class MarkerIconService {

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
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The geofield map settings.
   *
   * @var array
   */
  protected $geofieldMapSettings;

  /**
   * The list of file upload validators.
   *
   * @var array
   */
  protected $fileUploadValidators;

  /**
   * The Default Icon Element.
   *
   * @var array
   */
  protected $defaultIconElement;

  /**
   * The Link Generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * Constructor of the Icon Managed File Service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation,
    EntityTypeManagerInterface $entity_manager,
    ModuleHandlerInterface $module_handler,
    LinkGeneratorInterface $link_generator
  ) {
    $this->config = $config_factory;
    $this->stringTranslation = $string_translation;
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->link = $link_generator;
    $this->geofieldMapSettings = $config_factory->get('geofield_map.settings');
    $this->fileUploadValidators = [
      'file_validate_extensions' => !empty($this->geofieldMapSettings->get('theming.markers_extensions')) ? [$this->geofieldMapSettings->get('theming.markers_extensions')] : ['gif png jpg jpeg'],
      'file_validate_is_image' => [],
      'file_validate_size' => !empty($this->geofieldMapSettings->get('theming.markers_filesize')) ? [Bytes::toInt($this->geofieldMapSettings->get('theming.markers_filesize'))] : [Bytes::toInt('250 KB')],
    ];
    $this->defaultIconElement = [
      '#title' => $this->t('<- Default Legend Icon (34x34 px) ->'),
      '#theme' => 'image',
      '#width' => '34px',
      '#height' => '34px',
      '#uri' => '',
    ];
  }

  /**
   * Set Geofield Map Default Icon Style.
   */
  protected function setDefaultIconStyle() {
    $image_style_path = drupal_get_path('module', 'geofield_map') . '/config/optional/image.style.geofield_map_default_icon_style.yml';
    $image_style_data = Yaml::parse(file_get_contents($image_style_path));
    $geofield_map_default_icon_style = $this->config->getEditable('image.style.geofield_map_default_icon_style');
    if ($geofield_map_default_icon_style instanceof Config) {
      $geofield_map_default_icon_style->setData($image_style_data)->save(TRUE);
    }
  }

  /**
   * Get the default Icon Element.
   *
   * @return array
   *   The defaultIconElement element property.
   */
  public function getDefaultIconElement() {
    return $this->defaultIconElement;
  }

  /**
   * Validates the Icon Image statuses.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateIconImageStatus(array $element, FormStateInterface $form_state) {
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);
    if (!empty($element['#value']['fids'][0])) {
      $file = File::load($element['#value']['fids'][0]);
      if (in_array($clicked_button, ['save_settings', 'submit'])) {
        $file->setPermanent();
        self::fileSave($file);
      }
      if ($clicked_button == 'remove_button') {
        $file->setTemporary();
        self::fileSave($file);
      }
    }
  }

  /**
   * Save a file, handling exception.
   *
   * @param \Drupal\file\Entity\file $file
   *   The file to save.
   */
  public static function fileSave(file $file) {
    try {
      $file->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('Geofield Map Themer')->log('warning', t("The file couldn't be saved: @message", [
        '@message' => $e->getMessage(),
      ])
      );
    }
  }

  /**
   * Generate Icon File Managed Element.
   *
   * @param int $fid
   *   The file to save.
   *
   * @return array
   *   The icon preview element.
   */
  public function getIconFileManagedElement($fid) {

    $upload_location = !empty($this->geofieldMapSettings->get('theming.markers_location.security') . $this->geofieldMapSettings->get('theming.markers_location.rel_path')) ? $this->geofieldMapSettings->get('theming.markers_location.security') . $this->geofieldMapSettings->get('theming.markers_location.rel_path') : 'public://geofieldmap_markers';

    $element = [
      '#type' => 'managed_file',
      '#geofield_map_marker_icon_upload' => TRUE,
      '#theme' => 'image_widget',
      '#preview_image_style' => 'geofield_map_default_icon_style',
      '#title' => t('Choose a Marker Icon file'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($fid) ? [$fid] : NULL,
      '#multiple' => FALSE,
      '#error_no_message' => FALSE,
      '#upload_location' => $upload_location,
      '#upload_validators' => $this->fileUploadValidators,
      '#progress_message' => $this->t('Please wait...'),
      '#progress_indicator' => 'throbber',
      '#element_validate' => [
        [get_class($this), 'validateIconImageStatus'],
      ],
    ];

    if (!empty($fid)) {
      $element['preview'] = $this->getLegendIcon($fid, 'geofield_map_default_icon_style');
    }

    return $element;
  }

  /**
   * Generate Image Style Selection Element.
   *
   * @return array
   *   The Image Style Select element.
   */
  public function getImageStyleOptions() {
    $options = [
      'none' => $this->t('<- Original File ->'),
    ];

    if ($this->moduleHandler->moduleExists('image')) {

      // Always force the definition of the geofield_map_default_icon_style,
      // if not present.
      if (!ImageStyle::load('geofield_map_default_icon_style')) {
        try {
          $this->setDefaultIconStyle();
        }
        catch (ParseException $e) {
        }
      }

      /* @var \Drupal\image\ImageStyleInterface $style */
      foreach ($image_styles = ImageStyle::loadMultiple() as $k => $style) {
        $options[$k] = Unicode::truncate($style->label(), 20, TRUE, TRUE);
      };
    }

    return $options;
  }

  /**
   * Generate File Upload Help Message.
   *
   * @return array
   *   The field upload help element.
   */
  public function getFileUploadHelp() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      'file_upload_help' => [
        '#theme' => 'file_upload_help',
        '#upload_validators' => $this->fileUploadValidators,
        '#cardinality' => 1,
      ],
      'geofield_map_settings_link' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Customize this in  @geofield_map_settings_page_link', [
          '@geofield_map_settings_page_link' => $this->link->generate('Geofield Map Settings Page', Url::fromRoute('geofield_map.settings')),
        ]),
      ],
      '#attributes' => [
        'style' => ['style' => 'font-size:0.9em; color: gray; text-transform: lowercase; font-weight: normal'],
      ],
    ];
  }

  /**
   * Generate Legend Icon.
   *
   * @param int $fid
   *   The file identifier.
   * @param string $image_style
   *   The image style identifier.
   *
   * @return array
   *   The icon view render array..
   */
  public function getLegendIcon($fid, $image_style = 'none') {
    $icon_element = [];
    /* @var \Drupal\file\Entity\file $file */
    $file = File::load($fid);
    if ($file instanceof FileInterface) {
      $this->defaultIconElement['#uri'] = $file->getFileUri();
      switch ($image_style) {
        case 'none':
          $icon_element = [
            '#weight' => -10,
            '#theme' => 'image',
            '#uri' => $file->getFileUri(),
          ];
          break;

        default:
          $icon_element = [
            '#weight' => -10,
            '#theme' => 'image_style',
            '#uri' => $file->getFileUri(),
            '#style_name' => '',
          ];
          if ($this->moduleHandler->moduleExists('image') && ImageStyle::load($image_style)) {
            $icon_element['#style_name'] = $image_style;
          }
          else {
            $icon_element = $this->defaultIconElement;
          }
      }
    }
    return $icon_element;
  }

  /**
   * Generate Uri from fid, and image style.
   *
   * @param int $fid
   *   The file identifier.
   *
   * @return string
   *   The icon preview element.
   */
  public function getUriFromFid($fid = NULL) {
    if (isset($fid) && $file = File::load($fid)) {
      return $file->getFileUri();
    }
    return NULL;
  }

  /**
   * Generate File Managed Url from fid, and image style.
   *
   * @param int $fid
   *   The file identifier.
   * @param string $image_style
   *   The image style identifier.
   *
   * @return string
   *   The url path to the file id (image style).
   */
  public function getFileManagedUrl($fid = NULL, $image_style = 'none') {
    if (isset($fid) && $file = File::load($fid)) {
      $uri = $file->getFileUri();
      if ($this->moduleHandler->moduleExists('image') && $image_style != 'none' && ImageStyle::load($image_style)) {
        $url = ImageStyle::load($image_style)->buildUrl($uri);
      }
      else {
        $url = file_create_url($uri);
      }
      return $url;
    }
    return NULL;
  }

}
