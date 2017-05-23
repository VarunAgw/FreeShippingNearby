/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        '../model/shipping-rates-validator',
        '../model/shipping-rates-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        freeShippingNearbyShippingRatesValidator,
        freeShippingNearbyShippingRatesValidationRules
    ) {
        "use strict";
        defaultShippingRatesValidator.registerValidator('freeshippingnearby', freeShippingNearbyShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('freeshippingnearby', freeShippingNearbyShippingRatesValidationRules);
        return Component;
    }
);
