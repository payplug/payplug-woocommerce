import BannerLogo from "../atoms/banner/BannerLogo.vue.js"
import BannerVersion from "../atoms/banner/BannerVersion.vue.js"
import BannerTitle from "../atoms/banner/BannerTitle.vue.js"
import BannerDescription from "../atoms/banner/BannerDescription.vue.js"

const Banner = {
	components: {BannerLogo, BannerVersion, BannerTitle, BannerDescription},
	data: function () {
		return {
			logo: vars.base_url + "images/logo-payplug.svg"
		}
	},
	template: `
	<section class="payplugUiBlock descriptionBlock">
    	<div class="_content">
            <div class="_header">
            	<BannerLogo :logo="logo" />
            	<BannerVersion/>
    		</div>
    		<BannerTitle/>
    		<BannerDescription/>
    	</div>
	</section>
	`
}

export default Banner
