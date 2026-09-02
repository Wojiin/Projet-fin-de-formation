import process from 'node:process'
import { expect, test } from '@playwright/test'

const accessToken = process.env.CHIRORG_E2E_TOKEN

test.describe('material list speciality filter', () => {
  test.skip(!accessToken, 'CHIRORG_E2E_TOKEN is required to exercise the authenticated API.')

  test('displays lists for the selected surgery speciality', async ({ page }) => {
    await page.route('http://localhost:8080/api/token/refresh', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ token: accessToken }),
      })
    })

    await page.goto('/admin/listes-materiel')
    await expect(page.locator('.page-title')).toHaveText('Listes de matériel')

    const filteredResponsePromise = page.waitForResponse((response) =>
      response.url().includes('/api/listes-materiel?specialite='),
    )
    await page.getByLabel('Spécialité').selectOption({ label: 'Traumatologie' })
    const filteredResponse = await filteredResponsePromise
    const filteredPayload = await filteredResponse.json()

    expect(filteredResponse.ok()).toBe(true)
    expect(filteredPayload).toHaveLength(20)
    expect(await page.getByLabel('Spécialité').inputValue()).toBe('15')

    await expect(page.locator('.admin-table-shell tbody tr')).toHaveCount(20)
    await expect(page.getByText('Aucune donnée')).toHaveCount(0)

    const combinedResponsePromise = page.waitForResponse((response) =>
      response.url().includes('/api/listes-materiel?')
      && response.url().includes('specialite=15')
      && response.url().includes('chirurgien=19'),
    )
    await page.getByLabel('Chirurgien').selectOption({ label: 'Dr Jean Dupont' })
    const combinedPayload = await (await combinedResponsePromise).json()

    expect(combinedPayload).toHaveLength(4)
    await expect(page.locator('.admin-table-shell tbody tr')).toHaveCount(4)
  })
})
