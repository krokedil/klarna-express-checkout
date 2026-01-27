const $ = jQuery;
const { klarna_interoperability } = await import("@klarna/interoperability_token");
let configData = {};

const params = document.getElementById(
  'wp-script-module-data-@klarna/kec-two-step'
);

if (params?.textContent) {
  try { configData = JSON.parse(params.textContent); } catch { }
}

const KECTwoStep = {
  params: {
    ajax: {},
    client_id: '',
    testmode: false,
    theme: 'dark',
    shape: 'default',
    locale: false,
    currency: '',
    amount: 0,
    source: 'unknown'
  },
  Klarna: null,
  isInitiating: false,
  variationId: null,

  /**
   * Initialize the Klarna Express Checkout script for Two Step Checkout.
   *
   * @returns {Promise<void>}
   */
  init: async function (e) {
    KECTwoStep.params = configData;
    KECTwoStep.Klarna = klarna_interoperability.Klarna;

    KECTwoStep.Klarna.Payment
      .button( {
        theme: KECTwoStep.params.theme,
        shape: KECTwoStep.params.shape,
        locale: KECTwoStep.params.locale,
        intents: ["PAY"],
        initiationMode: "DEVICE_BEST",
        initiate: async () => await KECTwoStep.onClickPayButton()
      })
      .mount("#kec-pay-button");

    KECTwoStep.Klarna.Payment.on("shippingaddresschange", KECTwoStep.onShippingAddressChange);
    KECTwoStep.Klarna.Payment.on("shippingoptionselect", KECTwoStep.onShippingOptionSelect);

    // Listen for the WooCommerce variation change event and set the selected variation ID.
    $(document.body).on("found_variation", KECTwoStep.onFoundVariation);

  },

  /**
   * Checks if we are on a product page, and if so, if the product is a variable product.
   * If it is, it will see if the customer has selected a variation.
   *
   * @returns {boolean} True if we can continue, or if we need to wait for a variation to be set.
   */
  checkVariation() {
    const { source, is_variation } = KECTwoStep.params;

    if (source === 'cart' || !is_variation) {
      return true;
    }

    return KECTwoStep.variationId !== null; // Variation must be selected to continue
  },

  /**
   * Handle the found variation event.
   *
   * @param {object} event
   * @param {object} variation
   *
   * @returns {void}
   */
  onFoundVariation(event, variation) {
    KECTwoStep.variationId = variation.variation_id;
  },

  /**
   * Handle the initiate request from Klarna when the customer clicks the payment button.
   *
   * @return {Promise<Object>} The payment request object.
   */
  onClickPayButton: async function () {
    if( KECTwoStep.isInitiating ) {
      return;
    }
    KECTwoStep.isInitiating = true;

    const { url, nonce, method } = KECTwoStep.params.ajax.get_initiate_body;

    const result = await $.ajax({
      type: method,
      url: url,
      data: {
        nonce: nonce,
        source: KECTwoStep.params.source,
        variation_id: KECTwoStep.variationId,
      },
    });

    if (result.error) {
      console.error("Error initiating Klarna payment:", result.error);
      throw new Error(result.error);
    }

    const body = result.data || {};
    return body;
  },

  onShippingAddressChange: async function (data, shippingAddress) {
    const { url, nonce, method } = KECTwoStep.params.ajax.shipping_change;

    const result = await $.ajax({
      type: method,
      url: url,
      data:{
        nonce: nonce,
        shippingAddress: shippingAddress,
        paymentRequestId: data.paymentRequestId,
        paymentToken: data.stateContext.paymentToken
      },
    });

    if (result.error) {
      throw new Error(result.error);
    }

    const body = result.data || {};

    return body
  },

  onShippingOptionSelect: async function (_, shippingOption) {
    const { url, nonce, method } = KECTwoStep.params.ajax.shipping_option_change;

    const result = await $.ajax({
      type: method,
      url: url,
      data: {
        nonce: nonce,
        selectedOption: shippingOption.shippingOptionReference
      },
    });

    if (result.error) {
      console.error("Error updating selected shipping method:", result.error);
      throw new Error(result.error);
    }

    const body = result.data || {};

    return body
  },
}

window.KECTwoStep = KECTwoStep;

$('body').on('klarna_wc_sdk_loaded', KECTwoStep.init);
