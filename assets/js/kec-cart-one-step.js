jQuery(function ($) {
  const kec_cart = {
    selectedVariation: null,

    /**
     * Initialize the Klarna Express Checkout button.
     */
    init() {
      // Load the Klarna Express Checkout button.
      kec_cart.load();

      // Add event listener to the cart update button.
      $(document.body).on("updated_cart_totals", kec_cart.load);

      $(document.body).on("woocommerce_variation_has_changed", kec_cart.onVariationSelectChange);
      $(document.body).on("found_variation", kec_cart.onFoundVariation);
    },

    /**
     * Load the klarna express button.
     *
     * @returns {void}
     */
    load() {
      if (!kec_cart.checkVariation()) {
        return;
      }

      // Check if the button already exist.
      if (null !== document.querySelector('#kec-pay-button').shadowRoot) {
        return;
      }

      // Wait until the window.Klarna.Payments.Buttons is available before initializing the button.
      const interval = setInterval(() => {
        if (window.Klarna && window.Klarna.Payments && window.Klarna.Payments.Buttons) {
          clearInterval(interval);
          kec_cart.initKlarnaButton();
        }
      }, 100);

      // Stop trying to load the button after 2 seconds.
      setTimeout(() => clearInterval(interval), 2000);
    },

    /**
     * Initialize the Klarna button.
     *
     * @returns {void}
     */
    initKlarnaButton() {
      const { client_id, theme, shape, locale } = kec_cart_params;

      window.Klarna.Payments.Buttons.init({
        client_id,
      }).load({
        container: "#kec-pay-button",
        theme: theme,
        shape: shape,
        locale: locale
      });
    },

    /**
     * Handle the change selected variation event.
     * If no variation is selected, the KEC button will be hidden.
     *
     * @returns {void}
     */
    onVariationSelectChange() {
      let variationIsSelected = kec_cart.checkVariation() ? true : false;
      $('#kec-pay-button').toggle(variationIsSelected);
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
      kec_cart.selectedVariation = variation;

      kec_cart.load();
    },

    /**
     * Checks if we are on a product page, and if so, if the product is a variable product.
     * If it is, it will see if the customer has selected a variation.
     *
     * @returns {boolean} True if we can continue, or if we need to wait for a variation to be set.
     */
    checkVariation() {
      const { is_product_page, product } = kec_cart_params;

      if (!is_product_page) {
        return true;
      }

      if (product.type !== "variable") {
        return true;
      }

      return kec_cart.selectedVariation !== null;
    },
  };

  window.klarnaAsyncCallback = kec_cart.init();
});
