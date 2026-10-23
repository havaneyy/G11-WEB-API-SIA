describe('Catalog', () => {
  it('E2E-01 shows the catalog with correct peso prices', () => {
    cy.visit('/');
    cy.get('[data-cy=product-card]').should('have.length.at.least', 3);
    
    // Centavos correctly formatted in Pesos (35000 -> ₱350.00)
    cy.contains('[data-cy=product-card]', 'SIA1 Class T-Shirt')
      .find('[data-cy=product-price]')
      .should('contain.text', '₱350.00');

    cy.contains('[data-cy=product-card]', 'SIA1 Tote Bag')
      .find('[data-cy=product-price]')
      .should('contain.text', '₱150.00');

    cy.contains('[data-cy=product-card]', 'SIA1 Mug')
      .find('[data-cy=product-price]')
      .should('contain.text', '₱120.00');
  });

  it('E2E-09 blocks checkout with an empty cart', () => {
    cy.visit('/');
    cy.get('[data-cy=checkout-button]').first().click();
    cy.get('[data-cy=checkout-error]').should('be.visible');
    cy.location('pathname').should('not.include', 'success');
  });
});
