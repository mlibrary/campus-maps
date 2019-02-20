(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.geofieldGoogleMap = {
    attach: function (context, settings) {
      if (drupalSettings['geofield_google_map']) {
        $(context).find('.geofield-google-map').once('geofield-processed').each(function (index, element) {
          var mapid = $(element).attr('id');

          // Check if the Map container really exists and hasn't been yet initialized.
          if (drupalSettings['geofield_google_map'][mapid] && !Drupal.geoFieldMap.map_data[mapid]) {

            var map_settings = drupalSettings['geofield_google_map'][mapid]['map_settings'];
            var data = drupalSettings['geofield_google_map'][mapid]['data'];

            // Set the map_data[mapid] settings.
            Drupal.geoFieldMap.map_data[mapid] = map_settings;

            // Load before the Gmap Library, if needed.
            Drupal.geoFieldMap.loadGoogle(mapid, map_settings.gmap_api_key, function () {
              Drupal.geoFieldMap.map_initialize(mapid, map_settings, data, context);
            });
          }
        });
      }
    }
  };

  Drupal.geoFieldMap = {

    map_start: {
      center: {lat: 41.85, lng: -87.65},
      zoom: 18,
    },

    map_data: {},

    // Google Maps are loaded lazily. In some situations load_google() is called twice, which results in
    // "You have included the Google Maps API multiple times on this page. This may cause unexpected errors." errors.
    // This flag will prevent repeat $.getScript() calls.
    maps_api_loading: false,

    /**
     * Returns the re-coded google maps api language parameter, from html lang
     * attribute.
     */
    googleMapsLanguage: function (html_language) {
      switch (html_language) {
        case 'zh-hans':
          html_language = 'zh-CN';
          break;
        case 'zh-hant':
          html_language = 'zh-TW';
          break;
      }
      return html_language;
    },

    /**
     * Provides the callback that is called when maps loads.
     */
    googleCallback: function () {
      var self = this;
      // Wait until the window load event to try to use the maps library.
      $(document).ready(function (e) {
        _.invoke(self.googleCallbacks, 'callback');
        self.googleCallbacks = [];
      });
    },

    /**
     * Adds a callback that will be called once the maps library is loaded.
     *
     * @param callback - The callback
     */
    addCallback: function (callback) {
      var self = this;
      // Ensure callbacks array;
      self.googleCallbacks = self.googleCallbacks || [];
      self.googleCallbacks.push({callback: callback});
    },

    // Lead Google Maps library.
    loadGoogle: function (mapid, gmap_api_key, callback) {
      var self = this;
      var html_language = $('html').attr("lang") ? $('html').attr("lang") : 'en';

      // Add the callback.
      self.addCallback(callback);

      // Check for google maps.
      if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        if (self.maps_api_loading === true) {
          return;
        }

        self.maps_api_loading = true;
        // Google maps isn't loaded so lazy load google maps.

        // Default script path.
        var scriptPath = self.map_data[mapid]['gmap_api_localization'] + '?v=3.exp&sensor=false&language=' + self.googleMapsLanguage(html_language);

        // If a Google API key is set, use it.
        if (gmap_api_key) {
          scriptPath += '&key=' + gmap_api_key;
        }

        $.getScript(scriptPath)
          .done(function () {
            self.maps_api_loading = false;
            self.googleCallback();
          });

      }
      else {
        // Google maps loaded. Run callback.
        self.googleCallback();
      }
    },

    place_feature: function(feature, icon_image, mapid) {
      var self = this;

      // If the features are object of geofield map theming then remove custom url Icon Image
      if (feature.geojsonProperties.theming) {
        icon_image = null;
      }

      // Override and set icon image with geojsonProperties.icon, if set as not null/empty.
      if (feature.geojsonProperties.icon && feature.geojsonProperties.icon.length > 0) {
        icon_image = feature.geojsonProperties.icon;
      }

      // Define the OverlappingMarkerSpiderfier flag.
      var oms = self.map_data[mapid].oms ? self.map_data[mapid].oms : null;

      // Set the personalized Icon Image, if set.
      if (feature.setIcon && icon_image && icon_image.length > 0) {

        function checkImage(imageSrc, setIcon, logError) {
          var img = new Image();
          img.src = imageSrc;
          img.onload = setIcon;
          img.onerror = logError;
        }

        checkImage(icon_image,
          // Success loading image.
          function(){
            feature.setIcon(icon_image);
          },
          // Error loading image.
          function(){
            console.log("Geofield Gmap: The Icon Image doesn't exist at the requested path: " + icon_image);
          });
      }

      var map = self.map_data[mapid].map;

      // If the feature is a Point, make it a Marker and extend the Map bounds.
      if (feature.getPosition) {
        if (oms) {
          self.map_data[mapid].oms.addMarker(feature);
        }
        else {
          feature.setMap(map);
        }
        self.map_data[mapid].markers.push(feature);
        self.map_data[mapid].map_bounds.extend(feature.getPosition());

        // Check for eventual simple or OverlappingMarkerSpiderfier click Listener
        var clickListener = oms ? 'spider_click' : 'click';
        google.maps.event.addListener(feature, clickListener, function() {
          self.infowindow_open(mapid, feature);
        });
      }

      // If the feature is a Polyline or a Polygon, add to the Map and extend the Map bounds.
      if (feature.getPath) {
        var feature_options = JSON.parse(self.map_data[mapid].map_geometries_options) || {};
        feature.setOptions(feature_options);
        feature.setMap(map);
        var path = feature.getPath();
        var path_bounds = new google.maps.LatLngBounds();
        path.forEach(function (element) {
          self.map_data[mapid].map_bounds.extend(element);
          path_bounds.extend(element);
        });
        google.maps.event.addListener(feature, 'click', function() {
          self.infowindow_open(mapid, feature, path_bounds.getCenter());
        });
      }

    },

    // Closes and open the Map Infowindow at the input feature.
    infowindow_open: function (mapid, feature, anchor) {
      var self = this;
      var map = self.map_data[mapid].map;
      var properties = feature.get('geojsonProperties');
      if (feature.setTitle && properties && properties.title) {
        feature.setTitle(properties.title);
      }
      map.infowindow.close();
      if (properties.description) {
        map.infowindow.setContent(properties.description);

        /// Note: if the feature is a Marker (and not a Polyline/Polygon) its
        // extensions will override the infowindow anchor position, in the map.
        // infowindow.open method.
        map.infowindow.setPosition(anchor);
        setTimeout(function () {
          map.infowindow.open(map, feature);
        }, 200);
      }
    },

    map_refresh: function (mapid) {
      var self = this;
      setTimeout(function() {
        google.maps.event.trigger(self.map_data[mapid].map, 'resize');
      }, 10);
    },

    // Init Geofield Google Map and its functions.
    map_initialize: function (mapid, map_settings, data, context) {
      var self = this;
      $.noConflict();

      // Checking to see if google variable exists. We need this b/c views breaks this sometimes. Probably
      // an AJAX/external javascript bug in core or something.
      if (typeof google !== 'undefined' && typeof google.maps.ZoomControlStyle !== 'undefined' && data !== undefined) {

        var mapOptions = {
          center: map_settings.map_center ? new google.maps.LatLng(map_settings.map_center.lat, map_settings.map_center.lon) : new google.maps.LatLng(42, 12.5),
          zoom: map_settings.map_zoom_and_pan.zoom.initial ? parseInt(map_settings.map_zoom_and_pan.zoom.initial) : 8,
          minZoom: map_settings.map_zoom_and_pan.zoom.min ? parseInt(map_settings.map_zoom_and_pan.zoom.min) : 1,
          maxZoom: map_settings.map_zoom_and_pan.zoom.max ? parseInt(map_settings.map_zoom_and_pan.zoom.max) : 20,
          gestureHandling: map_settings.map_zoom_and_pan.gestureHandling ? map_settings.map_zoom_and_pan.gestureHandling : 'auto',
          mapTypeId: map_settings.map_controls.map_type_id ? map_settings.map_controls.map_type_id : 'roadmap',
        };

        // Manage the old scrollwheel & draggable settings (deprecated by google maps api).
        if(!map_settings.map_zoom_and_pan.scrollwheel && !map_settings.map_zoom_and_pan.gestureHandling) {
          mapOptions.scrollwheel = false;
        }

        if(!map_settings.map_zoom_and_pan.draggable && !map_settings.map_zoom_and_pan.gestureHandling) {
          mapOptions.draggable = false;
        }

        if(!!map_settings.map_controls.disable_default_ui) {
          mapOptions.disableDefaultUI = map_settings.map_controls.disable_default_ui;
        } else {

          // Implement Custom Style Map, if Set.
          if (map_settings.custom_style_map && map_settings.custom_style_map.custom_style_control && map_settings.custom_style_map.custom_style_name.length > 0 && map_settings.custom_style_map.custom_style_options.length > 0) {
            var customMapStyleName = map_settings.custom_style_map.custom_style_name;
            var customMapStyle = JSON.parse(map_settings.custom_style_map.custom_style_options);
            var styledMapType = new google.maps.StyledMapType(customMapStyle, {name: customMapStyleName});
            map_settings.map_controls.map_type_control_options_type_ids.push('custom_styled_map');
          }

          mapOptions.zoomControl = !!map_settings.map_controls.zoom_control;
          mapOptions.mapTypeControl = !!map_settings.map_controls.map_type_control;
          mapOptions.mapTypeControlOptions = {
            mapTypeIds: map_settings.map_controls.map_type_control_options_type_ids ? map_settings.map_controls.map_type_control_options_type_ids : ['roadmap', 'satellite', 'hybrid'],
            position: google.maps.ControlPosition.TOP_LEFT,
          };
          mapOptions.scaleControl = !!map_settings.map_controls.scale_control;
          mapOptions.streetViewControl = !!map_settings.map_controls.street_view_control;
          mapOptions.fullscreenControl = !!map_settings.map_controls.fullscreen_control;
        }

        // Add map_additional_options if any.
        if (map_settings.map_additional_options.length > 0) {
          var additionalOptions = JSON.parse(map_settings.map_additional_options) ;
          // Transforms additionalOptions "true", "false" values into true & false.
          for (var prop in additionalOptions) {
            if (additionalOptions.hasOwnProperty(prop)) {
              if (additionalOptions[prop] === 'true') {
                additionalOptions[prop] = true;
              }
              if (additionalOptions[prop] === 'false') {
                additionalOptions[prop] = false;
              }
            }
          }
          // Merge mapOptions with additionalOptions.
          $.extend(mapOptions, additionalOptions);
        }

        // Define the Geofield Google Map.
        var map = new google.maps.Map(document.getElementById(mapid), mapOptions);

        // Add the Map Reset Control, if set.
        if (map_settings.map_zoom_and_pan.map_reset) {
          var mapResetControlPosition = 'TOP_RIGHT';

          // Create the DIV to hold the control and call the mapResetControl()
          // constructor passing in this DIV.
          var mapResetControlDiv = document.createElement('div');
          var mapResetControl = new self.map_reset_control(mapResetControlDiv, mapid);
          mapResetControlDiv.index = 1;
          map.controls[google.maps.ControlPosition[mapResetControlPosition]].push(mapResetControlDiv);
        }

        // If defined a Custom Map Style, associate the styled map with custom_styled_map MapTypeId and set it to display.
        if (styledMapType) {
          map.mapTypes.set('custom_styled_map', styledMapType);
          // Set Custom Map Style to Default, if requested.
          if (map_settings.custom_style_map && map_settings.custom_style_map.custom_style_default) {
            map.setMapTypeId('custom_styled_map');
          }
        }

        // Define a mapid self property, so other code can interact with it.
        self.map_data[mapid].map = map;
        self.map_data[mapid].map_options = mapOptions;
        self.map_data[mapid].features = data.features;
        self.map_data[mapid].markers = [];

        // Define the MapBounds property.
        self.map_data[mapid].map_bounds = new google.maps.LatLngBounds();

        // Set the zoom force and center property for the map.
        self.map_data[mapid].zoom_force = !!map_settings.map_zoom_and_pan.zoom.force;
        self.map_data[mapid].center_force = !!map_settings.map_center.center_force;

        // Parse the Geojson data into Google Maps Locations.
        var features = data.features && data.features.length > 0 ? GeoJSON(data) : null;

        if (features && (!features.type || features.type !== 'Error')) {

          /**
           * Implement  OverlappingMarkerSpiderfier if its control set true.
           */
          if (map_settings.map_oms && map_settings.map_oms.map_oms_control && OverlappingMarkerSpiderfier) {
            var omsOptions = map_settings.map_oms.map_oms_options.length > 0 ? JSON.parse(map_settings.map_oms.map_oms_options) : {
              markersWontMove: true,
              markersWontHide: true,
              basicFormatEvents: true,
              keepSpiderfied: true
            };
            self.map_data[mapid].oms = new OverlappingMarkerSpiderfier(map, omsOptions);
          }

          map.infowindow = new google.maps.InfoWindow({
            content: ''
          });

          // Define the icon_image, if set.
          var icon_image = map_settings.map_marker_and_infowindow.icon_image_path.length > 0 ? map_settings.map_marker_and_infowindow.icon_image_path : null;

          if (features.setMap) {
            self.place_feature(features, icon_image, mapid);
          }
          else {
            for (var i in features) {
              if (features[i].setMap) {
                self.place_feature(features[i], icon_image, mapid);
              } else {
                for (var j in features[i]) {
                  if (features[i][j].setMap) {
                    self.place_feature(features[i][j], icon_image, mapid);
                  }
                }
              }
            }
          }

          // Implement Markeclustering, if more than 1 marker on the map,
          // and the markercluster option is set to true.
          if (self.map_data[mapid].markers.length > 1 && typeof MarkerClusterer !== 'undefined' && map_settings.map_markercluster.markercluster_control) {

            var markeclusterOption = {
              imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
            };

            // Add markercluster_additional_options if any.
            if (map_settings.map_markercluster.markercluster_additional_options.length > 0) {
              var markeclusterAdditionalOptions = JSON.parse(map_settings.map_markercluster.markercluster_additional_options);
              // Merge markeclusterOption with markeclusterAdditionalOptions.
              $.extend(markeclusterOption, markeclusterAdditionalOptions);
            }

            // Define a markerCluster property, so other code can interact with it.
            self.map_data[mapid].markerCluster = new MarkerClusterer(map, self.map_data[mapid].markers, markeclusterOption);
          }
        }

        // If the Map Initial State is defined by MapBounds.
        if (!self.mapBoundsAreNull(self.map_data[mapid].map_bounds) && !self.map_data[mapid].center_force) {
          map.fitBounds(self.map_data[mapid].map_bounds);
        }
        // else if the Map Initial State is defined by just One marker.
        else if (self.map_data[mapid].markers.length === 1 && !self.map_data[mapid].center_force) {
          map.setCenter(self.map_data[mapid].markers[0].getPosition());
        }

        google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
          // Force the Map Zoom if requested.
          if (self.map_data[mapid].zoom_force) {
            self.map_data[mapid].map.setZoom(self.map_data[mapid].map_options.zoom);
          }
        });

        // Add an event listener for the Ajax Infowindow Popup.
        google.maps.event.addListener(map.infowindow, 'domready', function(){
          var infowindow_content = document.createElement('div');
          infowindow_content.innerHTML = map.infowindow.getContent().trim();
          var content = $('[data-geofield-google-map-ajax-popup]', infowindow_content);
          if (content.length) {
            var url = content.data('geofield-google-map-ajax-popup');
            $.get(url, function (response) {
              if (response) {
                map.infowindow.setContent(response)
              }
            });
          }
        });

        // At the beginning (once) ...
        google.maps.event.addListenerOnce(map, 'idle', function() {

          // Open the Feature infowindow, if so set.
          if (self.map_data[mapid].map_marker_and_infowindow.force_open && parseInt(self.map_data[mapid].map_marker_and_infowindow.force_open) === 1) {
           // map.setCenter(features[0].getPosition());
            self.infowindow_open(mapid, features[0]);
          }

          // Update map initial state after everything is settled.
          self.map_set_start_state(mapid, map.getCenter(), map.getZoom());

          // Trigger a custom event on Geofield Map initialized, with mapid.
          $(context).trigger('geofieldMapInit', mapid);
        });

      }
    },

    mapBoundsAreNull: function (mapBounds) {
      var north_east = mapBounds.getNorthEast();
      var south_west = mapBounds.getSouthWest();
      return north_east.toString() === south_west.toString();

    },

    map_set_start_state: function (mapid, center, zoom) {
      var self = this;
      self.map_data[mapid].map_start_center = center;
      self.map_data[mapid].map_start_zoom = zoom;
    },
    map_reset_control: function (controlDiv, mapid) {
      // Set CSS for the control border.
      var controlUI = document.createElement('div');
      controlUI.style.backgroundColor = '#fff';
      controlUI.style.border = '2px solid #fff';
      controlUI.style.borderRadius = '3px';
      controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
      controlUI.style.cursor = 'pointer';
      controlUI.style.margin = '6px';
      controlUI.style.textAlign = 'center';
      controlUI.title = Drupal.t('Click to reset the map to its initial state');
      controlDiv.appendChild(controlUI);

      // Set CSS for the control interior.
      var controlText = document.createElement('div');
      controlText.style.color = 'rgb(25,25,25)';
      controlText.style.fontSize = '1.1em';
      controlText.style.lineHeight = '28px';
      controlText.style.paddingLeft = '5px';
      controlText.style.paddingRight = '5px';
      controlText.innerHTML = Drupal.t('Reset Map');
      controlUI.appendChild(controlText);

      // Setup the click event listeners: simply set the map to Chicago.
      controlUI.addEventListener('click', function() {
        Drupal.geoFieldMap.map_data[mapid].map.setCenter(Drupal.geoFieldMap.map_data[mapid].map_start_center);
        Drupal.geoFieldMap.map_data[mapid].map.setZoom(Drupal.geoFieldMap.map_data[mapid].map_start_zoom);
      });
    }

  };

})(jQuery, Drupal, drupalSettings);
