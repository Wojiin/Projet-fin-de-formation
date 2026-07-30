import { apiClient, unwrapCollection } from './apiClient'

/** Porte les appels CRUD génériques des référentiels administratifs, sans état d'interface. */
export const adminApi = {
  /** Liste une ressource API et normalise les formats de collection API Platform. */
  async list(resource, params = {}) {
    const { data } = await apiClient.get(`/${resource}`, { params })
    return unwrapCollection(data)
  },
  /** Charge une ressource administrative par son identifiant. */
  async get(resource, id) {
    const { data } = await apiClient.get(`/${resource}/${id}`)
    return data
  },
  /** Crée une ressource administrative à partir d'un payload déjà normalisé. */
  async create(resource, payload) {
    const { data } = await apiClient.post(`/${resource}`, payload)
    return data
  },
  /** Met à jour partiellement une ressource avec le média type API Platform adapté. */
  async update(resource, id, payload) {
    const { data } = await apiClient.patch(`/${resource}/${id}`, payload, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    })
    return data
  },
  /** Supprime une ressource ; les règles de dépendance restent appliquées par l'API. */
  async remove(resource, id) {
    await apiClient.delete(`/${resource}/${id}`)
  },
}
