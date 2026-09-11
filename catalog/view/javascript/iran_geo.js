/**
 * Wires an official استان -> شهرستان -> شهر/روستا picker onto an existing OpenCart
 * address form (province/zone select + free-text city input). When the selected
 * province has no data in the Iran geo tables (i.e. any non-Iran country/zone),
 * the extra selects simply never appear and the plain city text field is used as before.
 */
function ocIranGeoInit(prefix) {
	var $zone = $('#input-' + prefix + 'zone');
	var $cityInput = $('#input-' + prefix + 'city');
	var $cityField = $cityInput.closest('.mb-3');

	if (!$zone.length || !$cityInput.length || !$cityField.length || $zone.data('iranGeoBound')) {
		return;
	}

	$zone.data('iranGeoBound', true);

	var isRow = $cityField.hasClass('row');
	var wrapClass = isRow ? 'row mb-3 required' : 'col mb-3 required';
	var labelClass = isRow ? 'col-sm-2 col-form-label' : 'form-label';
	var controlOpen = isRow ? '<div class="col-sm-10">' : '';
	var controlClose = isRow ? '</div>' : '';

	var countyWrapId = 'iran-geo-county-wrap-' + prefix;
	var settlementWrapId = 'iran-geo-settlement-wrap-' + prefix;
	var countySelectId = 'input-' + prefix + 'iran-county';
	var settlementSelectId = 'input-' + prefix + 'iran-settlement';

	var $county = $(
		'<div class="' + wrapClass + '" id="' + countyWrapId + '" style="display:none;">' +
			'<label class="' + labelClass + '" for="' + countySelectId + '">شهرستان</label>' +
			controlOpen +
			'<select class="form-select" id="' + countySelectId + '"><option value="">شهرستان را انتخاب کنید</option></select>' +
			controlClose +
		'</div>'
	);

	var $settlement = $(
		'<div class="' + wrapClass + '" id="' + settlementWrapId + '" style="display:none;">' +
			'<label class="' + labelClass + '" for="' + settlementSelectId + '">شهر / روستا</label>' +
			controlOpen +
			'<select class="form-select" id="' + settlementSelectId + '"><option value="">ابتدا شهرستان را انتخاب کنید</option></select>' +
			controlClose +
		'</div>'
	);

	$cityField.after($settlement).after($county);

	$zone.on('change', function() {
		var zoneId = $(this).val();

		$('#' + countyWrapId).hide();
		$('#' + settlementWrapId).hide();
		$cityField.show();

		if (!zoneId) {
			return;
		}

		$.ajax({
			url: 'index.php?route=localisation/iran_geo.county&zone_id=' + zoneId,
			dataType: 'json',
			success: function(json) {
				if (json.county && json.county.length) {
					var html = '<option value="">شهرستان را انتخاب کنید</option>';

					for (var i = 0; i < json.county.length; i++) {
						html += '<option value="' + json.county[i].shahrestan_id + '">' + json.county[i].name + '</option>';
					}

					$('#' + countySelectId).html(html);
					$('#' + settlementSelectId).html('<option value="">ابتدا شهرستان را انتخاب کنید</option>');
					$('#' + countyWrapId).show();
					$cityField.hide();
					$cityInput.val('');
				}
			}
		});
	});

	$(document).on('change', '#' + countySelectId, function() {
		var shahrestanId = $(this).val();

		$('#' + settlementWrapId).hide();

		if (!shahrestanId) {
			return;
		}

		$.ajax({
			url: 'index.php?route=localisation/iran_geo.city&shahrestan_id=' + shahrestanId,
			dataType: 'json',
			success: function(json) {
				var html = '<option value="">شهر یا روستا را انتخاب کنید</option>';

				if (json.cities && json.cities.length) {
					html += '<optgroup label="شهر">';

					for (var i = 0; i < json.cities.length; i++) {
						html += '<option value="' + json.cities[i].name + '">' + json.cities[i].name + '</option>';
					}

					html += '</optgroup>';
				}

				if (json.villages && json.villages.length) {
					html += '<optgroup label="روستا">';

					for (var j = 0; j < json.villages.length; j++) {
						html += '<option value="' + json.villages[j].name + '">' + json.villages[j].name + '</option>';
					}

					html += '</optgroup>';
				}

				$('#' + settlementSelectId).html(html);
				$('#' + settlementWrapId).show();
			}
		});
	});

	$(document).on('change', '#' + settlementSelectId, function() {
		$cityInput.val($(this).val());
	});
}
