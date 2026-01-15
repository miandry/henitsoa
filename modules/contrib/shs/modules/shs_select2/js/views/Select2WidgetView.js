/**
 * @file
 * A Backbone view for a shs widget optionally rendered as select2 element.
 */

(function ($, Drupal, drupalSettings, Backbone) {
  'use strict';

  Drupal.shs_select2 = Drupal.shs_select2 || {};

  Drupal.shs_select2.Select2WidgetView = Drupal.shs.WidgetView.extend({

    /**
     * @inheritdoc
     */
    render: function () {
      var widget = Drupal.shs.WidgetView.prototype.render.apply(this);

      widget.$el
        .addClass('select2-widget')
        // @todo Applying configuration here won't work for e.g. placeholder
        //    (it's ignored by select2 for some reason.). Also these
        //    configuration options may be configurable in field settings.
        .attr('data-select2-config', "{\"width\":\"100%\", \"minimumResultsForSearch\": 0}");

      Drupal.behaviors.select2.attach(widget.container.$el.get(0));

      return widget;
    }
  });

}(jQuery, Drupal, drupalSettings, Backbone));
