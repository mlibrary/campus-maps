<?php

namespace Drupal\geofield_map\leafletTileLayerPlugin;

use Drupal\geofield_map\leafletTileLayers\LeafletTileLayerPluginBase;

/**
 * Provides an OpenStreetMap_Mapnik Leaflet TileLayer Plugin.
 *
 * @LeafletTileLayerPlugin(
 *   id = "OpenStreetMap_Mapnik",
 *   label = "OpenStreetMap Mapnik",
 *   url = "http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
 *   options = {
 *     "maxZoom" = 19,
 *     "attribution" = "&copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a>"
 *   }
 * )
 */
class OpenStreetMap_Mapnik extends LeafletTileLayerPluginBase {}
