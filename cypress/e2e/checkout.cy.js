describe('Checkout', () => {
  it('E2E-03 gets a checkout URL from PayMongo', () => {
    cy.intercept('POST', '**/checkout*').as('checkout');
    cy.intercept('https://api.paymongo.com/**', cy.spy().as('browserToPayMongo'));

    cy.visit('/', {
      onBeforeLoad(win) {
        win.__cypress_stop_redirect = true;
      }
    });
    cy.contains('[data-cy=product-card]', 'SIA1 Class T-Shirt')
      .find('[data-cy=add-to-cart]').click();
    cy.get('[data-cy=checkout-button]').first().click();

    cy.wait('@checkout').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.body.checkout_url).to.match(/^https:\/\/checkout\.paymongo\.com\//);
    });
    cy.get('@browserToPayMongo').should('not.have.been.called');
  });

  it('E2E-04 hands the customer over to PayMongo', () => {
    cy.intercept('POST', '**/checkout*').as('checkout');

    cy.visit('/');
    cy.contains('[data-cy=product-card]', 'SIA1 Class T-Shirt')
      .find('[data-cy=add-to-cart]').click();
    cy.get('[data-cy=checkout-button]').first().click();

    cy.wait('@checkout');

    // Confirm browser lands on PayMongo's hosted checkout without interacting with it
    cy.origin('https://checkout.paymongo.com', () => {
      Cypress.on('uncaught:exception', () => false);
      cy.location('hostname', { timeout: 20000 }).should('eq', 'checkout.paymongo.com');
    });
  });

  it('E2E-07 shows a clear error when checkout fails', () => {
    cy.intercept('POST', '**/checkout*', {
      statusCode: 500,
      body: { error: 'Payment gateway unavailable' }
    }).as('checkout');

    cy.visit('/');
    cy.get('[data-cy=add-to-cart]').first().click();
    cy.get('[data-cy=checkout-button]').first().click();
    cy.wait('@checkout');
    cy.get('[data-cy=checkout-error]').should('be.visible');
    cy.location('pathname').should('not.include', 'success');
  });
});
