class Catalog{

	getLandingPageUrl() {
		return Cypress.config('baseUrl')
	}

	siteTitle(){
		return cy.get('h1.site-title');
	}

	goTo() {
		cy.visit(this.getLandingPageUrl())
		this.siteTitle().should('be.visible')
	}



}

export default Catalog
