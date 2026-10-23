// Cypress support file
Cypress.on('uncaught:exception', (err, runnable) => {
  // Prevent external script or CDN warnings from failing test suites
  return false;
});
