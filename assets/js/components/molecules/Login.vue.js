import BlockTitle from "../atoms/block/BlockTitle.vue.js"
import BlockDescription from "../atoms/block/BlockDescription.vue.js"
import Input from "../atoms/common/Input.vue.js"
import Button from "../atoms/common/Button.vue.js"
import Link from "../atoms/common/Link.vue.js"
import ButtonLink from "../atoms/common/ButtonLink.vue.js"

const BannerDescription = {
	components: {BlockTitle, BlockDescription, Input, Button, Link, ButtonLink},
	data: function () {
		return {
			hideLogin: false
		}
	},
	template: `
	<section class="payplugUiBlock generalBlock -login">
		<BlockTitle text="Login/Subscription"/>
		<BlockDescription text="Log in to your PayPlug account."/>
		<div class="_content" v-if="!hideLogin">
			<form action="#" class="_loginForm">
				<div>
					<Input className="_email" id="userEmail" label="E-mail address" type="text" name="userEmail" placeholder="E-mail address" />
				</div>
				<div>
					<Input className="_password" id="userPassword" label="Password" type="password" name="userPassword" placeholder="Password" />
				</div>
				<div>
					<Button className="_connexion" type="button" name="login" text="Connect account"/>
					<Button @click.native="hideLogin = !hideLogin" className="_subscribe -tertiary" type="button" name="hideLogin" text="Not registered to PayPlug yet?"/>
				</div>
				<div>
					<Link className="_forgotPassword" href="https://www.payplug.com/portal/forgot_password" target="_blank" title="Forgot your password?" text="Forgot your password?"/>
				</div>
			</form>
		</div>
		<div class="_content -subscribe -center" v-if="hideLogin">
        	<p>Create your account to use the module.</p>
			<div class="_buttons">
				<ButtonLink className="" href="https://portal.payplug.com" title="Create a PayPlug account" target="_blank" text="Create a PayPlug account"/>
				<Button @click.native="hideLogin = !hideLogin" className="-tertiary" type="button" name="showLogin" text="I already have a PayPlug account"/>
			</div>
		</div>
	</section>
	`,
	mounted () {

	}
}

export default BannerDescription
