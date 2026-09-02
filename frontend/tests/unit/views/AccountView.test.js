import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AccountView from '@/views/AccountView.vue'
import { authApi } from '@/services/authApi'
import { useAuthStore } from '@/stores/auth'

describe('AccountView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    const pinia = createPinia()
    setActivePinia(pinia)
    const authStore = useAuthStore()
    authStore.user = {
      id: 9876,
      email: 'user@chirorg.test',
      roles: ['ROLE_USER'],
    }
    authStore.token = 'test-token'
  })

  it('hides the user id and changes a password matching the policy', async () => {
    const changePassword = vi.spyOn(authApi, 'changePassword').mockResolvedValue()
    const wrapper = mount(AccountView)
    const inputs = wrapper.findAll('input[type="password"]')

    expect(wrapper.text()).not.toContain('Identifiant')
    expect(wrapper.text()).not.toContain('9876')
    expect(inputs).toHaveLength(3)

    await inputs[0].setValue('password')
    await inputs[1].setValue('trop-faible')
    await inputs[2].setValue('trop-faible')
    await wrapper.find('form').trigger('submit')

    expect(changePassword).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('12 caractères minimum')

    await inputs[1].setValue('NouveauMotDePasse1!')
    await inputs[2].setValue('NouveauMotDePasse1!')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(changePassword).toHaveBeenCalledWith({
      currentPassword: 'password',
      newPassword: 'NouveauMotDePasse1!',
      newPasswordConfirmation: 'NouveauMotDePasse1!',
    })
    expect(wrapper.text()).toContain('Votre mot de passe a été modifié.')
    expect(inputs.every((input) => input.element.value === '')).toBe(true)
  })
})
