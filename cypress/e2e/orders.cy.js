describe('Orders', () => {
  it('E2E-02 creates a pending order with the right total', () => {
    // Verified through Order Service API, not just the page
    cy.request('POST', 'orders.php', {
      items: [{ productId: 1, quantity: 1 }]
    }).then((res) => {
      expect(res.status).to.eq(201);
      expect(res.body.status).to.eq('pending');
      expect(res.body.total).to.eq(35000);
      const orderId = res.body.id;

      cy.request(`orders.php?id=${orderId}`).then((orderRes) => {
        expect(orderRes.status).to.eq(200);
        expect(orderRes.body.status).to.eq('pending');
        expect(orderRes.body.total).to.eq(35000);
      });
    });
  });

  it('E2E-05 updates the order on the success page', () => {
    cy.request('POST', 'orders.php', {
      items: [{ productId: 1, quantity: 1 }]
    })
      .its('body.id')
      .then((id) => {
        cy.visit(`/success.php?order_id=${id}`);
        cy.get('[data-cy=order-status]').should('have.text', 'paid');
        cy.request(`orders.php?id=${id}`).its('body.status').should('eq', 'paid');
      });
  });

  it('E2E-06 never marks a cancelled order as paid', () => {
    cy.request('POST', 'orders.php', {
      items: [{ productId: 1, quantity: 1 }]
    })
      .its('body.id')
      .then((id) => {
        cy.visit(`/cancel.php?order_id=${id}`);
        cy.get('[data-cy=order-status]').should('not.have.text', 'paid');
        cy.request(`orders.php?id=${id}`).its('body.status').should('not.eq', 'paid');
      });
  });
});
