export default {

	props: {
		text: {
			type: String,
			required: false,
		},
		fieldname: {
			type: String,
			required: false,
		}
	},
	template: `
		<label
			:style="labelStyle"
			type="text"
			:for="fieldname">
			{{text}}
		</label>
	`,
	data(){
		return{
			labelStyle:{
				color: "#363a41",
				fontWeight: "700",
				margin: "0 0 4px"
			}
		}
	}

}







