jQuery(function ($) {
  const kec_cart = {
    /**
     * Initialize the Klarna Express Checkout button.
     */
    init() {
      const { client_key, theme, shape } = kec_cart_params;

      window.Klarna.Payments.Buttons.init({
        client_key: client_key,
      }).load({
        container: "#kec-pay-button",
        theme: theme,
        shape: shape,
        on_click: (authorize) => {
          kec_cart.onClickHandler(authorize);
        },
      });
    },

    onClickHandler(authorize) {
      const payload = kec_cart.getPayload();

      if (!payload) {
        return;
      }

      // Authorize the Klarna payment.
      authorize(
        { auto_finalize: false, collect_shipping_address: true },
        payload,
        (result) => kec_cart.onAuthorizeHandler(result)
      );
    },

    async onAuthorizeHandler(authorizeResult) {
      // Send the authorize result to the server.
      const authCallbackResult = await kec_cart.authCallback(authorizeResult);

      if (!authCallbackResult) {
        return;
      }

      // Redirect the customer to the redirect url.
      window.location.href = authCallbackResult;
    },

    /**
     * Get the order data from the server.
     *
     * @returns {object|boolean}
     */
    getPayload() {
      const { url, nonce, method } = kec_cart_params.ajax.get_payload;
      let payload = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
        },
        async: false,
        success: (result) => {
          payload = result.data || false;
        }
      });

      return payload;
    },

    /**
     * The auth callback ajax request.
     *
     * @param {object} authorizeResult
     * @returns {object|boolean}
     */
    authCallback(authorizeResult) {
      const { url, nonce, method } = kec_cart_params.ajax.auth_callback;
      let result = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
          result: authorizeResult,
        },
        async: false,
        success: (response) => {
          if( response.success ) {
            result = response.data || false;
          } else {
            return false;
          }
        }
      });

      return result;
    }
  };

  window.klarnaAsyncCallback = kec_cart.init();
});
