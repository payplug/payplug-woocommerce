/* global window, vars */

import {General} from './components/General.vue.js';

(function () {

	Vue.component("General", General);

	var vm = new Vue({
		el: document.querySelector('#mount'),
		data: {
			title: vars.options.title,
			description: vars.options.description,
		},
		template: `<div id="payplug">
						<div id="loading"><div class="loader"></div></div>
						<div id="success-message">Settings Saved Successfully ! <a>OK</a></div>
						<General :title="title" :description="description" />
					</div>
`,
		mounted: function () {
			jQuery("#payplug #success-message a").on("click", function () {
				jQuery('#payplug #success-message').hide()
			})
		}
	});

})();

