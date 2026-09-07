import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const adminUser = process.env.WLA_E2E_ADMIN_USER;
const adminPass = process.env.WLA_E2E_ADMIN_PASS;

async function submitLogin(page, username, password) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
}

async function login(page, username, password) {
  if (!username || !password) {
    throw new Error('Missing E2E credentials supplied by the CI environment.');
  }

  await submitLogin(page, username, password);
  try {
    await expect(page).toHaveURL(/\/wp-admin\//, { timeout: 10_000 });
  } catch (firstAttemptError) {
    if (!/\/wp-login\.php/.test(page.url())) {
      throw firstAttemptError;
    }

    const loginError = page.locator('#login_error');
    if (await loginError.isVisible().catch(() => false)) {
      throw new Error(`WordPress rejected E2E credentials: ${(await loginError.innerText()).trim()}`);
    }

    await submitLogin(page, username, password);
    await expect(page).toHaveURL(/\/wp-admin\//, { timeout: 30_000 });
  }

  await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 30_000 });
}

async function assertNoSeriousOwnUiViolations(page) {
  const results = await new AxeBuilder({ page })
    .include('.wla-inmo-admin')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
    .analyze();

  const blocking = results.violations.filter((violation) =>
    ['serious', 'critical'].includes(violation.impact || ''),
  );
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
}

async function assertNoOwnHorizontalOverflow(page, selector) {
  const element = page.locator(selector).first();
  await expect(element).toBeVisible();
  const size = await element.evaluate((node) => ({
    clientWidth: node.clientWidth,
    scrollWidth: node.scrollWidth,
  }));
  expect(size.scrollWidth).toBeLessThanOrEqual(size.clientWidth + 2);
}

test.describe.serial('WLA Inmo CSV import wizard', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, adminUser, adminPass);
  });

  test('administrator can dry-run, confirm and complete a two-row CSV import', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wla-inmo-import-export');
    await expect(page.getByRole('heading', { name: 'Importar / Exportar' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Nueva importación CSV' })).toBeVisible();

    const csv = [
      'codigo,titulo,precio,operacion,tipo,comuna',
      'E2E-IMP-001,Casa Importada Uno,98000000,Venta,Casa,Curicó',
      'E2E-IMP-002,Casa Importada Dos,112000000,Venta,Casa,Curicó',
    ].join('\n');

    await page.locator('#wla-import-file').setInputFiles({
      name: 'propiedades-e2e.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from(csv, 'utf8'),
    });

    await Promise.all([
      page.waitForURL(/page=wla-inmo-import-export.*draft=/),
      page.getByRole('button', { name: 'Subir y revisar' }).click(),
    ]);

    await expect(page.getByRole('heading', { name: 'Vista previa' })).toBeVisible();
    await expect(page.locator('.wla-inmo-import__mapping')).toContainText('codigo');
    await expect(page.locator('.wla-inmo-import__mapping')).toContainText('comuna');
    await expect(page.locator('#wla-source-key')).toHaveValue('carga_manual');

    await Promise.all([
      page.waitForURL(/wla_import_notice=dry_run_ready/),
      page.getByRole('button', { name: 'Validar y simular' }).click(),
    ]);

    const dryRun = page.getByRole('heading', { name: 'Resultado de la simulación' }).locator('..');
    await expect(dryRun).toContainText('Nuevas');
    await expect(dryRun).toContainText('2');
    await expect(dryRun).toContainText('Errores');
    await expect(page.getByRole('button', { name: 'Confirmar importación' })).toBeVisible();
    await assertNoSeriousOwnUiViolations(page);

    await Promise.all([
      page.waitForURL(/page=wla-inmo-import-export.*batch=/),
      page.getByRole('button', { name: 'Confirmar importación' }).click(),
    ]);

    await expect(page.getByRole('heading', { name: 'Confirmado' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Iniciar procesamiento' })).toBeVisible();

    await Promise.all([
      page.waitForURL(/wla_import_notice=run_completed/),
      page.getByRole('button', { name: 'Iniciar procesamiento' }).click(),
    ]);

    await expect(page.getByRole('heading', { name: 'Completado' })).toBeVisible();
    await expect(page.locator('.wla-inmo-import__panel').first()).toContainText(/2 de 2 filas procesadas/);
    await expect(page.getByRole('heading', { name: 'Historial de importaciones' })).toBeVisible();
    await expect(page.locator('.wla-inmo-admin')).toContainText('carga_manual');
  });

  test('@responsive import administration remains bounded on priority viewports', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wla-inmo-import-export');
    await expect(page.getByRole('heading', { name: 'Importar / Exportar' })).toBeVisible();
    await expect(page.locator('.wla-inmo-import__steps')).toBeVisible();
    await assertNoOwnHorizontalOverflow(page, '.wla-inmo-admin');
  });
});
