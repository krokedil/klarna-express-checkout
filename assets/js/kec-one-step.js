// Import the Klarna SDK
const { KlarnaSDK } = await import(
    "https://js.klarna.com/web-sdk/v2/klarna.mjs"
);

jQuery(function ($) {
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
    klarna: null,
    isInitiating: false,

    /**
     * Initialize the Klarna Express Checkout script for One Step Checkout.
     *
     * @returns {Promise<void>}
     */
    init: async function () {
      KECOneStep.params = kec_one_step_params;
      KECOneStep.klarna = await KlarnaSDK({
          clientId: KECOneStep.params.client_id,
          environment: KECOneStep.params.testmode ? 'playground' : 'production',
      });

      KECOneStep.klarna.Payment
        .button( {
          theme: KECOneStep.params.theme,
          shape: KECOneStep.params.shape,
          locale: KECOneStep.params.locale,
          intents: ["PAY"],
          initiationMode: "ON_PAGE",
          initiate: async () => await KECOneStep.onClickPayButton()
        })
        .mount("#kec-pay-button");

      KECOneStep.klarna.Payment.on("shippingaddresschange", KECOneStep.onShippingAddressChange);
      KECOneStep.klarna.Payment.on("shippingoptionselect", KECOneStep.onShippingOptionSelect);
      KECOneStep.klarna.Payment.on("complete", (data) => console.log(data));
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

      console.log("Klarna Payment Button Clicked");

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

    onShippingAddressChange: async function (paymentRequest, shippingAddress) {
      console.log("Shipping address changed:", paymentRequest, shippingAddress);
      const { url, nonce, method } = KECOneStep.params.ajax.shipping_change;

      const result = await $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
          shippingAddress: shippingAddress
        },
      });

      if (result.error) {
        console.error("Error updating shipping address:", result.error);
        throw new Error(result.error);
      }

      const body = result.data || {};

      return body
    },

    onShippingOptionSelect: async function (paymentRequest, shippingOption) {
      console.log("Shipping option selected:", paymentRequest, shippingOption);
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
    }
  }

  // Initialize Klarna One Step
  KECOneStep.init();
});
