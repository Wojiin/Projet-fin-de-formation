import axios from 'axios'

/**
 * Infrastructure HTTP commune : configure Axios, propage le JWT en mémoire et
 * coordonne un unique renouvellement de session après une réponse 401.
 */
const baseURL = (import.meta.env.VITE_API_URL || 'http://localhost:8080/api').replace(/\/+$/, '')

export const apiClient = axios.create({
  baseURL,
  withCredentials: true,
  headers: { Accept: 'application/ld+json, application/json' },
})

const refreshClient = axios.create({
  baseURL,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

const authSession = {
  getAccessToken: () => null,
  setAccessToken: () => {},
  onSessionExpired: () => {},
}

/** Branche le client HTTP sur le store d'authentification sans créer de dépendance circulaire. */
export function configureApiAuth({ getAccessToken, setAccessToken, onSessionExpired }) {
  authSession.getAccessToken =
    typeof getAccessToken === 'function' ? getAccessToken : authSession.getAccessToken
  authSession.setAccessToken =
    typeof setAccessToken === 'function' ? setAccessToken : authSession.setAccessToken
  authSession.onSessionExpired =
    typeof onSessionExpired === 'function' ? onSessionExpired : authSession.onSessionExpired
}

/** Ajoute le Bearer token en supportant les deux formes d'en-têtes Axios. */
function setAuthorizationHeader(config, token) {
  if (!token) return

  if (typeof config.headers?.set === 'function') {
    config.headers.set('Authorization', `Bearer ${token}`)
    return
  }

  config.headers = {
    ...config.headers,
    Authorization: `Bearer ${token}`,
  }
}

apiClient.interceptors.request.use((config) => {
  setAuthorizationHeader(config, authSession.getAccessToken())
  return config
})

let refreshPromise = null

/** Partage une même requête de refresh entre toutes les requêtes simultanément expirées. */
export function refreshAccessToken() {
  refreshPromise ??= refreshClient
    .post('/token/refresh')
    .then(({ data }) => {
      if (!data?.token) {
        throw new Error('La réponse de renouvellement ne contient aucun token.')
      }

      authSession.setAccessToken(data.token)
      return data.token
    })
    .finally(() => {
      refreshPromise = null
    })

  return refreshPromise
}

/** Identifie les routes qui ne doivent jamais déclencher une boucle de renouvellement. */
function isAuthenticationRequest(url = '') {
  return ['/login', '/token/refresh', '/logout'].some(
    (path) => url === path || url.endsWith(path),
  )
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const request = error.config

    if (
      error.response?.status !== 401 ||
      !request ||
      request._retry ||
      isAuthenticationRequest(request.url)
    ) {
      return Promise.reject(error)
    }

    request._retry = true

    try {
      const token = await refreshAccessToken()
      setAuthorizationHeader(request, token)
      return apiClient(request)
    } catch (refreshError) {
      authSession.onSessionExpired()
      return Promise.reject(refreshError)
    }
  },
)

/** Extrait le message métier le plus utile des différents formats d'erreur API Platform. */
export function getApiErrorMessage(error, fallback = 'Une erreur est survenue.') {
  return (
    error.response?.data?.detail ||
    error.response?.data?.message ||
    error.response?.data?.['hydra:description'] ||
    fallback
  )
}

/** Normalise les collections JSON-LD et les tableaux JSON simples. */
export function unwrapCollection(data) {
  return data?.member ?? data?.['hydra:member'] ?? data ?? []
}
