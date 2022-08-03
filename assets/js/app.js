import store from './store.js'
import AppHeader from "./components/AppHeader.js"
import AppLogin from "./components/AppLogin.js"
import AppNewAccount from "./components/AppNewAccount.js";

//place the components into components management file
export default {
	name: 'App',
	components: {
		AppLogin,
		AppHeader,
		AppNewAccount
	},
	setup(){
		const {onMounted} = Vue;
		//store management: save $variables to localstorage
		onMounted(() => {
			window.addEventListener('beforeunload', () => {
				Object.keys(store).forEach(function (key){
					if (key.charAt(0) == "$") {localStorage.setItem(key, store[key]); } else {localStorage.removeItem("$" + key);}
				});
			});

			Object.keys(store).forEach(function (key){
				//starts with $
				if (key.charAt(0) == "$") {
					if (localStorage.getItem(key)) store[key] = localStorage.getItem(key);
				}}
			)

		})
	},
	template: `
			<div class="payplugConfiguration">
			  <div class="payplug">
				<AppHeader />
				<AppLogin v-bind:appLogin="appLogin"/>
				<AppNewAccount />
			  </div>
			</div>
	`,
	data(){
		return {
			appLogin:{
				title:"General",
				title_description:"Log in to your PayPlug account.",
				form:{
					action:"#",
					class: "_loginForm",
					formFields: [
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
					formButtons: [
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

				}
			},
			appCreateAccount:{
				title:"General",
				title_description:"Registration",
				description:"Create your account to be able to use the module.",
				buttons: {
					buttonName: "showLogin",
					buttonClasses: "payplugUIButton -tertiary",
					buttonText: "I already have a PayPlug account"
				},
				anchors: {
					href: "https://portal.payplug.com",
					title: "PayPlug Registration",
					target: "_blank",
					name: "payplugRegistration",
					className:"payplugUIButtonLink",
					text: "PayPlug Registration"
				}
			}
		}
	}
}
