export default {

	props: {
		content: {
			type: String,
			required: true,
		},
	},
	template: `
		<h2 :style="[titleStyle,titleStyleAfter]">
			{{ content }}
		</h2>
	`,
	data(){
		return{
			titleStyle:{
				color: "#363a41",
				fontFamily: "Inter sans-serif",
				fontSize: "20px",
				fontWeight: "700",
				letterSpacing: "-.0165em",
				lineHeight: "1.45em",
				margin: "0 0 21px",
				position: "relative"
			}
		}
	}

}
