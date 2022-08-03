import paragraph from "../templates/atoms/paragraph.js";
import sectionTitle from "../templates/atoms/title.js";
import sectionForm from "../templates/molecules/form.js";
import formButton from "../templates/atoms/button.js";
import formAnchor from "../templates/atoms/anchor.js";


export default {
	name: 'New Account',
	setup() {
	},
	components: {
		paragraph, sectionTitle, sectionForm, formButton, formAnchor
	},
	template: `
		  <section class="payplugUiBlock generalBlock -subscribe" data-e2e-name="generalSubscribe">
		  	<sectionTitle :content="data.title" />
			<paragraph :content="data.title_desc" />

			<div class="_content">
				<paragraph :content="data.form.description" style="font-size: 14px;margin: 39px 0;text-align: center;" />
				<div class="_buttons">
					<formAnchor
						:href="data.payplug_redirect.href"
						:title="data.payplug_redirect.title"
						:target="data.payplug_redirect.target"
						:data-e2e-name="data.payplug_redirect.name"
						:class="data.payplug_redirect.className"
						:text="data.payplug_redirect.text"
						/>
					<formButton
						:buttonName="data.buttons.buttonName"
						:buttonClasses="data.buttons.buttonClasses"
						:buttonText="data.buttons.buttonText" />
				</div>
			</div>
		  </section>
	`,
	data(){
		return {
			data: {
				title: "General",
				title_desc : "Registration",
				form: {
					description: "Create your account to be able to use the module."
				},
				buttons:
				{
					buttonName: "showLogin",
					buttonClasses: "payplugUIButton -tertiary",
					buttonText: "I already have a PayPlug account"
				},
				payplug_redirect:
				{
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
};
