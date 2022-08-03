export default {
	props: {
		content: {
			type: String,
			required: true,
		},
	},
	template: `
		<p :style="paragraphStyle">
			{{ content }}
		</p>
	`,
	data(){
		return{
			paragraphStyle:{
				color: 'red',
			}

		}
	}

}
