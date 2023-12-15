jQuery(function ($) {
  const kec_checkout = {
    /**
     * Initialize the Klarna Express Checkout button.
     */
    init() {
      const { client_token } = kec_checkout_params;

      Klarna.Payments.init({
        client_token: client_token,
      });

      // Add listener to load the iframe.
      $(document.body).on("updated_checkout", kec_checkout.loadIframe);

      // Add on place order listener.
      $(document.body).on("click", "#place_order", kec_checkout.onPlaceOrder);
    },

    /**
     * Returns true if Klarna Payments is selected as payment method.
     *
     * @returns {boolean}
     */
    kpSelected() {
      return $("#payment_method_klarna_payments").is(":checked");
    },

    /**
     * Load the Klarna Payments iframe.
     */
    loadIframe() {
      console.log("loadIframe");
      Klarna.Payments.load({
        container: "#klarna_payments_container",
      });
    },

    /**
     * Handle on place order.
     *
     * @param {object} event
     */
    onPlaceOrder(event) {
      if (!kec_checkout.kpSelected()) {
        return;
      }

      event.preventDefault();
      kec_checkout.blockCheckout();
      $("form.checkout").addClass("processing");

      const checkoutResult = kec_checkout.checkout();

      if (!checkoutResult) {
        return;
      }

      const payload = checkoutResult.payload;
      const addresses = checkoutResult.addresses;
      const orderId = checkoutResult.order_id;
      const orderKey = checkoutResult.order_key;

      payload.billing_address = addresses.billing;
      payload.shipping_address = addresses.shipping;

      // Authorize the Klarna payment.
      Klarna.Payments.finalize({}, payload, (res) =>
        kec_checkout.onFinalizeHandler(res, orderId, orderKey)
      );
    },

    /**
     * Place the order with WooCommerce.
     *
     * @returns {object|boolean}
     */
    checkout() {
      const { url, method } = kec_checkout_params.ajax.checkout;
      let checkoutResult = false;

      $.ajax({
        type: method,
        url: url,
        data: $("form.checkout").serialize(),
        dataType: "json",
        async: false,
        success: function (response) {
          if (response.result === "success") {
            checkoutResult = response || false;
          } else {
            const messages = `<div class="woocommerce-error">${response.messages}</div>`;
            kec_checkout.onCheckoutError(messages || "Checkout error");
            return false;
          }
        },
      });

      return checkoutResult;
    },

    /**
     * Block the checkout from user interaction.
     *
     * @returns {void}
     */
    blockCheckout() {
      $("form.checkout").block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });
    },

    /**
     * Unblock the checkout.
     *
     * @returns {void}
     */
    unblockCheckout() {
      $("form.checkout").unblock();
    },

    /**
     * On checkout error.
     *
     * @param {string} errorMessage
     * @returns {void}
     */
    onCheckoutError(errorMessage) {
      $("body").trigger("updated_checkout");
      $("form.checkout").removeClass("processing");

      $(".woocommerce-NoticeGroup-checkout").remove();

      $("form.checkout").prepend(
        "<div class='woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout'></div>"
      );

      $(document.body).trigger("checkout_error", [errorMessage]);

      $(".woocommerce-NoticeGroup-checkout").prepend(errorMessage);

      // Scroll to the top of woocommerce checkout.
      $("html, body").animate(
        {
          scrollTop: (
            $(".woocommerce-checkout").offset().top -
            $(".woocommerce-checkout").height()
          ).toString(),
        },
        1000
      );

      kec_checkout.unblockCheckout();
    },

    /**
     * Get the payload from the server.
     *
     * @returns {object|boolean}
     */
    getPayload() {
      const { url, nonce, method } = kec_checkout_params.ajax.get_payload;
      let payload = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
        },
        async: false,
        success: function (response) {
          payload = response;
        },
      });

      return payload;
    },

    /**
     * Make the finalize ajax call.
     *
     * @param {object} result
     * @param {string} orderId
     * @param {string} orderKey
     *
     * @returns {object|boolean}
     */
    finalize(result, orderId, orderKey) {
      const { url, nonce, method } = kec_checkout_params.ajax.finalize_callback;
      let finalizeResult = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
          result: result,
          order_id: orderId,
          order_key: orderKey,
        },
        async: false,
        success: function (response) {
          if (response.success) {
            finalizeResult = response.data || false;
          } else {
            kec_checkout.onCheckoutError(response.data || "Checkout error");
            return false;
          }
        },
      });

      return finalizeResult;
    },

    /**
     * Handle the finalize callback.
     *
     * @param {object} response The response from Klarna.
     * @param {string} orderId The order id.
     * @param {string} orderKey The order key.
     */
    onFinalizeHandler(response, orderId, orderKey) {
      if (response.approved) {
        const finalizeResult = kec_checkout.finalize(
          response,
          orderId,
          orderKey
        );

        // Redirect to the redirect url from the result.
        if (finalizeResult && finalizeResult.redirect) {
          window.location.href = finalizeResult.redirect;
        }
      } else {
        const messages =
          '<div class="woocommerce-error">Klarna payment was not approved.</div>';
        kec_checkout.onCheckoutError(messages);
      }
    },
  };

  window.klarnaAsyncCallback = kec_checkout.init();
});
