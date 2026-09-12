/**
 * Replaces every native <input type="date"> (which only ever shows a
 * Gregorian calendar, regardless of browser locale) with a Jalali/Shamsi
 * calendar powered by JalaliDatePicker (view/javascript/jalalidatepicker/).
 *
 * The original input is kept in the DOM as a hidden field with its original
 * name/id, so every existing filter/form still submits and reads a plain
 * Gregorian Y-m-d string exactly as before - no PHP changes needed. A new
 * visible, read-only text input takes its place and shows/collects the
 * Jalali date; JalaliDatePicker's built-in gregorian target-value feature
 * keeps the hidden field in sync.
 *
 * Only loaded when the admin language direction is rtl (see
 * admin/controller/common/header.php).
 */
(function () {
	'use strict';

	function gregorianToJalali(gy, gm, gd) {
		var gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		var jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

		var gy2 = gy - 1600;
		var gm2 = gm - 1;
		var gd2 = gd - 1;

		var gDayNo = 365 * gy2 + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400);

		for (var i = 0; i < gm2; i++) {
			gDayNo += gDaysInMonth[i];
		}

		if (gm2 > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0)) {
			gDayNo++;
		}

		gDayNo += gd2;

		var jDayNo = gDayNo - 79;
		var jNp = Math.floor(jDayNo / 12053);
		jDayNo %= 12053;

		var jy = 979 + 33 * jNp + 4 * Math.floor(jDayNo / 1461);
		jDayNo %= 1461;

		if (jDayNo >= 366) {
			jy += Math.floor((jDayNo - 1) / 365);
			jDayNo = (jDayNo - 1) % 365;
		}

		var i2 = 0;
		for (; i2 < 11 && jDayNo >= jDaysInMonth[i2]; i2++) {
			jDayNo -= jDaysInMonth[i2];
		}

		return [jy, i2 + 1, jDayNo + 1];
	}

	// House style for this project: امرداد (not مرداد), Persian digits (not Latin).
	var PERSIAN_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'امرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
	var PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

	function toPersianDigits(string) {
		return String(string).replace(/[0-9]/g, function (d) {
			return PERSIAN_DIGITS[d];
		});
	}

	function toLatinDigits(string) {
		return String(string).replace(/[۰-۹]/g, function (d) {
			return String(PERSIAN_DIGITS.indexOf(d));
		});
	}

	function pad2(n) {
		n = String(n);
		return n.length < 2 ? '0' + n : n;
	}

	function toJalaliDisplay(isoValue) {
		if (!isoValue) {
			return '';
		}

		var parts = isoValue.split('-');

		if (parts.length !== 3) {
			return '';
		}

		var gy = parseInt(parts[0], 10);
		var gm = parseInt(parts[1], 10);
		var gd = parseInt(parts[2], 10);

		if (!gy || !gm || !gd) {
			return '';
		}

		var j = gregorianToJalali(gy, gm, gd);

		return toPersianDigits(j[0] + '/' + pad2(j[1]) + '/' + pad2(j[2]));
	}

	var uid = 0;
	var watching = false;

	function enhance(input) {
		if (input.dataset.jalaliEnhanced) {
			return;
		}

		input.dataset.jalaliEnhanced = '1';
		uid++;

		var hiddenId = 'jdp-gregorian-' + uid;

		var visible = document.createElement('input');
		visible.type = 'text';
		visible.className = input.className;

		if (input.id) {
			visible.id = input.id;
			input.removeAttribute('id');
		}

		if (input.placeholder) {
			visible.placeholder = input.placeholder;
		}

		visible.autocomplete = 'off';
		visible.readOnly = true;
		visible.setAttribute('data-jdp', '');
		visible.setAttribute('data-jdp-target-value-input', '#' + hiddenId);
		visible.setAttribute('data-jdp-target-value-type', 'gregorian');
		visible.value = toJalaliDisplay(input.value);

		// JalaliDatePicker only ever reads/writes its own input's value with
		// Latin digits (both to know which day is "selected" when the
		// calendar opens, and right after firing 'jdp:change' to compute the
		// hidden gregorian field). Persian digits are purely a display
		// concern layered on top: switch back to Latin the moment the field
		// is focused (before the library reads it to open the calendar), and
		// back to Persian once its own synchronous handling of the change is
		// done (hence the setTimeout deferral below).
		visible.addEventListener('focus', function () {
			if (visible.value) {
				visible.value = toLatinDigits(visible.value);
			}
		});

		visible.addEventListener('jdp:change', function () {
			setTimeout(function () {
				if (visible.value) {
					visible.value = toPersianDigits(visible.value);
				}
			}, 0);
		});

		input.type = 'hidden';
		input.id = hiddenId;

		input.parentNode.insertBefore(visible, input);
	}

	function scan() {
		var inputs = document.querySelectorAll('input[type="date"]:not([data-jalali-enhanced])');

		if (!inputs.length) {
			return;
		}

		inputs.forEach(enhance);

		if (window.jalaliDatepicker && !watching) {
			watching = true;

			window.jalaliDatepicker.startWatch({
				targetValueInput: 'attr',
				targetValueType: 'attr',
				autoReadOnlyInput: true,
				showTodayBtn: true,
				showEmptyBtn: true,
				persianDigits: true,
				months: PERSIAN_MONTHS
			});
		}
	}

	function start() {
		scan();

		// Product/coupon/etc forms add new date fields dynamically (discount
		// rows, option rows, ...) - keep enhancing those as they appear.
		new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
