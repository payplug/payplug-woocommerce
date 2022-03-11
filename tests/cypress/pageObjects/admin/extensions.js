import loader from "../../support/loader";

class extensions {

	pageRoute = 'wp-admin/plugins.php';

	getPluginsTable(){
		return cy.get(".wp-list-table.plugins");
	}

	getPayplugConfigurations(){
		return cy.get("[data-slug=payplug]");
	}

	//actions
	getPageUrl() {
		return Cypress.config('baseUrl') + this.pageRoute
	}

	goTo() {
		return cy.visit(this.getPageUrl())
	}

	activatePayplug(){
		return cy.get("[data-slug=payplug] .row-actions .activate a").click();
	}

	deactivatePayplug(){
		return cy.get("[data-slug=payplug] .row-actions .deactivate a").click();
	}

	//assertions
	assertPayplugStatus(status){
		cy.get("[data-slug=payplug] .row-actions").should('include.text', status);
	}

	assertExtensionsPageLocation(){
		this.getPluginsTable().should('be.visible');
	}


}

export default extensions
