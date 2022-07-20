export const General = {
	props: ['title', 'description'],
	template: `
	<div class="section">
		<h1 class="title">General</h1>
		<p class="description">Enable PayPlug for your customers.</p>
		<form @submit="onSubmit" >
			<div class="form-group">
				<label for="title">Title</label>
				<input type="text" v-model="title" name="title" required>
				<p class="description">The payment solution title displayed to your customers during checkout</p>
			</div>
			<div class="form-group">
				<label for="description">Description</label>
				<input type="text" v-model="description" name="description" required>
				<p class="description">The payment solution description displayed to your customers during checkout</p>
			</div>
			<div class="clearfix">
				<button type="submit">Save changes</button>
			</div>
		</form>
	</div>
	`,
	methods : {
		onSubmit(e){
			e.preventDefault()
			if(!this.title || !this.description){
				return
			}
			jQuery('#payplug #loading').show()
			jQuery.post(
				vars.ajax_url,
				{
					'action': 'payplug_update_settings',
					title: this.title,
					description: this.description,
				},
				function(response) {
					jQuery('#payplug #loading').hide()
					jQuery('#payplug #success-message').show()
				}
			);
		}
	}, mounted: function () {
		//alert(vars.translations['Title'])
	}
}
