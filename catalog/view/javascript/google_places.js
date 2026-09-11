/**
 * Optional Google Places autocomplete for address forms. Only active when the
 * store owner has entered a Google Places API key in Setting > Setting > Store
 * (config_google_places_key). Restricted to Iran explicitly (componentRestrictions),
 * rather than relying on Google's IP-based guess, since that is unreliable on
 * local/dev servers and inconsistent for users on VPNs.
 *
 * Runs alongside the built-in استان/شهرستان/شهر picker (iran_geo.js): once a
 * place is picked, the resolved province is applied to the zone select, which
 * still triggers the normal county/settlement cascade.
 */
function ocGooglePlacesReady() {
	['shipping-', 'payment-', ''].forEach(function(prefix) {
		ocGooglePlacesInit(prefix);
	});
}

function ocGooglePlacesInit(prefix) {
	var addressInput = document.getElementById('input-' + prefix + 'address-1');

	if (!addressInput || typeof google === 'undefined' || !google.maps || !google.maps.places || addressInput.dataset.googlePlacesBound) {
		return;
	}

	addressInput.dataset.googlePlacesBound = '1';

	var autocomplete = new google.maps.places.Autocomplete(addressInput, {
		componentRestrictions: {country: 'ir'},
		fields: ['address_components', 'formatted_address'],
		types: ['geocode']
	});

	autocomplete.addListener('place_changed', function() {
		var place = autocomplete.getPlace();

		if (!place || !place.address_components) {
			return;
		}

		var get = function(type) {
			var component = place.address_components.find(function(c) {
				return c.types.indexOf(type) !== -1;
			});

			return component ? component.long_name : '';
		};

		var streetNumber = get('street_number');
		var route = get('route');
		var neighborhood = get('neighborhood') || get('sublocality_level_1') || get('sublocality');
		var city = get('locality') || get('administrative_area_level_2');
		var province = get('administrative_area_level_1');
		var postcode = get('postal_code');

		var line1 = (route + ' ' + streetNumber).trim();

		if (line1) {
			addressInput.value = line1;
		}

		var $address2 = $('#input-' + prefix + 'address-2');

		if (neighborhood && $address2.length && !$address2.val()) {
			$address2.val(neighborhood);
		}

		var $postcode = $('#input-' + prefix + 'postcode');

		if (postcode && $postcode.length) {
			$postcode.val(postcode);
		}

		var $city = $('#input-' + prefix + 'city');

		if (city && $city.length) {
			$city.val(city);
		}

		if (province) {
			var $zone = $('#input-' + prefix + 'zone');
			var normalize = function(s) {
				return s.replace(/province/gi, '').replace(/استان/g, '').trim();
			};
			var target = normalize(province);

			$zone.find('option').each(function() {
				var text = normalize($(this).text());

				if (text && (text === target || text.indexOf(target) !== -1 || target.indexOf(text) !== -1)) {
					$zone.val($(this).val()).trigger('change');

					return false;
				}
			});
		}
	});
}
