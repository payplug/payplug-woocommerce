/// <reference types="cypress" />
import loader from '../../../support/loader'

describe('WC Admin login spec', () => {

    loader.loadPO('admin/login')
	loader.loadPO('admin/adminMenu')
	loader.loadPO('admin/adminBar')
	loader.loadData('lang')

	it('Test admin login fail', function() {
		var password = Cypress.env('auth').admin.password + 'fail';
		loader.po.admin.login.signIn(password)

		loader.po.admin.login.assertWrongLogin(loader.data.lang.admin.login.failMessage);
		loader.po.admin.login.assertLoginPageLocation();
	})


    it('Test admin login success', function() {
		cy.wait(1000)
        loader.po.admin.login.signIn();
		loader.po.admin.adminMenu.assertWpAdminMenu(loader.data.lang.admin.menu.title);
		loader.po.admin.adminBar.assertWpAdminBar();
    })

    it('Test admin logout', function() {
        loader.po.admin.login.signIn();
        loader.po.admin.adminBar.logout();
        loader.po.admin.login.assertUsernameVisibility();
    })

})
