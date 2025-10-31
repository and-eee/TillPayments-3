(function ($) {
	// Initialize immediately OR wait for ready (in case document isn't ready yet)
	var initTillPayments = function() {
		console.log('✓ Document ready - Till Payments init starting');

		var $paymentForm = $('#till_payments_seamless').closest('form');
	    var $paymentFormSubmitButton = $("#place_order");
	    var $paymentFormTokenInput = $('#till_payments_token');
	    var $tillPaymentsErrors = $('#till_payments_errors');

	    // Get version ID from window object keys matching pattern
	    var versionId = '';
	    for (var key in window) {
	        if (key.startsWith('integrationKey_')) {
	            versionId = key.substring('integrationKey_'.length);
	            break;
	        }
	    }

	    console.log('✓ Version ID detected:', versionId);

	    var integrationKey = versionId ? window['integrationKey_' + versionId] : window.integrationKey;
	    console.log('✓ Integration key:', integrationKey ? integrationKey.substring(0, 5) + '...' : 'UNDEFINED');

	    var initialized = false;
	    var init = function () {
	        console.log('→ Outer init() called. integrationKey:', integrationKey ? 'set' : 'UNDEFINED', ', initialized:', initialized);
	        if (integrationKey && !initialized) {
	            console.log('✓ Conditions met for initialization');
	            $paymentFormSubmitButton.prop("disabled", false);
	            tillPaymentsSeamless.init(
	                integrationKey,
	                function () {
	                    console.log('→ Invalid callback');
	                    $paymentFormSubmitButton.prop("disabled", true);
	                },
	                function () {
	                    console.log('→ Valid callback');
	                    $paymentFormSubmitButton.prop("disabled", false);
	                });
	        } else {
	            console.log('✗ Init conditions NOT met. integrationKey:', !!integrationKey, 'initialized:', initialized);
	        }
	    };

	    $paymentFormSubmitButton.on('click', function (e) {
	        console.log('→ Form submit button clicked');
	        tillPaymentsSeamless.submit(
	            function (token) {
	                console.log('✓ Token received:', token.substring(0, 10) + '...');
	                $paymentFormTokenInput.val(token);
	                $paymentForm.submit();
	            },
	            function (errors) {
	                console.error('✗ Payment errors:', errors);
	                errors.forEach(function (error) {
	                    $tillPaymentsErrors.html(error.message);
	                    console.error(error);
	                });
	            });
	        return false;
	    });

	    var tillPaymentsSeamless = function () {
	        var payment;
	        var validDetails;
	        var validNumber;
	        var validCvv;
	        var _invalidCallback;
	        var _validCallback;
	        var $seamlessForm = $('#till_payments_seamless');
	        var $seamlessCardHolderInput = $('#till_payments_seamless_card_holder', $seamlessForm);
	        var $seamlessEmailInput = $('#till_payments_seamless_email', $seamlessForm);
	        var $seamlessExpiryInput = $('#till_payments_seamless_expiry', $seamlessForm);
	        var $seamlessCardNumberInput = $('#till_payments_seamless_card_number', $seamlessForm);
	        var $seamlessCvvInput = $('#till_payments_seamless_cvv', $seamlessForm);

	        console.log('✓ Till Payments Seamless initialized, form elements found:', {
	            seamlessForm: $seamlessForm.length,
	            cardHolder: $seamlessCardHolderInput.length,
	            cardNumber: $seamlessCardNumberInput.length,
	            cvv: $seamlessCvvInput.length,
	            expiry: $seamlessExpiryInput.length
	        });

	        var init = async function (integrationKey, invalidCallback, validCallback) {
	            console.log('→ Inner init() called with integrationKey:', integrationKey ? integrationKey.substring(0, 5) + '...' : 'UNDEFINED');
	            _invalidCallback = invalidCallback;
	            _validCallback = validCallback;

	            if ($seamlessForm.length === 0) {
	                console.error('✗ Seamless form NOT found in DOM!');
	                return;
	            }

	            console.log('✓ Seamless form found, proceeding with initialization');
	            initialized = true;

	            // Set heights
	            $seamlessCardNumberInput.height($seamlessCardHolderInput.css('height'));
	            $seamlessCvvInput.height($seamlessCardHolderInput.css('height'));

	            // Show form
	            $seamlessForm.show();
	            console.log('✓ Seamless form shown');

	            var style = {
	                'border': $seamlessCardHolderInput.css('border'),
	                'border-radius': $seamlessCardHolderInput.css('border-radius'),
	                'height': $seamlessCardHolderInput.css('height'),
	                'padding': $seamlessCardHolderInput.css('padding'),
	                'font-size': $seamlessCardHolderInput.css('font-size'),
	                'font-weight': $seamlessCardHolderInput.css('font-weight'),
	                'font-family': $seamlessCardHolderInput.css('font-family'),
	                'letter-spacing': '0.1px',
	                'word-spacing': '1.7px',
	                'color': $seamlessCardHolderInput.css('color'),
	                'background': $seamlessCardHolderInput.css('background'),
	            };

	            console.log('→ Waiting for PaymentJs library to load...');

	            const waitForScript = el => {
	                return new Promise((res, rej) => {
	                    let retryCounter = 0;
	                    const findScriptElement = el => {
	                        // Check if PaymentJs library is loaded
	                        if (typeof PaymentJs !== 'undefined') {
	                            console.log('✓ PaymentJs library loaded (attempts:', retryCounter + 1, ')');
	                            res(new PaymentJs('1.3'));
	                        }
	                        else if (retryCounter >= 50) {
	                            console.error('✗ PaymentJs library failed to load after', retryCounter, 'attempts');
	                            rej("Payment Js script failed to load");
	                        }
	                        else {
	                            retryCounter += 1;
	                            if (retryCounter % 10 === 0) {
	                                console.log('  ... still waiting for PaymentJs (attempt ' + retryCounter + '/50)');
	                            }
	                            setTimeout(() => findScriptElement(el), 100);
	                        }
	                    }
	                    findScriptElement(el);
	                });
	            }

	            try {
	                await waitForScript(`[data-main="payment-js"]`).then(p => {
	                    console.log('→ Initializing PaymentJs with integration key');
	                    payment = p;
	                    payment.init(integrationKey, $seamlessCardNumberInput.prop('id'), $seamlessCvvInput.prop('id'), function (payment) {
	                        console.log('✓ PaymentJs init callback executed');

	                        // Find the payment method container dynamically (works with versioned gateway IDs)
	                        var paymentMethodElements = document.querySelectorAll('[class*="payment_method_"]');
	                        console.log('→ Found payment method elements:', paymentMethodElements.length);
	                        paymentMethodElements.forEach(function(element) {
	                            if (element.className.includes('till_payments') && element.className.includes('creditcard')) {
	                                console.log('✓ Setting background transparent for:', element.className);
	                                element.style.background = 'transparent';
	                            }
	                        });

	                        // remove WP injected br tags
	                        const paymentBoxes = document.querySelectorAll('#payment > ul > li > div > div.payment_box');
	                        console.log('→ Found payment boxes:', paymentBoxes.length);
	                        paymentBoxes.forEach(box => {
	                            const brTags = box.querySelectorAll('br');
	                            if (brTags.length > 0) {
	                                console.log('✓ Removing', brTags.length, 'br tags');
	                            }
	                            brTags.forEach(br => {
	                                br.remove();
	                            });
	                        });

	                        console.log('→ Setting up payment event handlers');
	                        payment.enableAutofill();
	                        payment.onAutofill(function(data) {
	                            console.log('→ Autofill triggered');
	                            $('#till_payments_seamless_card_holder').val(data.card_holder);
	                            $('#till_payments_seamless_expiry').val(data.month+"/"+data.year);
	                        });

	                        payment.setNumberStyle(style);
	                        payment.setCvvStyle(style);

	                        payment.numberOn('input', function (data) {
	                            validNumber = data.validNumber;
	                            console.log('→ Card number input event, valid:', data.validNumber);
	                            validate();
	                        });

	                        payment.cvvOn('input', function (data) {
	                            validCvv = data.validCvv;
	                            console.log('→ CVV input event, valid:', data.validCvv);
	                            validate();
	                        });

	                        console.log('✓ PaymentJs fully initialized with all handlers');
	                    });
	                });
	            } catch (e) {
	                console.error('✗ PaymentJs initialization failed:', e);
	            }

	            $('input, select', $seamlessForm).on('input', validate);
	            console.log('✓ Form input event handlers attached');
	        };

	        var validate = function () {
	            $tillPaymentsErrors.html('');
	            validDetails = true;
	            if (!$seamlessCardHolderInput.val().length) {
	                validDetails = false;
	            }
	            if (!$seamlessExpiryInput.val().length) {
	                validDetails = false;
	            }
	            if (validNumber && validCvv && validDetails) {
	                _validCallback.call();
	                return;
	            }
	        };

	        var reset = function () {
	            console.log('→ Resetting seamless form');
	            $seamlessForm.hide();
	        };

	        // add in forward slash to mm/yy
	        function onExpiryInputChange(e) {
	            if (e.target.value.length > 2 && !e.target.value.includes("/")) {
	                document.getElementById("till_payments_seamless_expiry").value = e.target.value.slice(0, 2) + "/" + e.target.value.slice(2)
	            }
	        }

	        // Wait for expiry input to exist before attaching handler
	        var expiryRetries = 0;
	        var attachExpiryHandler = setInterval(function() {
	            var expiryInput = document.getElementById("till_payments_seamless_expiry");
	            if (expiryInput) {
	                console.log('✓ Attaching expiry input handler');
	                expiryInput.addEventListener("input", onExpiryInputChange);
	                clearInterval(attachExpiryHandler);
	            } else if (expiryRetries > 20) {
	                console.error('✗ Could not find expiry input element');
	                clearInterval(attachExpiryHandler);
	            }
	            expiryRetries++;
	        }, 100);

	        // hide loader
	        function removeLoader() {
	            var loader = document.getElementById("loader");
	            if (loader) {
	                console.log('✓ Removing loader');
	                loader.style.display = "none";
	            }
	        };

	        window.addEventListener('load', function() {
	            console.log('→ Window load event fired');
	            // Safely check for iframe before attaching event listener
	            var iframe = document.querySelector("iframe");
	            if (iframe) {
	                console.log('✓ Found iframe, attaching load listener');
	                iframe.addEventListener("load", removeLoader);
	            } else {
	                console.log('→ No iframe found yet, will try again');
	                setTimeout(function() {
	                    iframe = document.querySelector("iframe");
	                    if (iframe) {
	                        console.log('✓ Found iframe on retry, attaching load listener');
	                        iframe.addEventListener("load", removeLoader);
	                    }
	                }, 500);
	            }
	        });

	        var submit = function (success, error) {
	            console.log('→ Submit called');
	            var expiryData = $seamlessExpiryInput.val().split('/');
	            payment.tokenize({
	                    card_holder: $seamlessCardHolderInput.val(),
	                    month: expiryData[0],
	                    year: expiryData[1],
	                    email: $seamlessEmailInput.val()
	                },
	                function (token, cardData) {
	                    console.log('✓ Token generated successfully');
	                    success.call(this, token);
	                },
	                function (errors) {
	                    console.error('✗ Tokenization errors:', errors);
	                    error.call(this, errors);
	                }
	            );
	        };

	        return {
	            init: init,
	            reset: reset,
	            submit: submit,
	        };
	    }();

	    console.log('→ Calling outer init()');
	    init();

	    // Also try init after a delay in case integration key wasn't set yet
	    setTimeout(function() {
	        console.log('→ Attempting delayed init (in case integration key was late)');
	        if (!initialized) {
	            init();
	        }
	    }, 500);
	});
})(jQuery);
