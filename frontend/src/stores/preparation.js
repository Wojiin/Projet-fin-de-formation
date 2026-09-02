import { defineStore } from 'pinia'
import { preparationApi } from '@/services/preparationApi'
import { getApiErrorMessage } from '@/api/axios'

/** Adapte la réponse de checklist de l'API au modèle attendu par l'interface de préparation. */
export function normalizePreparation(data) {
  return {
    chirurgie: {
      id: data.id,
      dateProgrammee: data.dateProgrammee,
      date: data.dateProgrammee,
      salle: data.salle,
      ordre: data.ordre,
      valide: data.valide,
      valideLe: data.valideLe,
      etatValidation: data.etatValidation ?? (data.valide ? 'VALIDEE' : 'EN_PREPARATION'),
      chirurgien: data.chirurgien,
      chirurgieModele: data.chirurgieModele,
    },
    preparations: (data.preparationsMateriel ?? []).map((item) => ({
      ...item,
      materiel: {
        ...item.materiel,
        type: item.materiel?.type ?? item.materiel?.typeMateriel,
      },
    })),
    progressionPreparation: data.progressionPreparation,
  }
}

/** Adapte les deux formes historiques de vue finale au même contrat d'affichage. */
export function normalizeFinalView(data) {
  const surgerySource = data.chirurgie ?? data
  const materials = data.materiels ?? data.materielsValides ?? []
  const technicalSheets = data.fichesTechniques ?? data.ficheTechnique ?? []

  return {
    chirurgie: normalizePreparation(surgerySource).chirurgie,
    validePar: data.validePar ?? null,
    materiels: materials.map((item) => {
      if (item.materiel) {
        return {
          ...item,
          materiel: {
            ...item.materiel,
            type: item.materiel.type ?? item.materiel.typeMateriel,
          },
        }
      }

      return {
        id: item.id,
        coche: true,
        cocheLe: item.cocheLe,
        materiel: {
          id: item.id,
          intitule: item.intitule,
          adresse: item.adresse,
          type: item.typeMateriel,
        },
      }
    }),
    fichesTechniques: technicalSheets.map((sheet) => ({
      ...sheet,
      contenu: sheet.contenu ?? sheet.description ?? '',
      image: sheet.image ?? sheet.lienImage ?? null,
    })),
  }
}

/** Gère la checklist, sa progression optimiste et la validation finale d'une chirurgie. */
export const usePreparationStore = defineStore('preparation', {
  state: () => ({
    preparation: null,
    finalView: null,
    loading: false,
    savingId: null,
    error: '',
  }),

  getters: {
    isComplete: (state) =>
      Boolean(state.preparation?.preparations?.length) &&
      state.preparation.preparations.every((item) => item.coche),
    isResolved: (state) =>
      Boolean(state.preparation?.preparations?.length) &&
      state.preparation.preparations.every((item) => item.coche || item.absent),
    isPartial: (state) =>
      state.preparation?.chirurgie?.etatValidation === 'VALIDATION_PARTIELLE',
  },

  actions: {
    /** Recalcule localement les compteurs utilisés par la barre de progression. */
    updateProgress() {
      if (!this.preparation) return

      const total = this.preparation.preparations.length
      const coches = this.preparation.preparations.filter((item) => item.coche).length
      const absents = this.preparation.preparations.filter((item) => item.absent).length
      const traites = coches + absents
      this.preparation.progressionPreparation = {
        total,
        coches,
        absents,
        traites,
        complete: total > 0 && total === traites,
      }
    },

    /** Charge la checklist fraîche d'une chirurgie et réinitialise les vues incompatibles. */
    async loadPreparation(id) {
      this.loading = true
      this.error = ''
      this.preparation = null
      this.finalView = null

      try {
        const data = await preparationApi.getPreparation(id)
        this.preparation = normalizePreparation(data)
        return this.preparation
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Impossible de charger la préparation.')
        return null
      } finally {
        this.loading = false
      }
    },

    /** Bascule l'état d'un matériel de façon optimiste puis restaure l'ancien état si l'API échoue. */
    async setMaterialState(item, state) {
      const previous = { coche: item.coche, absent: item.absent }
      item.coche = state === 'ready' ? !item.coche : false
      item.absent = state === 'absent' ? !item.absent : false
      this.updateProgress()
      this.savingId = item.id
      this.error = ''

      try {
        Object.assign(item, await preparationApi.toggle(item.id, {
          coche: item.coche,
          absent: item.absent,
        }))
        this.updateProgress()
        return true
      } catch (error) {
        Object.assign(item, previous)
        this.updateProgress()
        this.error = getApiErrorMessage(error, 'La mise à jour du matériel a échoué.')
        return false
      } finally {
        this.savingId = null
      }
    },

    async toggleMaterial(item) {
      return this.setMaterialState(item, 'ready')
    },

    /** Valide une chirurgie uniquement lorsque toutes ses lignes sont cochées. */
    async validateSurgery() {
      if (!this.isResolved || !this.preparation) return false

      this.loading = true
      this.error = ''

      try {
        const data = await preparationApi.validate(this.preparation.chirurgie.id)
        this.preparation.chirurgie = {
          ...this.preparation.chirurgie,
          ...data,
          etatValidation: data.valide ? 'VALIDEE' : 'VALIDATION_PARTIELLE',
        }
        return data.valide ? 'final' : 'partial'
      } catch (error) {
        this.error = getApiErrorMessage(error, 'La validation de la chirurgie a échoué.')
        return false
      } finally {
        this.loading = false
      }
    },

    /** Charge la vue finale et vide la checklist de travail devenue obsolète. */
    async loadFinalView(id) {
      this.loading = true
      this.error = ''
      this.preparation = null
      this.finalView = null

      try {
        const data = await preparationApi.getFinalView(id)
        this.finalView = normalizeFinalView(data)
        return this.finalView
      } catch (error) {
        this.error = getApiErrorMessage(error, 'Impossible de charger la vue finale.')
        return null
      } finally {
        this.loading = false
      }
    },
  },
})
