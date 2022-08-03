/* global window, vars */

import Banner from "./components/molecules/Banner.vue.js"
import Login from "./components/molecules/Login.vue.js"
import General from "./components/molecules/General.vue.js"

(function () {

	Vue.component("Banner", Banner)
	Vue.component("Login", Login)
	Vue.component("General", General)

	var vm = new Vue({
		el: document.querySelector('#vue'),
		data: {
			message: "If you see this message that means VueJs works here !",
			vars: vars
		},
		template: `<div class="bootstrap">
						<div class="payplugConfiguration">
							<div class="payplug">
								<Banner/>
								<Login v-if="!vars.logged_in"/>
								<General v-else/>
							</div>
						</div>
					</div>
`,
		mounted: function () {
			console.log(this.message)
			//alert(vars.translations['Title'])
		}
	})

})()
