import BlockTitle from "../atoms/block/BlockTitle.vue.js"
import BlockDescription from "../atoms/block/BlockDescription.vue.js"
import Input from "../atoms/common/Input.vue.js"
import Button from "../atoms/common/Button.vue.js"
import Link from "../atoms/common/Link.vue.js"
import ButtonLink from "../atoms/common/ButtonLink.vue.js"

const General = {
	components: {BlockTitle, BlockDescription, Input, Button, Link, ButtonLink},
	data: function () {
		return {
			title: vars.options.title,
			description: vars.options.description,
		}
	},
	template: `
	<section class="payplugUiBlock generalBlock -login">
		<BlockTitle text="General"/>
		<BlockDescription text="General Payplug configurations"/>
		<div class="_content">
			<form action="#" class="_loginForm" @submit="onSubmit">
				<div>
					<div class="payplugUIInput _email">
						<label :for="title">Title</label>
						<input v-model="title" type="text" id="title" name="title" class="" placeholder="">
					</div>
				</div>
				<div>
					<div class="payplugUIInput _email">
						<label :for="title">Description</label>
						<input v-model="description" type="text" id="description" name="description" class="" placeholder="">
					</div>
				</div>
				<div>
					<Button className="_connexion" type="submit" name="login" text="Save Changes"/>
				</div>
			</form>
		</div>
	</section>
	`,
	methods : {
		onSubmit(e){
			e.preventDefault()
			jQuery('._connexion').attr("disabled", true)
			if(!this.title || !this.description){
				return
			}
			jQuery.post(
				vars.ajax_url,
				{
					'action': 'payplug_update_settings',
					title: this.title,
					description: this.description,
				},
				function(response) {
					jQuery('._connexion').attr("disabled", false)
					alert("Data Saved Successfully !")
				}
			);
		}
	}
}

export default General
