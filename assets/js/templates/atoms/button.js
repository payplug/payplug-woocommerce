export default {

	props: {
		buttonName: {
			type: String,
			required: false,
		},
		buttonClasses: {
			type: String,
			required: false,
		},
		buttonText: {
			type: String,
			required: false,
		}
	},
	template: `
		<button
			type="button"
			:name="buttonName"
			:class="buttonClasses"
			:data-e2e-name="buttonName">
			{{buttonText}}
		</button>
	`

}







