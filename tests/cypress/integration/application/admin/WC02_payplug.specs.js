/// <reference types="cypress" />
import loader from '../../../support/loader'

describe('Extension Page spec', () => {
	loader.loadPO('admin/login');
	loader.loadPO('admin/extensions');
	loader.loadData('lang');

	it('Payplug deactivate - activate', function() {
		loader.po.admin.login.signIn();
		loader.po.admin.extensions.goTo().then( (elem) => {
			cy.wait(1000);
			loader.po.admin.extensions.assertExtensionsPageLocation();

			if( Cypress.$("[data-slug=payplug] .row-actions").text().indexOf(loader.data.lang.admin.extension.settings) > -1 ){
				loader.po.admin.extensions.assertPayplugStatus(loader.data.lang.admin.extension.settings);

				loader.po.admin.extensions.deactivatePayplug().then( (elem) => {
					loader.po.admin.extensions.assertPayplugStatus(loader.data.lang.admin.extension.activate);

					loader.po.admin.extensions.activatePayplug().then( (elem) => {
						loader.po.admin.extensions.assertPayplugStatus(loader.data.lang.admin.extension.settings);

					});
				});
			}else{
				loader.po.admin.extensions.activatePayplug().then( (elem) => {
					loader.po.admin.extensions.assertPayplugStatus(loader.data.lang.admin.extension.settings);

				});
			}

		});
	});


	it('Install payplug', function() {
		//TODO:: write here the code for it

	});

})
