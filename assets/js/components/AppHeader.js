export default {
	name: 'Test',

	setup() {
		const title = "Header";

		return {
			title
		};
	},
	template: `
		  <section class="payplugUiBlock descriptionBlock" data-e2e-name="blockDescription">
			<div class="_content">
			  <div class="_header">
				<div class="_logo">
				  <!-- image banner-->
				  <svg width="242" height="50" viewBox="0 0 242 50" fill="none" xmlns="http://www.w3.org/2000/svg"></svg>
				</div>
				<div class="_version">
				  <p class="payplugUIParagraph _descriptionVersion"> V 3.8.2 </p>
				  <div class="payplugUISelect -disabled _show">
					<div class="_current">
					  <div class="_value">
						<input class="_input" type="radio" id="payplug_show-0" value="0" name="payplug_show" data-e2e-name="payplug_show-0" checked="checked">
						<span class="_text">Hidden plugin</span>
					  </div>
					  <div class="_value">
						<input class="_input" type="radio" id="payplug_show-1" value="1" name="payplug_show" data-e2e-name="payplug_show-1">
						<span class="_text">Visible plugin</span>
					  </div>
					</div>
					<div class="_listWrapper">
					  <div class="_list">
						<ul>
						  <li>
							<label class="_option" for="payplug_show-0" aria-hidden="aria-hidden">Hidden plugin</label>
						  </li>
						  <li>
							<label class="_option" for="payplug_show-1" aria-hidden="aria-hidden">Visible plugin</label>
						  </li>
						</ul>
					  </div>
					</div>
				  </div>
				</div>
			  </div>
			  <p class="payplugUIParagraph _descriptionTitle">
				The payment solution that increases your sales.
			  </p>
			  <p class="payplugUIParagraph _descriptionText">
				PayPlug is the French payment solution for SMEs. Boost your performance thanks to our turnkey, conversion-oriented tools.
			  </p>
			</div>
		  </section>
`,
};
