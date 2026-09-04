import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const adminUser = process.env.WLA_E2E_ADMIN_USER;
const adminPass = process.env.WLA_E2E_ADMIN_PASS;
const editorUser = process.env.WLA_E2E_EDITOR_USER;
const editorPass = process.env.WLA_E2E_EDITOR_PASS;

async function login(page, username, password) {
  if (!username || !password) {
    throw new Error('Missing E2E credentials supplied by the CI environment.');
  }

  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await expect(page).toHaveURL(/\/wp-admin\//, { timeout: 30_000 });
  await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 30_000 });
}

async function assertNoSeriousOwnUiViolations(page, include) {
  const results = await new AxeBuilder({ page })
    .include(include)
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
    .analyze();

  const blocking = results.violations.filter((violation) =>
    ['serious', 'critical'].includes(violation.impact || ''),
  );

  expect(
    blocking,
    `Accessibility violations in ${include}: ${JSON.stringify(blocking, null, 2)}`,
  ).toEqual([]);
}

async function assertNoOwnHorizontalOverflow(page, selector, context) {
  const element = page.locator(selector).first();
  await expect(element, `${context} should remain visible`).toBeVisible();

  const overflow = await element.evaluate((node) => ({
    clientWidth: node.clientWidth,
    scrollWidth: node.scrollWidth,
  }));

  expect(
    overflow.scrollWidth,
    `${context} own UI should not overflow horizontally: ${JSON.stringify(overflow)}`,
  ).toBeLessThanOrEqual(overflow.clientWidth + 2);
}

async function saveClassicPost(page, buttonId) {
  await Promise.all([
    page.waitForURL(/\/wp-admin\/post\.php\?post=\d+&action=edit/),
    page.locator(buttonId).click(),
  ]);
}

async function openEditorSection(page, title) {
  const section = page.locator('details.wla-inmo-property-editor__section').filter({ hasText: title });
  await expect(section).toBeVisible();

  if (!(await section.evaluate((element) => element.open))) {
    await section.locator('summary').click();
  }

  await expect(section).toHaveJSProperty('open', true);
}

test.describe.serial('WLA Inmo administration quality gate', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, adminUser, adminPass);
  });

  test('administrator can use dashboard, create a property and audit changes', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wla-inmo');
    await expect(page.getByRole('heading', { name: 'Resumen' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Necesita atención' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Estado del catálogo' })).toBeVisible();
    await assertNoSeriousOwnUiViolations(page, '.wla-inmo-admin');

    await page.goto('/wp-admin/post-new.php?post_type=wla_property');
    await expect(page.locator('#wla-inmo-property-editor')).toBeVisible();
    await page.locator('#title').fill('Propiedad E2E Quality Gate');
    await page.locator('#wla-inmo-field-property_code').fill('E2E-QG-001');
    await page.locator('#wla-inmo-field-status').selectOption('available');
    await page.locator('#wla-inmo-field-currency_primary').selectOption('CLP');
    await page.locator('#wla-inmo-field-price_clp').fill('125000000');
    await page.locator('#wla-inmo-taxonomy-wla_operation').selectOption({ label: 'Venta' });
    await page.locator('#wla-inmo-taxonomy-wla_property_type').selectOption({ label: 'Casa' });

    await openEditorSection(page, '6. Ubicación');
    await page.locator('#wla-inmo-taxonomy-wla_commune').selectOption({ label: 'Curicó' });
    await assertNoSeriousOwnUiViolations(page, '#wla-inmo-property-editor');

    await saveClassicPost(page, '#save-post');
    await expect(page.locator('#message')).toContainText(/Draft updated|Borrador actualizado/i);

    await saveClassicPost(page, '#publish');
    await expect(page.locator('#message')).toContainText(/Post published|publicada|published/i);

    await page.locator('#wla-inmo-field-price_clp').fill('130000000');
    await page.locator('#wla-inmo-field-status').selectOption('reserved');
    await saveClassicPost(page, '#publish');
    await expect(page.locator('#wla-inmo-field-price_clp')).toHaveValue('130000000');
    await expect(page.locator('#wla-inmo-field-status')).toHaveValue('reserved');

    await page.goto('/wp-admin/admin.php?page=wla-inmo-quality');
    await expect(page.getByRole('heading', { name: 'Calidad del catálogo' })).toBeVisible();
    await expect(page.locator('.wla-inmo-admin')).toContainText('Propiedad E2E Quality Gate');
    await assertNoSeriousOwnUiViolations(page, '.wla-inmo-admin');

    await page.goto('/wp-admin/admin.php?page=wla-inmo-activity');
    await expect(page.getByRole('heading', { name: 'Actividad' })).toBeVisible();
    await expect(page.locator('.wla-inmo-admin')).toContainText(/Precio|Estado comercial|Propiedad creada/);
    await assertNoSeriousOwnUiViolations(page, '.wla-inmo-admin');

    await page.goto('/wp-admin/admin.php?page=wla-inmo-help');
    await expect(page.getByRole('heading', { name: 'Ayuda' })).toBeVisible();
    await expect(page.locator('.wla-inmo-admin')).toContainText(/Primeros pasos|Crear una propiedad/);
    await assertNoSeriousOwnUiViolations(page, '.wla-inmo-admin');
  });

  test('restricted editor cannot access settings directly', async ({ page }) => {
    await page.context().clearCookies();
    await login(page, editorUser, editorPass);
    const response = await page.goto('/wp-admin/admin.php?page=wla-inmo-settings');
    expect(response).not.toBeNull();
    expect(response.status()).toBeGreaterThanOrEqual(400);
    await expect(page.locator('body')).toContainText(/not allowed|no tienes permisos|no tienes autorización|sorry/i);
  });

  test('@responsive administration remains usable across priority screens', async ({ page }) => {
    const ownScreens = [
      ['/wp-admin/admin.php?page=wla-inmo', '.wla-inmo-admin', 'Resumen'],
      ['/wp-admin/admin.php?page=wla-inmo-quality', '.wla-inmo-admin', 'Calidad'],
      ['/wp-admin/admin.php?page=wla-inmo-activity', '.wla-inmo-admin', 'Actividad'],
      ['/wp-admin/admin.php?page=wla-inmo-help', '.wla-inmo-admin', 'Ayuda'],
      ['/wp-admin/admin.php?page=wla-inmo-settings', '.wla-inmo-admin', 'Ajustes'],
    ];

    for (const [url, selector, label] of ownScreens) {
      await page.goto(url);
      await assertNoOwnHorizontalOverflow(page, selector, label);
    }

    await page.goto('/wp-admin/post-new.php?post_type=wla_property');
    await assertNoOwnHorizontalOverflow(page, '#wla-inmo-property-editor', 'Editor de propiedad');
    await assertNoOwnHorizontalOverflow(page, '.wla-inmo-property-media', 'Multimedia');

    await page.goto('/wp-admin/edit.php?post_type=wla_property');
    await expect(page.locator('#posts-filter .wp-list-table')).toBeVisible();
    await expect(page.locator('#wla_code')).toContainText(/Código/i);
    await expect(page.locator('#wla_price')).toContainText(/Precio/i);

    await page.goto('/wp-admin/admin.php?page=wla-inmo');
    await expect(page.getByRole('heading', { name: 'Necesita atención' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Accesos rápidos' })).toBeVisible();
  });
});
