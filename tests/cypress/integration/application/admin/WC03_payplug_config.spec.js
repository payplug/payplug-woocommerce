/// <reference types="cypress" />
import loader from '../../../support/loader'

describe('Payplug Configuration Page spec', () => {
	loader.loadPO('admin/login');
	loader.loadPO('admin/payplug');
	loader.loadData('lang');

	it( 'Payplug Login', function(){
		loader.po.admin.login.signIn();

		loader.po.admin.payplug.goTo().then( (elem) => {
			if(Cypress.$("#payplug-logout").length){
				loader.po.admin.payplug.assertPayplugPluginLocation();
				loader.po.admin.payplug.logout();
			}
		});

		loader.po.admin.payplug.signIn();
	});

	it( 'Payplug activate Oney', function(){
		//enable oney with fees
		loader.po.admin.login.signIn();

		loader.po.admin.payplug.goTo().then( (elem) => {
			if(Cypress.$("#payplug-logout").length){
				loader.po.admin.payplug.assertPayplugPluginLocation();
				loader.po.admin.payplug.logout();
			}
		});

		loader.po.admin.payplug.signIn();
		loader.po.admin.payplug.payplugActivateOney();
		loader.po.admin.payplug.payplugActivateOneyWithFees();

		loader.po.admin.payplug.savechangesButton().click().then( (elem) => {
			loader.po.admin.payplug.assertOneyIsChecked();
			loader.po.admin.payplug.assertOneyWithFeesIsChecked();
		});

	});


});
