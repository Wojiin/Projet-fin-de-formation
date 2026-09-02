import { defineStore } from 'pinia'
import { adminApi } from '@/services/adminApi'
import { getApiErrorMessage } from '@/api/axios'

/** Porte l'état et les actions CRUD de l'écran d'administration courant. */
export const useAdminStore = defineStore('admin', {
  state: () => ({
    resource: '',
    items: [],
    current: null,
    loading: false,
    saving: false,
    deletingId: null,
    error: '',
  }),

  actions: {
    /** Charge la collection du référentiel demandé et remplace les résultats précédents. */
    async loadItems(resource, params = {}) {
      this.resource = resource
      this.loading = true
      this.error = ''

      try {
        this.items = await adminApi.list(resource, params)
        return this.items
      } catch (error) {
        this.items = []
        this.error = getApiErrorMessage(error, 'Impossible de charger ce référentiel.')
        return []
      } finally {
        this.loading = false
      }
    },

    /** Charge un élément à modifier tout en réinitialisant l'élément courant. */
    async loadItem(resource, id) {
      this.resource = resource
      this.current = null
      this.loading = true
      this.error = ''

      try {
        this.current = await adminApi.get(resource, id)
        return this.current
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Impossible de charger cette ressource.')
        return null
      } finally {
        this.loading = false
      }
    },

    /** Crée ou met à jour un élément selon la présence de son identifiant. */
    async saveItem(resource, id, payload) {
      this.saving = true
      this.error = ''

      try {
        const item = id
          ? await adminApi.update(resource, id, payload)
          : await adminApi.create(resource, payload)
        this.current = item
        return item
      } catch (error) {
        this.error = getApiErrorMessage(error, 'L’enregistrement a échoué.')
        return null
      } finally {
        this.saving = false
      }
    },

    /** Supprime un élément puis retire immédiatement sa représentation de la liste locale. */
    async removeItem(resource, id) {
      this.deletingId = id
      this.error = ''

      try {
        await adminApi.remove(resource, id)
        this.items = this.items.filter((item) => item.id !== id)
        return true
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Cette ressource ne peut pas être supprimée.')
        return false
      } finally {
        this.deletingId = null
      }
    },
  },
})
