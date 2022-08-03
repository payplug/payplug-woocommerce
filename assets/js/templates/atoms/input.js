export default {

	props: {
		fieldname: {
			type: String,
			required: true,
		},
		placeholder: {
			type: String,
			required: true,
		},
		inputType: {
			type: String,
			required: true,
		}
	},
	template: `
		<input
			:style="inputStyle"
			:type="inputType"
			:id="fieldname"
			:name="fieldname"
			:data-e2e-name="'payplug' + fieldname"
			:placeholder="placeholder" />

	`,
	data(){
		return{
			inputStyle:{
				backgroundColor: "inherit",
				border: "1px solid #c8d7e4",
				borderRadius: "4px",
				cursor: "pointer",
				height: "36px",
				padding: "8px 12px",
				width: "100%"
			}

		}
	}

}







