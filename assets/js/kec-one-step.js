const $ = jQuery;
const { klarna_interoperability } = await import("@klarna/interoperability_token");
let configData = {};

const params = document.getElementById(
  'wp-script-module-data-@klarna/kec-one-step'
);

if (params?.textContent) {
  try { configData = JSON.parse(params.textContent); } catch { }
}

const KECOneStep = {
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

  /**
   * Initialize the Klarna Express Checkout script for One Step Checkout.
   *
   * @returns {Promise<void>}
   */
  init: async function (e) {
    KECOneStep.params = configData;
    KECOneStep.Klarna = klarna_interoperability.Klarna;

    KECOneStep.Klarna.Payment
      .button( {
        theme: KECOneStep.params.theme,
        shape: KECOneStep.params.shape,
        locale: KECOneStep.params.locale,
        intents: ["PAY"],
        initiationMode: "DEVICE_BEST",
        initiate: async () => await KECOneStep.onClickPayButton()
      })
      .mount("#kec-pay-button");

    KECOneStep.Klarna.Payment.on("shippingaddresschange", KECOneStep.onShippingAddressChange);
    KECOneStep.Klarna.Payment.on("shippingoptionselect", KECOneStep.onShippingOptionSelect);
  },

  /**
   * Handle the initiate request from Klarna when the customer clicks the payment button.
   *
   * @return {Promise<Object>} The payment request object.
   */
  onClickPayButton: async function () {
    if( KECOneStep.isInitiating ) {
      return;
    }
    KECOneStep.isInitiating = true;

    const { url, nonce, method } = KECOneStep.params.ajax.get_initiate_body;

    const result = await $.ajax({
      type: method,
      url: url,
      data: {
        nonce: nonce,
        source: KECOneStep.params.source,
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
    const { url, nonce, method } = KECOneStep.params.ajax.shipping_change;

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
    const { url, nonce, method } = KECOneStep.params.ajax.shipping_option_change;

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

window.kecOneStep = KECOneStep;

$('body').on('klarna_wc_sdk_loaded', KECOneStep.init);
