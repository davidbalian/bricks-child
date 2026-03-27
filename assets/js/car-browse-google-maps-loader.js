/**
 * Load Google Maps API only when the location modal needs it (first open).
 */
(function () {
	'use strict';

	window.autoagoraCarBrowseMapsQueue = [];
	window.autoagoraCarBrowseMapsLoading = false;

	window.autoagoraOnMapsApiLoaded = function () {
		window.autoagoraCarBrowseMapsLoading = false;
		var q = window.autoagoraCarBrowseMapsQueue.splice(0);
		for (var i = 0; i < q.length; i++) {
			try {
				q[i]();
			} catch (e) {}
		}
	};

	window.autoagoraLoadCarBrowseMaps = function (callback) {
		if (typeof callback !== 'function') {
			return;
		}
		if (window.google && window.google.maps) {
			callback();
			return;
		}
		window.autoagoraCarBrowseMapsQueue.push(callback);
		if (window.autoagoraCarBrowseMapsLoading) {
			return;
		}
		var cfg = window.autoagoraCarBrowseMapsConfig || {};
		if (!cfg.scriptUrl) {
			window.autoagoraCarBrowseMapsLoading = false;
			return;
		}
		window.autoagoraCarBrowseMapsLoading = true;
		var s = document.createElement('script');
		s.src = cfg.scriptUrl;
		s.async = true;
		s.defer = true;
		s.onerror = function () {
			window.autoagoraCarBrowseMapsLoading = false;
			window.autoagoraCarBrowseMapsQueue = [];
		};
		document.head.appendChild(s);
	};
})();
