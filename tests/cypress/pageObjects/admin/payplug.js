import loader from "../../support/loader";

class payplug {

	pageRoute = 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=payplug';

	username() {
		return cy.get('#payplug_email');
	}

	oney(){
		return cy.get("#woocommerce_payplug_oney");
	}

	oney_with_fees(){
		return cy.get('#woocommerce_payplug_oney_type-with_fees');
	}

	password() {
		return cy.get('#payplug_password')
	}

	submitButton() {
		return cy.get('#payplug-login')
	}

	savechangesButton(){
		return cy.get("[type=submit].woocommerce-save-button")
	}

	logoutButton(){
		return cy.get("#payplug-logout");
	}

	payplugRequirements(){
		return cy.get(".payplug-requirements");
	}

	getPayplugUrl() {
		return Cypress.config('baseUrl') + this.pageRoute
	}

	//actions
	payplugActivateOney(){
		return this.oney().check();
	}

	payplugActivateOneyWithoutFees(){
		return cy.get('#woocommerce_payplug_oney_type-without_fees').check('without_fees');
	}

	payplugActivateOneyWithFees(){
		return this.oney_with_fees().check('with_fees');
	}

	goTo() {
		return cy.visit(this.getPayplugUrl())
	}

	logout(){
		this.logoutButton().click();
	}

	fillUsername(value) {
		const field = this.username();
		field.wait(500).clear();
		field.type(value);
	}

	fillPassword(value) {
		const field = this.password()
		field.wait(500).clear()
		field.type(value)
	}

	signIn(overridePassword, config) {
		cy.url().then(url => {
			if(url !== this.getPayplugUrl(config)) {
				this.goTo(config);
			}
		});

		this.fillUsername(Cypress.env('auth').payplug.username);
		this.fillPassword(overridePassword ? overridePassword : Cypress.env('auth').payplug.password);
		this.submitButton().click();
	}

	//assertions
	assertPayplugPluginLocation(){
		this.payplugRequirements().should('be.visible')
	}

	assertOneyIsChecked(){
		this.oney().should('be.checked');
	}

	assertOneyWithFeesIsChecked(){
		this.oney_with_fees().should('be.checked');
	}

}

export default payplug
