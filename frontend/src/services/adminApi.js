import { apiClient, unwrapCollection } from '@/api/axios'

/** Porte les appels CRUD génériques des référentiels administratifs, sans état d'interface. */
export const adminApi = {
  /** Liste une ressource API et normalise les formats de collection API Platform. */
  async list(resource, params = {}) {
    const { data } = await apiClient.get(`/${resource}`, {
      params,
      headers: { Accept: 'application/json' },
    })
    return unwrapCollection(data)
  },
  /** Charge une ressource administrative par son identifiant. */
  async get(resource, id) {
    const { data } = await apiClient.get(`/${resource}/${id}`)
    return data
  },
  /** Téléverse une illustration et retourne son chemin public contrôlé par l'API. */
  async uploadTechnicalSheetImage(image) {
    const body = new FormData()
    body.append('image', image)
    const { data } = await apiClient.post('/fiche-technique-images', body)
    return data.url
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
