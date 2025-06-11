/**
 * List Record Management
 *
 * @copyright: Copyright (C) 2025, Jlowcode Organization - All rights reserved.
 * @license  : GNU/GPL http  //www.gnu.org/copyleft/gpl.html
 */
define(['jquery', 'fab/fabrik', 'fab/list-plugin'], function (jQuery, Fabrik, FbListPlugin) {
    var FbListRecords_management = new Class({
        Extends   : FbListPlugin,

        Implements: [Events],

        options: {},

        initialize: function (options) { 
            // Init options
            this.options = JSON.parse(options);

            if(this.options.canUse === false) {
                return;
            }
            
            this.setButtons();
        },

        /**
         * Set event and add button to the middle buttons in template
         * 
         */
        setButtons: function () {
            var self = this;

            jQuery('.middle-buttons').prepend(
                jQuery('<li>').append(
                    jQuery('<a>', {
                        'id'   : 'check-deadlines',
                        'text' : Joomla.JText._('PLG_FABRIK_LIST_RECORDS_MANAGEMENT_CHECK_DEADLINES')
                    }).css('cursor', 'pointer').on('click', function (e) {
			            Fabrik.loader.start(jQuery('.listContent'), Joomla.JText._('COM_FABRIK_LOADING'));
                        e.preventDefault();
                        self.requestCheckDeadlines();
                    })
                )
            );
        },

        /* 
        * Ajax function to check deadlines
        */
        requestCheckDeadlines: function () {
            var self = this;

            var data = {
                'option'   : 'com_fabrik',
                'format'   : 'raw',
                'task'     : 'plugin.pluginAjax',
                'plugin'   : 'records_management',
                'method'   : 'checkDeadlines',
                'g'        : 'list',
                'listid'   : this.options.listId,
            }

            jQuery.ajax({
				'url'   : '',
				'method': 'post',
				'data'  : data
			}).done(function (response) {
                response = JSON.parse(response);

                if(response['error']) {
                    alert(Joomla.JText._("PLG_FABRIK_LIST_RECORDS_MANAGEMENT_ERROR"));
                    console.warn(response['message']);
				    location.reload();
                    return;
                }

                alert(Joomla.JText._("PLG_FABRIK_LIST_RECORDS_MANAGEMENT_DEADLINES_CHECKED"));
                Fabrik.loader.stop(jQuery('.listContent'));
				location.reload();
            }).fail(function (jq, status, error) {
				var message = {
					url: '',
					data: data,
					error: error,
					status: status,
					jq: jq
				};

				self.saveLogs(message);
			});
        },
      
        /**
		 * This function send a request to save the log in log table
		 * 
		 */
		saveLogs: function (message) {
			alert(Joomla.JText._("PLG_FABRIK_LIST_RECORDS_MANAGEMENT_ERROR"));

			jQuery.ajax({
				url     : '',
				method	: 'post',
				data	: {
					message: JSON.stringify(message),
					option: 'com_fabrik',
					format: 'raw',
					task: 'plugin.pluginAjax',
					g: 'list',
					plugin: 'records_management',
					method: 'saveLogs'
				}
			}).done(function (r) {
				location.reload();
			});
		}
    });

    return FbListRecords_management;
});
