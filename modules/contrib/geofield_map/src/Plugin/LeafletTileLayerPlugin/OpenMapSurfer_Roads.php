<?php

namespace Drupal\geofield_map\Plugin\LeafletTileLayerPlugin;

use Drupal\geofield_map\LeafletTileLayerPluginBase;

/**
 * Provides an OpenMapSurfer_Roads Leaflet TileLayer Plugin.
 *
 * @LeafletTileLayerPlugin(
 *   id = "OpenMapSurfer_Roads",
 *   label = "OpenMapSurfer Roads",
 *   url = "http://korona.geog.uni-heidelberg.de/tiles/roads/x={x}&y={y}&z={z}",
 *   options = {
 *     "maxZoom" = 20,
 *     "attribution" = "Imagery from <a href='http://giscience.uni-hd.de/'>GIScience Research Group @ University of Heidelberg</a> &mdash; Map data &copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a>",
 *   }
 * )
 */
class OpenMapSurfer_Roads extends LeafletTileLayerPluginBase {}
