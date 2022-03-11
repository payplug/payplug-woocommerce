class LandingPage{

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

	goToCatalog(label){
		cy.get('ul.nav-menu .page_item').each(($el, index, $list) => {
			if($el.text().indexOf(label) > -1){
				cy.wrap($el).click().then(($elem) => {

					cy.get(".products > .product").should("be.greaterThan",0);

				});
			}
		})
	}

}

export default LandingPage
