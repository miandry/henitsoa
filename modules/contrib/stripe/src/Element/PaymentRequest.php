<?php

namespace Drupal\stripe\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element that will be rendered by stripe elements and provide.
 * a payment request button if available.
 *
 * @see https://stripe.com/docs/elements
 *
 * Usage example:
 * @code
 * @endcode
 * *
 * @FormElement("stripe_paymentrequest")
 */
class PaymentRequest extends StripeBase {

  protected static $type = 'paymentrequest';
}
