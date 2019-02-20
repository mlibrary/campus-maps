(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.leaflet = {
    attach: function (context, settings) {

      // Attach leaflet ajax popup listeners.
      $(document).on('leaflet.map', function (e, settings, lMap) {
        lMap.on('popupopen', function (e) {
          var content = $('[data-leaflet-ajax-popup]', e.popup._contentNode);
          if (content.length) {
            var url = content.data('leaflet-ajax-popup');
            $.get(url, function (response) {
              if (response) {
                e.popup.setContent(response)
              }
            });
          }
        });
      });

      $.each(settings.leaflet, function (m, data) {
        $('#' + data.mapId, context).each(function () {
          var $container = $(this);

          // If the attached context contains any leaflet maps, make sure we have a Drupal.leaflet_widget object.
          if ($container.data('leaflet') === undefined) {
            $container.data('leaflet', new Drupal.Leaflet(L.DomUtil.get(data.mapId), data.mapId, data.map));
            if (data.features.length > 0) {
              Drupal.Leaflet.path = data.map.settings.path && data.map.settings.path.length > 0 ? JSON.parse(data.map.settings.path) : {};
              $container.data('leaflet').add_features(data.features, true);
            }

            // Set map position features.
            $container.data('leaflet').fitbounds();

            // Add the leaflet map to our settings object to make it accessible
            data.lMap = $container.data('leaflet').lMap;
          }
          else {
            // If we already had a map instance, add new features.
            // @todo Does this work? Needs testing.
            if (data.features !== undefined) {
              $container.data('leaflet').add_features(data.features);
            }
          }
        });
      });
    }
  };

  Drupal.Leaflet = function (container, mapId, map_definition) {
    this.container = container;
    this.mapId = mapId;
    this.map_definition = map_definition;
    this.settings = this.map_definition.settings;
    this.bounds = [];
    this.base_layers = {};
    this.overlays = {};
    this.lMap = null;
    this.layer_control = null;
    this.path = {};

    this.initialise();
  };

  Drupal.Leaflet.prototype.initialise = function () {
    // Instantiate a new Leaflet map.
    this.lMap = new L.Map(this.mapId, this.settings);

    // add map layers (base and overlay layers)
    var layers = {}, overlays = {};
    var i = 0;
    for (var key in this.map_definition.layers) {
      var layer = this.map_definition.layers[key];
      // Distinguish between "base" and "overlay" layers.
      // Default to "base" in case "layer_type" has not been defined in hook_leaflet_map_info().
      layer.layer_type = (typeof layer.layer_type === 'undefined') ? 'base' : layer.layer_type;

      switch (layer.layer_type) {
        case 'overlay':
          var overlay_layer = this.create_layer(layer, key);
          var layer_hidden = (typeof layer.layer_hidden === "undefined") ? false : layer.layer_hidden ;
          this.add_overlay(key, overlay_layer, layer_hidden);
          break;
        default:
          this.add_base_layer(key, layer, i);
          if (i === 0) {    //  Only the first base layer needs to be added to the map - all the others are accessed via the layer switcher
            i++;
          }
          break;
      }
      i++;
    }

    // Set initial view, fallback to displaying the whole world.
    if (this.settings.center && this.settings.zoom) {
      this.lMap.setView(new L.LatLng(this.settings.center.lat, this.settings.center.lng), this.settings.zoom);
    }
    else {
      this.lMap.fitWorld();
    }

    // Add attribution
    if (this.settings.attributionControl && this.map_definition.attribution) {
      this.lMap.attributionControl.setPrefix(this.map_definition.attribution.prefix);
      this.attributionControl.addAttribution(this.map_definition.attribution.text);
    }

    // allow other modules to get access to the map object using jQuery's trigger method
    $(document).trigger('leaflet.map', [this.map_definition, this.lMap, this]);
  };

  Drupal.Leaflet.prototype.initialise_layer_control = function () {
    var count_layers = function (obj) {
      // Browser compatibility: Chrome, IE 9+, FF 4+, or Safari 5+
      // @see http://kangax.github.com/es5-compat-table/
      return Object.keys(obj).length;
    };

    // Only add a layer switcher if it is enabled in settings, and we have
    // at least two base layers or at least one overlay.
    if (this.layer_control == null && this.settings.layerControl && (count_layers(this.base_layers) > 1 || count_layers(this.overlays) > 0)) {
      // Instantiate layer control, using settings.layerControl as settings.
      this.layer_control = new L.Control.Layers(this.base_layers, this.overlays, this.settings.layerControl);
      this.lMap.addControl(this.layer_control);
    }
  };

  Drupal.Leaflet.prototype.add_base_layer = function (key, definition, i) {
    var map_layer = this.create_layer(definition, key);
    this.base_layers[key] = map_layer;
    if (i === 0) {    //  Only the first base layer needs to be added to the map - all the others are accessed via the layer switcher
      this.lMap.addLayer(map_layer);
    }
    if (this.layer_control == null) {
      this.initialise_layer_control();
    }
    else {
      // If we already have a layer control, add the new base layer to it.
      this.layer_control.addBaseLayer(map_layer, key);
    }
  };

  Drupal.Leaflet.prototype.add_overlay = function (label, layer, layer_hidden) {
    this.overlays[label] = layer;
    if (!layer_hidden) {
      this.lMap.addLayer(layer);
    }

    if (this.layer_control == null) {
      this.initialise_layer_control();
    }
    else {
      // If we already have a layer control, add the new overlay to it.
      this.layer_control.addOverlay(layer, label);
    }
  };

  Drupal.Leaflet.prototype.add_features = function (features, initial) {
    var self = this;
    for (var i = 0; i < features.length; i++) {
      var feature = features[i];
      var lFeature;

      // dealing with a layer group
      if (feature.group) {
        var lGroup = this.create_feature_group(feature);
        for (var groupKey in feature.features) {
          var groupFeature = feature.features[groupKey];
          lFeature = this.create_feature(groupFeature);
          if (lFeature !== undefined) {
            if (groupFeature.popup) {
              lFeature.bindPopup(groupFeature.popup);
            }
            lGroup.addLayer(lFeature);
          }
        }

        // Add the group to the layer switcher.
        this.add_overlay(feature.label, lGroup, FALSE);
      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          if (lFeature.setStyle) {
            lFeature.setStyle(Drupal.Leaflet.path);
          }
          this.lMap.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
        }
      }

      // Allow others to do something with the feature that was just added to the map
      $(document).trigger('leaflet.feature', [lFeature, feature, this]);
    }

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this])
  };

  Drupal.Leaflet.prototype.create_feature_group = function (feature) {
    return new L.LayerGroup();
  };

  Drupal.Leaflet.prototype.create_feature = function (feature) {
    var lFeature;
    switch (feature.type) {
      case 'point':
        lFeature = this.create_point(feature);
        break;
      case 'linestring':
        lFeature = this.create_linestring(feature);
        break;
      case 'polygon':
        lFeature = this.create_polygon(feature);
        break;
       case 'multipolygon':
        lFeature = this.create_multipolygon(feature);
        break;
      case 'multipolyline':
        lFeature = this.create_multipoly(feature);
        break;
      case 'json':
        lFeature = this.create_json(feature.json);
        break;
      case 'multipoint':
      case 'geometrycollection':
        lFeature = this.create_collection(feature);
        break;
      default:
        return; // Crash and burn.
    }

    // assign our given unique ID, useful for associating nodes
    if (feature.leaflet_id) {
      lFeature._leaflet_id = feature.leaflet_id;
    }

    var options = {};
    if (feature.options) {
      for (var option in feature.options) {
        options[option] = feature.options[option];
      }
      lFeature.setStyle(options);
    }

    return lFeature;
  };

  Drupal.Leaflet.prototype.create_layer = function (layer, key) {
    var map_layer = new L.TileLayer(layer.urlTemplate);
    map_layer._leaflet_id = key;

    if (layer.options) {
      for (var option in layer.options) {
        map_layer.options[option] = layer.options[option];
      }
    }

    // layers served from TileStream need this correction in the y coordinates
    // TODO: Need to explore this more and find a more elegant solution
    if (layer.type == 'tilestream') {
      map_layer.getTileUrl = function (tilePoint) {
        this._adjustTilePoint(tilePoint);
        var zoom = this._getZoomForUrl();
        return L.Util.template(this._url, L.Util.extend({
          s: this._getSubdomain(tilePoint),
          z: zoom,
          x: tilePoint.x,
          y: Math.pow(2, zoom) - tilePoint.y - 1
        }, this.options));
      }
    }
    return map_layer;
  };

  Drupal.Leaflet.prototype.create_icon = function (options) {
    var icon = new L.Icon({iconUrl: options.iconUrl});

    // override applicable marker defaults
    if (options.iconSize) {
      icon.options.iconSize = new L.Point(parseInt(options.iconSize.x), parseInt(options.iconSize.y));
    }
    if (options.iconAnchor && options.iconAnchor.x && options.iconAnchor.y) {
      icon.options.iconAnchor = new L.Point(parseFloat(options.iconAnchor.x), parseFloat(options.iconAnchor.y));
    }
    if (options.popupAnchor && options.popupAnchor.x && options.popupAnchor.y) {
      icon.options.popupAnchor = new L.Point(parseInt(options.popupAnchor.x), parseInt(options.popupAnchor.y));
    }
    if (options.shadowUrl) {
      icon.options.shadowUrl = options.shadowUrl;
    }
    if (options.shadowSize && options.shadowSize.x && options.shadowSize.y) {
      icon.options.shadowSize = new L.Point(parseInt(options.shadowSize.x), parseInt(options.shadowSize.y));
    }
    if (options.shadowAnchor && options.shadowAnchor.x && options.shadowAnchor.y) {
      icon.options.shadowAnchor = new L.Point(parseInt(options.shadowAnchor.x), parseInt(options.shadowAnchor.y));
    }
    if (options.className) {
      icon.options.className = options.className;
    }

    return icon;
  };

  Drupal.Leaflet.prototype.create_point = function (marker) {
    var self = this;
    var latLng = new L.LatLng(marker.lat, marker.lon);
    this.bounds.push(latLng);
    var lMarker;
    var tooltip = marker.label ? marker.label.replace(/<[^>]*>/g, '').trim() : '';
    var options = {
      title: tooltip
    };

    if (marker.alt !== undefined) {
      options.alt = marker.alt;
    }

    function checkImage(imageSrc, setIcon, logError) {
      var img = new Image();
      img.src = imageSrc;
      img.onload = setIcon;
      img.onerror = logError;
    }

    lMarker = new L.Marker(latLng, options);

    if (marker.icon) {
      checkImage(marker.icon.iconUrl,
        // Success loading image.
        function(){
          marker.icon.iconSize = marker.icon.iconSize || {};
          marker.icon.iconSize.x = marker.icon.iconSize.x || this.naturalWidth;
          marker.icon.iconSize.y = marker.icon.iconSize.y || this.naturalHeight;
          if (marker.icon.shadowUrl) {
            marker.icon.shadowSize = marker.icon.shadowSize || {};
            marker.icon.shadowSize.x = marker.icon.shadowSize.x || this.naturalWidth;
            marker.icon.shadowSize.y = marker.icon.shadowSize.y || this.naturalHeight;
          }
          options.icon = self.create_icon(marker.icon);
          lMarker.setIcon(options.icon);
        },
        // Error loading image.
        function(err){
          console.log("Leaflet: The Icon Image doesn't exist at the requested path: " + marker.icon.iconUrl);
        });
    }

    return lMarker;
  };

  Drupal.Leaflet.prototype.create_linestring = function (polyline) {
    var latlngs = [];
    for (var i = 0; i < polyline.points.length; i++) {
      var latlng = new L.LatLng(polyline.points[i].lat, polyline.points[i].lon);
      latlngs.push(latlng);
      this.bounds.push(latlng);
    }
    return new L.Polyline(latlngs);
  };

  Drupal.Leaflet.prototype.create_collection = function (collection) {
    var layers = new L.featureGroup();
    for (var x = 0; x < collection.component.length; x++) {
      layers.addLayer(this.create_feature(collection.component[x]));
    }
    return layers;
  };

  Drupal.Leaflet.prototype.create_polygon = function (polygon) {
    var latlngs = [];
    for (var i = 0; i < polygon.points.length; i++) {
      var latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
      latlngs.push(latlng);
      this.bounds.push(latlng);
    }
    return new L.Polygon(latlngs);
  };

  Drupal.Leaflet.prototype.create_multipolygon = function (multipolygon) {
    var polygons = [];
    for (var x = 0; x < multipolygon.component.length; x++) {
      var latlngs = [];
      var polygon = multipolygon.component[x];
      for (var i = 0; i < polygon.points.length; i++) {
        var latlng = [polygon.points[i].lat, polygon.points[i].lon];
        latlngs.push(latlng);
        this.bounds.push(latlng);
      }
      polygons.push(latlngs);
    }
    return new L.Polygon(polygons);
  };

  Drupal.Leaflet.prototype.create_multipoly = function (multipoly) {
    var polygons = [];
    for (var x = 0; x < multipoly.component.length; x++) {
      var latlngs = [];
      var polygon = multipoly.component[x];
      for (var i = 0; i < polygon.points.length; i++) {
        var latlng = new L.LatLng(polygon.points[i].lat, polygon.points[i].lon);
        latlngs.push(latlng);
        this.bounds.push(latlng);
      }
      polygons.push(latlngs);
    }
    if (multipoly.multipolyline) {
      return new L.polyline(polygons);
    }
    else {
      return new L.polygon(polygons);
    }
  };

  Drupal.Leaflet.prototype.create_json = function (json) {
    lJSON = new L.GeoJSON();

    lJSON.options.onEachFeature = function(feature, layer){
      for (var layer_id in layer._layers) {
        for (var i in layer._layers[layer_id]._latlngs) {
          Drupal.Leaflet.bounds.push(layer._layers[layer_id]._latlngs[i]);
        }
      }
      if (feature.properties.style) {
        layer.setStyle(feature.properties.style);
      }
      if (feature.properties.leaflet_id) {
        layer._leaflet_id = feature.properties.leaflet_id;
      }
      if (feature.properties.popup) {
        layer.bindPopup(feature.properties.popup);
      }
    };

    lJSON.addData(json);
    return lJSON;
  };

  // Set Map position, fitting Bounds in case of more than one feature
  // @NOTE: This method used by Leaflet Markecluster module (don't remove/rename)
  Drupal.Leaflet.prototype.fitbounds = function () {
    // Fit Bounds if both them and features exist, and the Map Position in not forced.
    if (!this.settings.map_position_force && this.bounds.length > 0) {
      this.lMap.fitBounds(new L.LatLngBounds(this.bounds));

      // In case of single result use the custom Map Zoom set.
      if (this.bounds.length === 1 && this.settings.zoom) {
        this.lMap.setZoom(this.settings.zoom);
      }

    }
  };

})(jQuery, Drupal, drupalSettings);
