import {Banner} from "./components/molecules/Banner.vue.js"

(function () {

	Vue.component("Banner", Banner)

	var vm = new Vue({
		el: document.querySelector('#vue'),
		data: {
			message: "If you see this message that means VueJs works here !",
		},
		template: `<div class="payplug">
						<h3>{{message}}</h3>
						<Banner :message="message" />
					</div>
`,
		mounted: function () {
			console.log("This is a the VueJs main component")
		}
	})

})()
