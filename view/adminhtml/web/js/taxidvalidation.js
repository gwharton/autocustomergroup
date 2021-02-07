define([
    'jquery',
    'Magento_Sales/order/create/scripts'
], function (jQuery) {
    'use strict';
    AdminOrder.prototype.validateTaxId = function(parameters){
        var params = {
            country: $(parameters.countryElementId).value,
            postcode: $(parameters.postcodeElementId).value,
            tax: $(parameters.taxIdElementId).value
        };

        if (this.storeId !== false) {
            params.store_id = this.storeId;
        }

        var currentCustomerGroupId = $(parameters.groupIdHtmlId)
            ? $(parameters.groupIdHtmlId).value : '';

        new Ajax.Request(parameters.validateUrl, {
            parameters: params,
            onSuccess: function (response) {
                var message = '';
                var groupActionRequired = null;
                try {
                    response = response.responseText.evalJSON();

                    if (null === response.group) {
                        if (true === response.valid) {
                            message = parameters.taxIdValidMessage;
                        } else if (true === response.success) {
                            message = parameters.taxIdInvalidMessage.replace(/%s/, params.taxId);
                        } else {
                            message = parameters.taxIdValidationFailedMessage;
                        }
                    } else {
                        if (true === response.valid) {
                            message = parameters.taxIdValidAndGroupValidMessage;
                            if (currentCustomerGroupId != response.group) {
                                message = parameters.taxIdValidAndGroupChangeMessage;
                                groupActionRequired = 'change';
                            }
                        } else if (response.success) {
                            message = parameters.taxIdInvalidMessage.replace(/%s/, params.taxId);
                            groupActionRequired = 'inform';
                        } else {
                            message = parameters.taxIdValidationFailedMessage;
                            groupActionRequired = 'inform';
                        }
                    }
                } catch (e) {
                    message = parameters.taxIdValidationFailedMessage;
                }
                if (null === groupActionRequired) {
                    alert(message);
                } else {
                    this.processCustomerGroupChange(
                        parameters.groupIdHtmlId,
                        message,
                        parameters.taxIdCustomerGroupMessage,
                        parameters.taxIdGroupErrorMessage,
                        response.group,
                        groupActionRequired
                    );
                }
            }.bind(this)
        });
    }
});
