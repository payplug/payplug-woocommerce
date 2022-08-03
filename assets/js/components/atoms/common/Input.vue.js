const Input = {
	props: ['className', 'id', 'label', 'type', 'name', 'placeholder', 'value'],
	template: `
		<div class="payplugUIInput" :class="className">
			<label :for="id">{{label}}</label>
			<input :value="value" :type="type" :id="id" :name="name" :placeholder="placeholder">
		</div>
	`
}

export default Input
