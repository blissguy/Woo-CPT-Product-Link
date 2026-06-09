(function ($) {
	'use strict';

	$(document).ready(function () {
		var $select = $('#woo-cpl-product-select');

		if (!$select.length || typeof $.fn.select2 === 'undefined') {
			return;
		}

		$select.select2({
			allowClear: true,
			placeholder: wooCplAdmin.placeholder,
			minimumInputLength: 2,
			ajax: {
				url: wooCplAdmin.ajaxUrl,
				dataType: 'json',
				delay: 300,
				data: function (params) {
					return {
						action: 'woo_cpl_search_products',
						term: params.term,
						nonce: wooCplAdmin.nonce,
						page: params.page || 1,
					};
				},
				processResults: function (data, params) {
					return {
						results: data.results || [],
						pagination: { more: !!data.more },
					};
				},
			},
		});
	});
})(jQuery);
