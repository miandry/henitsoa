/**
 * @file
 * Javascript file for the FooTable module.
 */

(function ($) {
  'use strict';
  $(document).ready(function(){
    $('.footable').footable();
  });
  Drupal.behaviors.footable = {
    attach: function (context) {
     // $('.footable').footable();
    }
  };

}(jQuery));
