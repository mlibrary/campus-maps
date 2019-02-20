/**
 * @file
 * Disable Field javascript functions.
 */

(function ($) {

Drupal.behaviors.disable_field = {
    attach: function (context) {
    jQuery("#edit-add-disable-add-disable").change(function () {
        if(this.checked){
        }
        else {
          jQuery("#edit-roles-add").val('');
        }
      });
    jQuery("#edit-edit-disable-edit-disable").change(function () {
        if(this.checked){
        }
        else {
          jQuery("#edit-roles-edit").val('');
        }
    });
  }
};
}(jQuery));
