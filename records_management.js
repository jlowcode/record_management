/**
 * List Record Management
 *
 * @copyright: Copyright (C) 2025, Jlowcode Organization - All rights reserved.
 * @license  : GNU/GPL http  //www.gnu.org/copyleft/gpl.html
 */
define(['jquery', 'fab/fabrik', 'fab/list-plugin'], function (jQuery, Fabrik, FbListPlugin) {
    var FbListAction = new Class({
        Extends   : FbListPlugin,

        // Implements: [Events],

        initialize: function (options) { 
            // Init options
            this.options = JSON.parse(options);
            // Get the button
            var actionButton = jQuery('.actionButton');

            this.setPHPTrigger(actionButton);
        },

        /* 
        * Ajax function to run php code on server 
        */
        runPHPFunction: function () {
            const phpString = this.options.phpCode;
            return jQuery.ajax({
				'url'   : 'index.php',
				'method': 'get',
				'data'  : {
					'option'   : 'com_fabrik',
					'format'   : 'raw',
					'task'     : 'plugin.pluginAjax',
					'plugin'   : 'action',
					'method'   : 'ProcessPHPAction',
					'phpString': phpString,
					'g'        : 'list'
				}
			});
        }
    });
    return FbListAction;
});
