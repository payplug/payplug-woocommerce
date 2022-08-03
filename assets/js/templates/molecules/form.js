import inputField from "../../templates/atoms/input.js";
import labelField from "../../templates/atoms/label.js";
import formButton from "../../templates/atoms/button.js";

export default {
	components: {
		inputField, labelField, formButton
	},
	template: `
		<form
			:action="data.action"
			:class="data.class"
			style="width:100%">

			<div v-for="(form, index) in data.form" style="width:100%; display:block">

				<div  class="payplugUIInput_email">
					<labelField
						:text="form.text"
						:fieldname="form.fieldname" />

					<inputField
						:fieldname="form.fieldname"
						:placeholder="form.placeholder"
						:inputType="form.inputType" />
				</div>
			</div>

			<div v-for="(button, index) in data.buttons">
				<formButton
					:buttonName="button.buttonName"
					:buttonClasses="button.buttonClasses"
					:buttonText="button.buttonText" />
            </div>
		</form>
	`,

	data(){
		return{
			data: {
				action: "#",
				class: "_loginForm",
				form: [
					{
						text: "E-mail addressssss",
						fieldname: "userEmail",
						placeholder: "E-mail address",
						inputType: "text"
					},
					{
						text: "test",
						fieldname: "test",
						placeholder: "test",
						inputType: "text"
					},
					{
						text: "Password",
						fieldname: "userPassword",
						placeholder: "Password",
						inputType: "password"
					}
				],
				buttons: [
					{
						buttonName: "login",
						buttonClasses: "payplugUIButton _connexion",
						buttonText: "Login"
					},
					{
						buttonName: "hideLogin",
						buttonClasses: "payplugUIButton _subscribe -tertiary",
						buttonText: "No PayPlug account yet?"
					}
				]
			},
			paragraphStyle:{
				color: 'red',
			}

		}
	}

}
