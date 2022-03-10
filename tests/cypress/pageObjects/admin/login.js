import loader from "../../support/loader";

class Login{

    loginPageRoute = 'wp-login'

	adminPageRoute = 'wp-admin';

    username() {
        return cy.get('#user_login')
    }

    password() {
        return cy.get('#user_pass')
    }

	submitButton() {
		return cy.get('#wp-submit')
	}

	wrongLogin(){
		return cy.get("#login_error");
	}

	//actions
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
            if(url !== this.getLoginUrl(config)) {
                this.goTo(config);
            }
        })

        this.fillUsername(Cypress.env('auth').admin.username);
		this.fillPassword(overridePassword ? overridePassword : Cypress.env('auth').admin.password)
        this.submitButton().click()
    }

    goTo() {
        cy.visit(this.getLoginUrl())
        this.assertUsernameVisibility();
    }

    getLoginUrl() {
        return Cypress.config('baseUrl') + this.adminPageRoute
    }

	//assertions
	assertWrongLogin(message){
		this.wrongLogin().contains(message)
	}

	assertLoginPageLocation(){
		cy.url().should('eq', Cypress.config('baseUrl') + this.loginPageRoute + '.php')
	}

	assertUsernameVisibility(){
		this.username().should('be.visible')
	}

}

export default Login
