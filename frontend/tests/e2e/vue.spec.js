import { test, expect } from '@playwright/test'

async function login(page) {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible()
  await page.getByLabel('Email').fill('admin@chirorg.test')
  await page.getByLabel('Mot de passe').fill('password')
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page.getByText('Programme opératoire', { exact: true }).first()).toBeVisible()
}

test('authenticates and loads a programme from the API', async ({ page }) => {
  await login(page)
  await page.evaluate(() => {
    window.__chirorgSpaMarker = 'preserved'
  })
  const programmeLinks = page.getByRole('link', { name: 'Voir le détail du programme' })
  await expect(programmeLinks.first()).toBeVisible()
  await programmeLinks.first().click()

  await expect(page.getByRole('heading', { name: 'Détail du programme' })).toBeVisible()
  expect(await page.evaluate(() => window.__chirorgSpaMarker)).toBe('preserved')
  await expect(page.locator('article.programme-card').first()).toBeVisible()
  await expect(
    page.getByRole('link', { name: /Préparer|Vue finale/ }).first(),
  ).toBeVisible()
})

test('renews the HttpOnly refresh session after a page reload', async ({ page }) => {
  await login(page)
  await page.reload()

  await expect(page).toHaveURL(/\/programme$/)
  await expect(page.getByRole('button', { name: 'Déconnexion' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Connexion' })).not.toBeVisible()
})

test('loads administration forms and planning references from the API', async ({ page }) => {
  await login(page)

  await page.goto('/admin/materiels')
  await expect(page.getByRole('link', { name: /Modifier/ }).first()).toBeVisible()
  await page.getByRole('link', { name: /Modifier/ }).first().click()
  await expect(page).toHaveURL(/\/admin\/materiels\/\d+\/edit$/)
  await expect(page.getByText('Impossible de charger le formulaire.')).not.toBeVisible()
  await expect(page.getByLabel('Type de matériel')).not.toHaveValue('')
  await expect(page.getByLabel('Spécialité')).not.toHaveValue('')

  await page.goto('/planifier')
  await expect(page.getByLabel('Chirurgien').locator('option')).not.toHaveCount(1)
  await expect(page.getByLabel('Chirurgie 1').locator('option')).not.toHaveCount(1)
})
