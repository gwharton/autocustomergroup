define([
    'jquery',
    'Magento_Ui/js/form/element/abstract',
    'ko',
    'mage/url',
    'uiRegistry',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function (
    $,
    Abstract,
    ko,
    url,
    registry,
    setShippingInformation,
    quote,
    $t
) {
    'use strict';

    return Abstract.extend({
        defaults: {
            timeout: null,
            delay: 0,
            success: ko.observable(false),
            prompt: ko.observable(false),
            isChanging: false,
            retry: false,
            retryText: $t('Check again'),
            schemes: [],
            storeId: 0,
            patterns: {
                    'AT' : '(AT)U[0-9]{8}$',
                    'BE' : '(BE)0[0-9]{9}$',
                    'BG' : '(BG)[0-9]{9,10}$',
                    'CY' : '(CY)[0-9]{8}[A-Z]$',
                    'CZ' : '(CZ)[0-9]{8,10}$',
                    'DE' : '(DE)[0-9]{9}$',
                    'DK' : '(DK)[0-9]{8}$',
                    'EE' : '(EE)[0-9]{9}$',
                    'GR' : '(EL|GR)[0-9]{9}$',
                    'EL' : '(EL|GR)[0-9]{9}$',
                    'ES' : '(ES)[0-9A-Z][0-9]{7}[0-9A-Z]$',
                    'FI' : '(FI)[0-9]{8}$',
                    'FR' : '(FR)[0-9A-Z]{2}[0-9]{9}$',
                    'HR' : '(HR)[0-9]{11}$',
                    'HU' : '(HU)[0-9]{8}$',
                    'IE' : '(IE)[0-9][0-9A-Z][0-9]{5}[A-Z]{1,2}$',
                    'IT' : '(IT)[0-9]{11}$',
                    'LT' : '(LT)([0-9]{9}|[0-9]{12}$)',
                    'LU' : '(LU)[0-9]{8}$',
                    'LV' : '(LV)[0-9]{11}$',
                    'MT' : '(MT)[0-9]{8}$',
                    'NL' : '(NL)[0-9]{9}B[0-9]{2}$',
                    'PL' : '(PL)[0-9]{10}$',
                    'PT' : '(PT)[0-9]{9}$',
                    'RO' : '(RO)[0-9]{2,10}$',
                    'SE' : '(SE)[0-9]{12}$',
                    'SI' : '(SI)[0-9]{8}$',
                    'SK' : '(SK)[0-9]{10}$',
                    'GB' : '(GB)([0-9]{9}([0-9]{3})?|[A-Z]{2}[0-9]{3}$)',
                    'IM' : '(GB)00([0-9]{7}([0-9]{3})?$)',
                    'NO' : '(8|9)[0-9]{8}$',
                    'NZ' : '[0-9]{8,9}$',
                    'AU' : '[0-9]{11}$'
            }
        },

        initialize: function (options) {
            this._super();
            this.initObservable();
            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('success');
            this.observe('prompt');
            quote.shippingAddress.subscribe(this.shippingAddressObserver.bind(this));

            return this;
        },

        clearMessages: function () {
            this.success(false);
            this.warn(false);
            this.bubble('success');
            this.bubble('warn');
        },

        shippingAddressObserver: function (address) {
            let schemeId = this.getSchemeIdFromCountry(address.countryId);
            if(schemeId != null) {
                let prompt = this.schemes[schemeId].prompt;
                this.prompt(prompt);
                this.bubble('prompt');
                this.onUpdate(this.value());
            } else {
                this.prompt(false);
                this.bubble('prompt');
                this.clearMessages();
            }
        },

        getSchemeIdFromCountry: function (countryId) {
            let retval = null;
            let taxSchemes = this.schemes;

            Object.keys(this.schemes).forEach( function(schemeId) {
                if (taxSchemes[schemeId]['countries'].includes(countryId)) {
                    retval = schemeId;
                }
            }, this.schemes);
            return retval;
        },

        onUpdate: function (value) {
            if (this.timeout !== null) {
                clearTimeout(this.timeout);
            }
            this.clearMessages();
            var self = this;

            if (value !== ''
                && value.length > 3
                && !this.isChanging
            ) {
                this.timeout = setTimeout(function () {
                    value = value.replace(/[\W_]/g, "").toUpperCase().trim();
                    self.value(value);
                    self.isChanging = true;
                    self.validate(value);
                    self.isChanging = false;
                }, this.delay);
            }
        },

        isSupportedCountry: function (countryId) {
            let supported = false;
            let taxSchemes = this.schemes;

            Object.keys(this.schemes).forEach( function(schemeId) {
                if (taxSchemes[schemeId]['countries'].includes(countryId)) {
                    supported = true;
                }
            }, this.schemes);
            return supported;
        },

        getCountry: function () {
            let country = registry.get(this.parentName + '.' + 'country_id');
            if (typeof(country) !== 'object' || country.value() === '') {
                return false;
            }
            return country.value();
        },

        validate: function (value) {
            this.clearMessages();
            var countryCode = this.getCountry();

            if (this.isChanging && value !== '')
            {
                if (!this.isSupportedCountry(countryCode)) {
                    return;
                }

                if (typeof(this.patterns[countryCode]) !== 'undefined') {
                    var regex = new RegExp(this.patterns[countryCode]);
                    if (regex.test(value)) {
                        return this.validateTaxId(countryCode, value);
                    } else {
                        this.retry = false;
                        this.warn($t('Invalid Format'));
                        this.bubble('warn');
                    }
                }
            }
        },

        validateTaxId: function (countryCode, taxId) {
            $('body').trigger('processStart');
            var formKey = $.cookie('form_key');
            var self = this;
            quote.shippingAddress().vatId = taxId;
            $.ajax({
                type: 'POST',
                url: url.build('autocustomergroup/ajax/validate/'),
                data: {
                    tax_id: taxId,
                    country_code: countryCode,
                    form_key: formKey,
                    store_id: this.storeId
                },
                success: function (response) {
                    self.clearMessages();
                    if (quote.shippingMethod() === null) {
                        var nullCarrier = {method_code: null, carrier_code: null};
                        quote.shippingMethod(nullCarrier);
                    }
                    if (response.valid === true) {
                        self.success(response.message);
                        self.bubble('success');
                        setShippingInformation();
                    } else {
                        self.retry = true;
                        self.warn(response.message);
                        self.bubble('warn');
                        setShippingInformation();
                    }
                    $('body').trigger('processStop');
                },
                error: function (response) {
                    self.retry = true;
                    self.warn($t('There was an error validating your Tax Id'));
                    self.bubble('warn');
                    $('body').trigger('processStop');
                }
            });
        },

        retryValidation: function () {
            this.isChanging = true;
            this.validate(this.value());
            this.isChanging = false;
        }
    });
});
