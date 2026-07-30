import { defineStore } from 'pinia'
import { programmeApi } from '@/services/programmeApi'
import { getApiErrorMessage, unwrapCollection } from '@/services/apiClient'

/** Uniformise une chirurgie planifiée, quelle que soit la forme du payload API reçu. */
export function normalizePlannedSurgery(data) {
  return {
    ...data,
    date: data.dateProgrammee ?? data.date ?? '',
    progressionPreparation: data.progressionPreparation ?? {
      total: data.preparationsMateriel?.length ?? 0,
      coches: data.preparationsMateriel?.filter((item) => item.coche).length ?? 0,
      complete:
        Boolean(data.preparationsMateriel?.length) &&
        data.preparationsMateriel.every((item) => item.coche),
    },
  }
}

/** Uniformise le détail d'un programme et propage ses informations communes aux chirurgies. */
export function normalizeProgramme(data) {
  return {
    ...data,
    chirurgies: (data.chirurgies ?? []).map((chirurgie) =>
      normalizePlannedSurgery({
        ...chirurgie,
        date: data.date,
        salle: data.salle,
        chirurgien: data.chirurgien,
      }),
    ),
  }
}

/** Normalise la représentation légère utilisée par la liste des programmes. */
export function normalizeProgrammeSummary(data) {
  return {
    ...data,
    id: data.id ?? `${data.date}|${data.salle}|${data.chirurgien.id}`,
    chirurgies: data.chirurgies ?? [],
  }
}

/** Charge et normalise les résumés sans déclencher la lecture coûteuse des détails. */
export async function loadProgrammeSummaries(params = {}, client = programmeApi) {
  const response = await client.list(params)
  const data = response?.data ?? response
  return unwrapCollection(data).map(normalizeProgrammeSummary)
}

/** Gère les programmes, les filtres locaux, la création et le réordonnancement persistant. */
export const useProgrammeStore = defineStore('programme', {
  state: () => ({
    programmes: [],
    selectedProgramme: null,
    filters: {
      date: '',
      room: '',
    },
    loading: false,
    planning: false,
    savingProgrammeId: null,
    error: '',
  }),

  getters: {
    chirurgies: (state) => state.programmes.flatMap((programme) => programme.chirurgies),
    rooms: (state) => [...new Set(state.programmes.map((item) => item.salle))].sort(),
    filteredProgrammes: (state) =>
      state.programmes.filter(
        (programme) =>
          (!state.filters.date || programme.date === state.filters.date) &&
          (!state.filters.room || programme.salle === state.filters.room),
      ),
    filteredChirurgies() {
      return this.filteredProgrammes.flatMap((programme) => programme.chirurgies)
    },
    hasActiveFilters: (state) => Boolean(state.filters.date || state.filters.room),
  },

  actions: {
    /** Remplace les filtres de liste de manière atomique. */
    setFilters({ date = this.filters.date, room = this.filters.room } = {}) {
      this.filters = { date, room }
    },

    /** Réinitialise les filtres facultatifs de programme. */
    clearFilters() {
      this.filters = { date: '', room: '' }
    },

    /** Insère ou remplace un programme dans le cache local à partir de la réponse API. */
    upsertProgramme(data) {
      const programme = normalizeProgramme(data)
      const index = this.programmes.findIndex((item) => item.id === programme.id)

      if (index === -1) {
        this.programmes.push(programme)
      } else {
        this.programmes[index] = programme
      }

      return programme
    },

    /** Charge la liste filtrée et expose toute erreur métier à l'interface. */
    async fetchProgrammes(filters = this.filters) {
      this.setFilters(filters)
      this.loading = true
      this.error = ''

      try {
        const params = {
          ...(this.filters.date ? { date: this.filters.date } : {}),
          ...(this.filters.room ? { salle: this.filters.room } : {}),
        }
        this.programmes = await loadProgrammeSummaries(params)
        return this.programmes
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Impossible de charger le programme opératoire.')
        return []
      } finally {
        this.loading = false
      }
    },

    /** Charge le détail d'un programme sélectionné pour sa consultation ou son réordonnancement. */
    async loadProgramme({ date, salle, chirurgien }) {
      this.loading = true
      this.error = ''
      this.selectedProgramme = null

      try {
        const data = await programmeApi.getProgramme({ date, salle, chirurgien })
        this.selectedProgramme = normalizeProgramme(data)
        return this.selectedProgramme
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Impossible de charger le détail du programme.')
        return null
      } finally {
        this.loading = false
      }
    },

    /** Planifie un programme multi-chirurgies, met à jour le cache puis cible ses filtres. */
    async planProgramme(payload) {
      this.planning = true
      this.error = ''

      try {
        const createdProgramme = await programmeApi.planProgram(payload)
        this.upsertProgramme(createdProgramme)
        this.setFilters({
          date: payload.dateProgrammee,
          room: payload.salle,
        })
        return createdProgramme
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Le programme n’a pas pu être planifié.')
        return null
      } finally {
        this.planning = false
      }
    },

    /** Applique un ordre optimiste, le persiste côté API et le restaure en cas d'échec. */
    async reorderProgramme(programme, chirurgieIds) {
      const previousChirurgies = programme.chirurgies.map((chirurgie) => ({ ...chirurgie }))
      const byId = new Map(programme.chirurgies.map((chirurgie) => [String(chirurgie.id), chirurgie]))
      programme.chirurgies = chirurgieIds.map((id, index) => ({
        ...byId.get(String(id)),
        ordre: index + 1,
      }))
      this.savingProgrammeId = programme.id
      this.error = ''

      try {
        const data = await programmeApi.reorder({
          date: programme.date,
          salle: programme.salle,
          chirurgien: programme.chirurgien.id,
          chirurgieIds,
        })
        Object.assign(programme, normalizeProgramme(data))
        return true
      } catch (error) {
        programme.chirurgies = previousChirurgies
        this.error = getApiErrorMessage(error, 'Le nouvel ordre n’a pas pu être enregistré.')
        return false
      } finally {
        this.savingProgrammeId = null
      }
    },
  },
})
