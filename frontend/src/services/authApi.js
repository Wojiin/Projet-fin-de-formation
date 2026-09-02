import { apiClient } from '@/api/axios'

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
  /** Vérifie le secret actuel puis remplace le mot de passe du compte connecté. */
  async changePassword(passwords) {
    await apiClient.post('/me/password', passwords)
  },
  /** Termine la session serveur et invalide le refresh token HTTP-only. */
  async logout() {
    const { data } = await apiClient.post('/logout')
    return data
  },
}
