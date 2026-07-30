import { apiClient } from './apiClient'

/** Isole les endpoints d'authentification du reste de la logique de session Pinia. */
export const authApi = {
  /** Authentifie des identifiants et retourne le JWT d'accès émis par l'API. */
  async login(credentials) {
    const { data } = await apiClient.post('/login', credentials)
    return data
  },
  /** Retourne le profil de l'utilisateur porté par le JWT courant. */
  async me() {
    const { data } = await apiClient.get('/me')
    return data
  },
  /** Termine la session serveur et invalide le refresh token HTTP-only. */
  async logout() {
    const { data } = await apiClient.post('/logout')
    return data
  },
}
