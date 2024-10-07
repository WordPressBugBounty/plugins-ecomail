jQuery(document).ready(function ($) {
	const trackCart = (items = null, email = null) => {
		$.ajax({
			url: ecomailArgs.restUrl + '/cart',
			method: 'post',
			data: {
				email: email,
				items: items,
			},
			success: function (response) {
				console.log(response);
			}
		}, 'json');
	};

	const trackLastViewedProduct = (productId) => {
		$.ajax({
			url: ecomailArgs.restUrl + '/product',
			method: 'post',
			data: {
				product_id: productId,
			},
			success: function (response) {
				console.log(response);
			}
		}, 'json');
	};

	if (ecomailArgs.cartTrackingEnabled) {
		$(document.body).on('added_to_cart', () => {
			trackCart();
		});
		$(document.body).on('removed_from_cart', () => {
			trackCart();
		});
		$(document.body).on('cart_totals_refreshed', () => {
			trackCart();
		});

		if (typeof ecomailCart !== 'undefined') {
			trackCart(ecomailCart.items);
		}

		const validateEmail = (email) => {
			const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
			return re.test(String(email).toLowerCase());
		};

		$('input#billing_email').on('change', function () {
			if (ecomailArgs.emailExists) {
				return false;
			}

			const email = $(this).val();
			if (validateEmail(email)) {
				trackCart(null, email);
			}
		});
	}
	if (ecomailArgs.lastProductTrackingEnabled && ecomailArgs.productId) {
		trackLastViewedProduct(ecomailArgs.productId);
	}
});
