(function () {

	var vm = new Vue({
		el: document.querySelector('#vue'),
		data: {
			message: "If you see this message that means VueJs works here !",
		},
		template: `<div id="payplug" style="margin-bottom: 800px">
						<h3>{{message}}</h3>
					</div>
`,
		mounted: function () {
			console.log("This is a the VueJs main component")
		}
	});

})();
