class adminBar{

	wpAdminBar(){
		return cy.get("#wpadminbar");
	}

	wpAdminBarMyAccount(){
		return cy.get("#wp-admin-bar-my-account");
	}

	wpAdminBarLogout(){
		return cy.get("#wp-admin-bar-logout a");
	}

	logout(){
		this.wpAdminBarMyAccount().invoke('addClass', 'hover');
		this.assertWpAdminBarLogout();
		this.wpAdminBarLogout().click();
	}

	//assertions
	assertWpAdminBar(){
		this.wpAdminBar().should('be.visible');
	}

	assertWpAdminBarLogout(){
		this.wpAdminBarLogout().should('be.visible');
	}

}

export default adminBar

