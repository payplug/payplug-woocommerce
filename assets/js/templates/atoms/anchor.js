export default {

	props: {
		href: {
			type: String,
			required: true,
		},
		title: {
			type: String,
			required: false,
		},
		target: {
			type: String,
			required: false,
		},
		name: {
			type: String,
			required: false,
		},
		className: {
			type: String,
			required: false,
		}
	},
	template: `
		<a
			href="href"
			title="title"
			target="target"
			data-e2e-name="name"
			class="className">
			{{text}}
		</a>
	`

}







