(function (Drupal, $) {
  "use strict";
  Drupal.behaviors.entityReferenceFilter = {
    attach: function (context, settings) {
      if (settings['entityreference_filter']) {
        $.each(settings['entityreference_filter'], function (form_id, filter_setting) {

          let form = $('#' + form_id, context);
          if (form.length === 0) {
            return;
          }

          let dependent_filters_data = filter_setting['dependent_filters_data'];
          let ajax_path = filter_setting['view']['ajax_path'];

          if (ajax_path.constructor.toString().indexOf('Array') !== -1) {
            ajax_path = ajax_path[0];
          }

          let controlling_filters = {};
          let controlling_filters_names = {};
          let dependent_filters = {};
          let dependent_filters_names = {};

          // Build controlling and dependent filters array to react
          // on their change and collect their elements and names.
          if (dependent_filters) {
            $.each(dependent_filters_data, function (dependent_filter_name, dep_controlling_filters) {
              $.each(dep_controlling_filters, function (index, controlling_filter_name) {

                //Dependent filters
                let elementDependentFilter = form.find('[name="' + dependent_filter_name + '"],[name="' + dependent_filter_name + '[]"]');
                if (elementDependentFilter.length > 0) {
                  // disable autocomplete.
                  elementDependentFilter.attr('autocomplete', 'off');

                  dependent_filters[dependent_filter_name] = elementDependentFilter;
                  dependent_filters_names[dependent_filter_name] = dependent_filter_name;
                }

                //Controlling filters
                let elementControllingFilter = form.find('[name="' + controlling_filter_name + '"],[name="' + controlling_filter_name + '[]"]');
                if (elementControllingFilter.length > 0) {
                  // disable autocomplete.
                  elementControllingFilter.attr('autocomplete', 'off');

                  controlling_filters[controlling_filter_name]       = elementControllingFilter;
                  controlling_filters_names[controlling_filter_name] = controlling_filter_name;
                }
              });
            });
          }

          $.each(controlling_filters, function (filter_name, filter_element) {
            filter_element.once('entityreference_filter').change(function (event) {
              let submitValues = {};

              // get current input data RAW.
              // Controlling filters.
              $.each(controlling_filters, function (filter_n, filter_el) {
                submitValues[filter_n] = filter_el.val();
              });

              // Dependent filters.
              $.each(dependent_filters, function (filter_n, filter_el) {
                submitValues[filter_n] = filter_el.val();
              });

              $.extend(submitValues, filter_setting, {
                'controlling_filters': controlling_filters_names,
                'dependent_filters': dependent_filters_names,
                'form_id': form_id
              });

              let elementSettings = {
                url: ajax_path,
                submit: submitValues,
              };

              let ajax = new Drupal.Ajax(false, false, elementSettings);

              // Send request
              ajax.eventResponse(ajax, event);
            });
          });
        });
      }
    }
  };

  /**
   * Command to insert new content into the DOM without wrapping in extra DIV
   * element.
   */
  Drupal.AjaxCommands.prototype.entityReferenceFilterInsertNoWrapCommand = function (ajax, response, status) {
    // Get information from the response. If it is not there, default to
    // our presets.
    let wrapper = response.selector ? $(response.selector) : $(ajax.wrapper);
    let method = response.method || ajax.method;
    let effect = ajax.getEffect(response);

    // We don't know what response.data contains: it might be a string of text
    // without HTML, so don't rely on jQuery correctly interpreting
    // $(response.data) as new HTML rather than a CSS selector. Also, if
    // response.data contains top-level text nodes, they get lost with either
    // $(response.data) or $('<div></div>').replaceWith(response.data).
    let new_content_wrapped = $('<div></div>').html(response.data);
    let new_content = new_content_wrapped.contents();

    // If removing content from the wrapper, detach behaviors first.
    let settings = response.settings || ajax.settings || Drupal.settings || {};
    let wrapperEl = wrapper.get(0);

    Drupal.detachBehaviors(wrapperEl, settings);

    // Show or hide filter depending on its values and
    // `hide empty filter` option
    let elHidden = wrapper.parent().parent().hasClass('hidden');
    let elHasValues = settings['has_values'];
    let hideEmptyFilter = settings['hide_empty_filter'];

    if (hideEmptyFilter) {
      if (!elHasValues && !elHidden) {
        wrapper.parent().wrap('<div class="hidden"></div>');
      }
      if (elHasValues && elHidden) {
        wrapper.parent().unwrap();
      }
    }

    // Add the new content to the page.
    wrapper[method](new_content);

    // Immediately hide the new content if we're using any effects.
    if (effect.showEffect !== 'show') {
      new_content.hide();
    }

    //@todo what is it ?
    // Determine which effect to use and what content will receive the
    // effect, then show the new content.
    if ($('.ajax-new-content', new_content).length > 0) {
      $('.ajax-new-content', new_content).hide();
      new_content.show();
      $('.ajax-new-content', new_content)[effect.showEffect](effect.showSpeed);
    }
    else if (effect.showEffect !== 'show') {
      new_content[effect.showEffect](effect.showSpeed);
    }

    // Attach all JavaScript behaviors to the new content, if it was
    // successfully added to the page, this if statement allows
    // #ajax['wrapper'] to be optional.
    if (new_content.parents('html').length > 0) {
      // Apply any settings from the returned JSON if available.
      settings = response.settings || ajax.settings || Drupal.settings;
      Drupal.attachBehaviors(wrapperEl, settings);
    }
  };
})(Drupal, jQuery);
