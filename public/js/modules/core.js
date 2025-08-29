// TL;DR Pro Core Module
(function(window) {
	'use strict';
	
	let TLDRCore = {
		version: '2.5.2',
		config: {},
		utils: {},
		init: function() {
			console.log('TL;DR Pro Core initialized');
		}
	};
	
	// Export for global use
	window.TLDRCore = TLDRCore;
	
})(window);