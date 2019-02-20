<?php

namespace Drupal\geofield_map\Plugin\LeafletTileLayerPlugin;

use Drupal\geofield_map\LeafletTileLayerPluginBase;

/**
 * Provides an OpenStreetMap_BlackAndWhite Leaflet TileLayer Plugin.
 *
 * @LeafletTileLayerPlugin(
 *   id = "OpenStreetMap_BlackAndWhite",
 *   label = "OpenStreetMap BlackAndWhite",
 *   url = "http://{s}.tiles.wmflabs.org/bw-mapnik/{z}/{x}/{y}.png",
 *   options = {
 *     "maxZoom" = 18,
 *     "attribution" = "&copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a>",
 *   }
 * )
 */
class OpenStreetMap_BlackAndWhite extends LeafletTileLayerPluginBase {}
