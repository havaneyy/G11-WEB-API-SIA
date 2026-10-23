describe('Security', () => {
  it('E2E-08 keeps the secret key out of the browser', () => {
    // Spy on any potential client-side browser calls to PayMongo's API
    cy.intercept('https://api.paymongo.com/**', cy.spy().as('browserToPayMongo'));

    // 1. Visit storefront
    cy.visit('/');
    cy.get('@browserToPayMongo').should('not.have.been.called');
    cy.get('html').invoke('html').should('not.include', 'sk_test_').and('not.include', 'sk_live_');

    // 2. Visit cart page
    cy.visit('/cart.php');
    cy.get('@browserToPayMongo').should('not.have.been.called');
    cy.get('html').invoke('html').should('not.include', 'sk_test_').and('not.include', 'sk_live_');
  });

  it('blocks unauthenticated users from adding crafts', () => {
    // 1. Visiting storefront and clicking Add Craft without being logged in
    cy.visit('/');
    cy.get('#nav-btn-create').click();

    // 2. Verify auth modal pops up and Create tab stays hidden
    cy.get('#authModal').should('not.have.class', 'hidden');
    cy.get('#loginStatus').should('be.visible').and('contain.text', 'Please sign in or register to add and publish crafts');
    cy.get('#tab-create').should('have.class', 'hidden');

    // 3. Verify direct API request to create_product is rejected with 401
    cy.request({
      method: 'POST',
      url: 'api.php?action=create_product',
      body: {
        title: 'Hacker Craft',
        price: '999.00',
        category: 'Tech'
      },
      failOnStatusCode: false
    }).then((res) => {
      expect(res.status).to.eq(401);
      expect(res.body.error).to.include('Authentication required');
    });
  });
});

