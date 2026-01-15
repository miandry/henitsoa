<?php

namespace Drupal\stripe\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element that will be rendered by stripe elements.
 *
 * @see https://stripe.com/docs/elements
 *
 * Usage example:
 * @code
 * @endcode
 * *
 * @FormElement("stripe")
 */
class CC extends StripeBase {

  protected static $type = 'card';

}
