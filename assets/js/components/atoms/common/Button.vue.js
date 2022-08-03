const Button = {
	props: ['className', 'type', 'name', 'text'],
	template: `
		<button :type="type" :name="name" class="payplugUIButton" :class="className">{{text}}</button>
	`
}

export default Button
