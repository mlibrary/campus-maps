### **Geofield Map**

is an advanced, complete and easy-to-use Geo Mapping solution for Drupal 8, 
based on and fully compatible with the 
[Geofield](https://www.drupal.org/project/geofield "Geofield") module, 
that **lets you manage the Geofield with an interactive Map both in back-end 
and in the front-end.** It represents the perfect solution to:

*   geolocate (with one or more Locations / Geofields) any fieldable Drupal 
entity throughout an Interactive Geofield Map widget;
*   render each Content's Locations throughout a fully customizable Interactive 
Geofield Map Formatter;
*   expose and query Contents throughout fully customizable Map Views 
Integration;
*   implement advanced front-end Google Maps with Marker Icon & Infowindow 
advanced customizations, custom Google Map Styles and Marker Clustering 
capabilities;
*   customize Map Geometries properties (Lines & Polylines, Polygons, 
Multipolygons, etc.), based on Google Maps Polygons APIs

* * *

#### Geofield Map 2.x. What's New

### **Dynamic Map Theming & Contextual Legends.**

The Geofield Map 2.x new version allows the Geofield Map View definition of Custom 
Markers & Icons Images based on dynamic values of the Map features.

As an absolute novelty and uniqueness in the history of Drupal CMS (!), 
moreover, a custom Geofield Map Legend Block is defined by the module and is 
able to expose each Map Theming logics defined in the application in the form 
of fully configurable and impressive Legends.

<u>Compatibility:</u> This Geofield Map 2.x new version is fully compatible
 with the existing Geofield Map 1.x version (<u>Drupal cache needs to be 
 cleaned right after updating the code</u>).  
You are (should be) free to upgrade cleanly, without loosing any of your 
existing Geofield Map settings (!).

### **Technical Functionalities and specifications**

The actual module release implements the following components:

#### **Geofield Map widget**

An highly customizable Map widget, providing an interactive and very intuitive 
map on which to perform localization and input of geographic coordinates 
throughout:

*   MULTIPOINTS Geofield mapping support;
*   Google Geocoding and [Google Maps Places Autocomplete Service](https://developers.google.com/maps/documentation/javascript/examples/places-autocomplete);
*   Google Map or Leaflet Map JS libraries and interfaces;
*   Map click and marker dragging, with instant reverse geocoding;
*   HTML5 Geolocation of the user position;
*   the possibility to permanently store the Geocoded address into the Entity 
Title or in a "string type" field (among the content's ones);
*   etc.

#### **Geofield Map Formatter**

An higly customizable Google Map formatter, by which render and expose the 
contents Geofields / Geolocations, throughout:

*   a wide set of Map options fully compliant with 
[Google Maps Js v3 APIs](https://developers.google.com/maps/documentation/javascript/ "Google Maps Js v3 APIs");
*   the possibility to fully personalize the Map Marker Icon and its Infowindow 
content;
*   the integration of 
[Markecluster Google Maps Library](https://github.com/googlemaps/js-marker-clusterer "Markecluster Google Maps Library") functionalities and its personalization;

A "Geofield Google Map (static)" formatter is also available, that renders a 
Google Map, accordingly to the [Google Maps Static API](https://developers.google.com/maps/documentation/maps-static/dev-guide)
(only Points supported, and not Geometries such as Polylines, Polygons, etc.).
The use of the static maps API is significantly cheaper than the dynamic map. The
drawback is that the map is displayed as a static image (i.e. without any
controls, zoom, pan, etc). 
For more information on pricing you can consult the 
[Google Maps Static Pricing documentation](https://developers.google.com/maps/billing/understanding-cost-of-use#static-maps).

#### Views Integration

A dedicated Geofield Map View style plugin able to render a Views result on a 
higly customizable Google Map, with Marker and Infowindow specifications and 
Markers Clustering capabilities.

#### Advanced Google Map and Markeclustering Features for the front-end maps

Both in Geofield Map Formatter and in the Geofield Map View style it is 
possible:

*   to add additional Map and Markecluster Options, as Object Literal in valid 
Json format;
*   define and manage a [Google Custom Map Style](https://developers.google.com/maps/documentation/javascript/examples/maptype-styled-simple "Google Custom Map Style")
*   use the [Overlapping Marker Spiderfier Library (for Google Maps)](https://github.com/jawj/OverlappingMarkerSpiderfier#overlapping-marker-spiderfier-for-google-maps-api-v3 "Overlapping Marker Spiderfier Library (for Google Maps)") to manage overlapping markers;

### **Basic Installation and Use**

Geofield Map module needs to be installed [using Composer to manage Drupal site dependencies](https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies), which will also download the required [Geofield Module](https://www.drupal.org/project/geofield) dependency and PHP libraries).

It means simply running the following command from your project root 
(where the main composer.json file is sited):

**$ composer require 'drupal/geofield_map'**

Once done, you can setup the following:

*   Geofield Widget: In a Content Type including a Geofield Field, go to 
"Manage form display" and select "Geofield Map" as Geofield Widget. Specify the
 Widget further settings for both Google or Leaflet Map types;
*   Geofield Google Map Formatter: In a Content Type including a Geofield Field,
go to "Manage display" and select "Geofield Google Map" as Geofield field 
Formatter. Specify the Formatter further settings for specific personalization;
*   Geofield Map Views: In a View Display select the Geofield Google Map Format,
 and be sure to add a Geofield type field in the fields list. Specify the View 
 Format settings for specific personalization;

#### Hints for Advanced Use

*   For each GeofieldMapWidget it is possible the enable (and custom configure) 
addresses Geocoding via the [Google Maps Places Autocomplete Service](https://developers.google.com/maps/documentation/javascript/examples/places-autocomplete).
*   GeofieldMapWidget uses Leaflet MapTypes/Tiles pre-defined as 
LeafletTileLayers D8 plugins, but any third party module is able to define and 
add its new LeafletTileLayer Plugins;
*   As default (configurable) option, eventual overlapping markers will be 
Spiderfied, with the support of the [Overlapping Marker Spiderfier Library (for Google Maps)](https://github.com/jawj/OverlappingMarkerSpiderfier#overlapping-marker-spiderfier-for-google-maps-api-v3 "Overlapping Marker Spiderfier Library (for Google Maps)");
*   The Geofield Map View style plugin will pass to the client js 
(as drupalSettings.geofield_google_map[mapid] & Drupal.geoFieldMap[mapid] 
variables) the un-hidden fields values of the View, as markers/features' 
properties data;

### **Geofield Map 2.x Dynamic Markers Theming & Legends Specifications**

Geofield Map 2.x introduces the MapThemer Plugin system that allows the 
definition of MapThemer Plugins able to dinamically differentiate Map 
Features/Markers based on Contents Types, Taxonomy Terms, Values, etc. Each 
Plugin Type provides the automatic definition of a related Legend Build, that 
is able to fill the definition of a Custom GeofieldMapLegend block.

At the moment the following two Geofield Map Themers plugin types have been 
defined:

*   Custom Icon Image File, allows the definition of a unique custom Marker 
Icon, valid for all the Map Markers;
*   Entity Type, allows the definition of different Marker Icons based on the 
View filtered Entity Types/Bundles;
*   Taxonomy Term, allows the definition of different Marker Icons based on 
Taxonomy Terms reference field in View;
*   List Type Field, allows the definition of different Marker Icons based on 
List (Options) Type fields in View;

As Drupal 8 Plugin system based, the Geofield MapThemers Plugin and Legend 
block system is fully extendable and overridable. You, as D8 developer, are 
free to override and extend the existing ones, or create your custom MapThemer 
based on your specific needs and logics.

#### How to use.

In a Geofield Map View Display, just go into its settings and choose the wanted 
MapThemer in the new Map Theming Options section/fieldset. It is possible to 
associate a Drupal Managed File for each MapThemer plugin value and even the 
Icon Image style the Icon should be rendered on the Map. The Value labels and 
Icons might have an alias, might be reordered and might be hidden from the 
correspondent Legend Block.

Once defined and configured the Legend you are free t   o place it, once or 
several times, as a normal Drupal 8 block on the pages, with your logics and 
contextual rules.

#### **Notes & Warnings**

*   The Geofield Map module depends from the 
[Geofield](https://www.drupal.org/project/geofield) module;
*   A unique [Gmap Api Key](https://developers.google.com/maps/documentation/javascript/get-api-key) 
is required for both Google Mapping and Geocoding operations, all performed 
client-side by js. 
[It might/should be restricted using the Website Domain / HTTP referrers method](https://developers.google.com/maps/documentation/javascript/get-api-key#key-restrictions).

#### **Roadmap / Planned evolution**
*   Full integration with [Geocoder](https://www.drupal.org/project/geocoder) 
module;
*   Leaflet Map library support for the Geofield Map Formatter and the Map 
Views Plugin. Now please refer to the 
[Leaflet](https://www.drupal.org/project/leaflet "Leaflet") and the 
[Leaflet Markercluster](https://www.drupal.org/project/leaflet_markercluster) 
modules for Leaflet front-end mapping of Drupal 8 Geofields;