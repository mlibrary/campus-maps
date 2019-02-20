/**
 * We are overriding the adding features functionality of the Leaflet module.
 */

(function ($) {
  Drupal.Leaflet.prototype.add_features = function (features, initial) {

    var leaflet_markercluster_options = this.settings.leaflet_markercluster.options && this.settings.leaflet_markercluster.options.length > 0 ? JSON.parse(this.settings.leaflet_markercluster.options) : {};
    var cluster_layer = new L.MarkerClusterGroup(leaflet_markercluster_options);
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
            if (lFeature.setStyle) {
              lFeature.setStyle(Drupal.Leaflet.path);
            }
            if (groupFeature.popup) {
              lFeature.bindPopup(groupFeature.popup);
            }
            lGroup.addLayer(lFeature);
          }
        }

        // @todo we need to correctly handle the groups here
        cluster_layer.addLayer(lGroup);
      }
      else {
        lFeature = this.create_feature(feature);
        if (lFeature !== undefined) {
          if (lFeature.setStyle) {
            lFeature.setStyle(Drupal.Leaflet.path);
          }
          // this.lMap.addLayer(lFeature);
          cluster_layer.addLayer(lFeature);

          if (feature.popup) {
            lFeature.bindPopup(feature.popup);
          }
        }
      }

      // Allow others to do something with the feature that was just added to the map
      $(document).trigger('leaflet.feature', [lFeature, feature, this]);
    }

    // Add all markers to the map
    this.lMap.addLayer(cluster_layer)

    // Fit bounds after adding features.
    this.fitbounds();

    // Allow plugins to do things after features have been added.
    $(document).trigger('leaflet.features', [initial || false, this])
  };

})(jQuery);
