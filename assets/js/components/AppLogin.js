import paragraph from "../templates/atoms/paragraph.js";
import sectionTitle from "../templates/atoms/title.js";
import sectionForm from "../templates/molecules/form.js";

export default {
	name: 'Login',
	props:[
		'appLogin'
	],
	setup(props) {

		console.log(props.appLogin);

		const data = {
			title: "General",
			title_desc : "Log in to your PayPlug account."
		}

		let newAccount = true;

		function showElement() {
			store[newAccount] = true;
		}

		return {data, newAccount, showElement};

	},
	components: {
		paragraph, sectionTitle, sectionForm
	},
	template: `
		  <section class="payplugUiBlock -login" data-e2e-name="generalLogin">
		  	<sectionTitle :content="data.title" />
			<paragraph :content="data.title_desc" />
			<sectionForm />
		  </section>
`,
};
