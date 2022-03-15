class AdminMenu{

	wpAdminMenu(){
		return cy.get("#wpadminbar");
	}

	wpAdminMenuName(){
		return cy.get(".wp-menu-name");
	}

	//assertions
	assertWpAdminMenu(label){
		this.wpAdminMenuName().should('include.text', label);
	}

	//actions
    openMenu() {
        this.columnLeft().then((element) => {
            if(!element.hasClass('active')){
                this.buttonMenu().click()
            }
        })
    }

}

export default AdminMenu
