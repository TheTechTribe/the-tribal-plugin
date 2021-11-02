(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	function initUpdateTabHashLink(){
		var hash = location.hash.replace(/^#/, '');  // ^ means starting, meaning only match the first hash
		if (hash) {
			$('#tttUserDashboard button[data-bs-target="#'+hash+'"]').tab('show');
		} 
		
		$('#tttUserDashboard button').on('shown.bs.tab', function (event) {
			console.log(event);
			var getId = $(event.target).data('bs-target');

			if(history.pushState) {
				history.pushState(null, null, getId);
			} else {
				window.location.hash = getId; //Polyfill for old browsers
			}
			
		});
	}

	var ajaxImportPost = function() {
		function ajaxImportWithoutRealTimeProgress()
		{
			$('.dashboard-form-import').on('submit', function(e){
				e.preventDefault();
				let msg = 'Start import please wait...';
				$( ".import-ajax-status .msg" ).html( msg );
	
				console.log('import');
				var data = {
					'action' : 'ttt_import_post'
				};
				var request = $.ajax({
					url: ajaxurl,
					method: "POST",
					data: data,
					dataType: "json"
				});
				
				request.done(function( msg ) {
					msg = '';
					$( ".import-ajax-status .msg" ).html( msg );
					$( ".import-ajax-status .msg" ).hide();
				});
				
				request.fail(function( jqXHR, textStatus ) {
					msg = "Request failed: " + textStatus;
					$( ".import-ajax-status .msg" ).html( msg );
					$( ".import-ajax-status .msg" ).hide();
				});
			});
		}
		return {
			initWithoutRealTimeProgress: function(){
				ajaxImportWithoutRealTimeProgress();
			}//init: function()
		};
	}();

	$(function(){
		initUpdateTabHashLink();
		initWithoutRealTimeProgress.init();
	});

})( jQuery );
