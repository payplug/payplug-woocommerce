( function() {
	var vm = new Vue({
		el: document.querySelector('#mount'),
		template: "<h1>My Latest Posts Widget</h1>",
		mounted: function(){
			console.log("Hello Vue!");
		}
	});
})();


