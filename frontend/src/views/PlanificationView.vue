<script setup>
/** Vue de création d'un programme multi-chirurgies avec ordre initial et date minimale. */
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { getTomorrowDateValue } from '@/utils/date'
import { useProgrammeStore } from '@/stores/programme'
import { useReferenceStore } from '@/stores/references'
import PageContainer from '@/components/ui/PageContainer.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'

const router = useRouter()
const programmeStore = useProgrammeStore()
const referenceStore = useReferenceStore()
const { planning, error: programmeError } = storeToRefs(programmeStore)
const { loading: referencesLoading, error: referenceError } = storeToRefs(referenceStore)

const minimumDate = getTomorrowDateValue()
const formError = ref('')
const form = reactive({
  specialiteId: '',
  chirurgienId: '',
  dateProgrammee: minimumDate,
  salle: 'Salle A',
  chirurgieModeleIds: [''],
})

const rooms = ['Salle A', 'Salle B', 'Salle C']
const specialties = computed(() =>
  referenceStore
    .getCollection('specialites')
    .map((item) => ({ value: item.id, label: item.intitule }))
    .sort((left, right) => left.label.localeCompare(right.label, 'fr')),
)
const surgeons = computed(() =>
  referenceStore
    .getCollection('chirurgiens')
    .filter((item) => String(item.specialite?.id) === String(form.specialiteId))
    .map((item) => ({
      value: item.id,
      label: `Dr ${item.prenom} ${item.nom}`,
    }))
    .sort((left, right) => left.label.localeCompare(right.label, 'fr')),
)
const surgeries = computed(() =>
  referenceStore
    .getCollection('chirurgie-modeles')
    .filter((item) => String(item.specialite?.id) === String(form.specialiteId))
    .map((item) => ({
      value: item.id,
      label: item.intitule,
    }))
    .sort((left, right) => left.label.localeCompare(right.label, 'fr')),
)
const displayedError = computed(
  () => formError.value || programmeError.value || referenceError.value,
)

/** Ajoute une position de chirurgie à la fin de l'ordre initial. */
function addSurgery() {
  form.chirurgieModeleIds.push('')
}

/** Retire une position sans permettre de vider entièrement le programme. */
function removeSurgery(index) {
  if (form.chirurgieModeleIds.length > 1) {
    form.chirurgieModeleIds.splice(index, 1)
  }
}

/** Efface les choix dépendants lorsqu'une autre spécialité est sélectionnée. */
watch(
  () => form.specialiteId,
  () => {
    form.chirurgienId = ''
    form.chirurgieModeleIds = ['']
  },
)

onMounted(async () => {
  try {
    await referenceStore.load(['specialites', 'chirurgiens', 'chirurgie-modeles'])
  } catch {
    // Le store expose le message d'erreur à la vue.
  }
})

/** Valide le formulaire local puis délègue la création atomique au store de programme. */
async function submit() {
  formError.value = ''
  const surgeryModelIds = form.chirurgieModeleIds.map(Number)

  if (
    !form.specialiteId ||
    !form.chirurgienId ||
    !form.dateProgrammee ||
    !form.salle ||
    surgeryModelIds.some((id) => !Number.isInteger(id) || id <= 0)
  ) {
    formError.value =
      'La spécialité, le chirurgien, la date, la salle et chaque modèle de chirurgie sont obligatoires.'
    return
  }
  if (
    !surgeons.value.some((item) => Number(item.value) === Number(form.chirurgienId)) ||
    surgeryModelIds.some(
      (id) => !surgeries.value.some((item) => Number(item.value) === id),
    )
  ) {
    formError.value = 'Le chirurgien et les chirurgies doivent correspondre à la spécialité sélectionnée.'
    return
  }
  if (form.dateProgrammee < minimumDate) {
    formError.value = 'La date du programme doit être au minimum celle de demain.'
    return
  }

  const createdProgramme = await programmeStore.planProgramme({
    chirurgienId: Number(form.chirurgienId),
    chirurgieModeleIds: surgeryModelIds,
    dateProgrammee: form.dateProgrammee,
    salle: form.salle,
  })

  if (createdProgramme) {
    await router.push({ name: 'programme' })
  }
}
</script>

<template>
  <PageContainer>
    <header>
      <p class="page-eyebrow">Programme opératoire</p>
      <p class="page-title">Planifier un programme</p>
      <p class="page-description">
        Choisissez une spécialité pour filtrer le chirurgien et les interventions, puis définissez leur ordre initial.
      </p>
    </header>

    <form class="form-panel" @submit.prevent="submit">
      <ErrorMessage v-if="displayedError" :message="displayedError" />

      <fieldset class="programme-form-section">
        <legend class="section-title">Informations du programme</legend>
        <div class="form-grid mt-5">
          <BaseSelect
            v-model="form.specialiteId"
            label="Spécialité"
            :options="specialties"
            placeholder="Sélectionner une spécialité"
            required
          />
          <BaseSelect
            v-model="form.chirurgienId"
            label="Chirurgien"
            :options="surgeons"
            :placeholder="form.specialiteId ? 'Sélectionner un chirurgien' : 'Sélectionner d’abord une spécialité'"
            :disabled="!form.specialiteId"
            required
          />
          <BaseInput
            v-model="form.dateProgrammee"
            label="Date programmée"
            type="date"
            :min="minimumDate"
            required
          />
          <BaseSelect v-model="form.salle" label="Salle" :options="rooms" required />
        </div>
      </fieldset>

      <fieldset class="programme-form-section">
        <legend class="section-title">Chirurgies du programme</legend>
        <p class="text-muted mt-2">L’ordre de cette liste devient l’ordre initial du programme.</p>

        <ol class="surgery-model-list">
          <li
            v-for="(_, index) in form.chirurgieModeleIds"
            :key="index"
            class="surgery-model-row"
          >
            <BaseSelect
              v-model="form.chirurgieModeleIds[index]"
              :label="`Chirurgie ${index + 1}`"
              :options="surgeries"
              :placeholder="form.specialiteId ? 'Sélectionner une intervention' : 'Sélectionner d’abord une spécialité'"
              :disabled="!form.specialiteId"
              required
            />
            <BaseButton
              v-if="form.chirurgieModeleIds.length > 1"
              type="button"
              variant="ghost"
              size="sm"
              class="surgery-remove-button"
              @click="removeSurgery(index)"
            >
              Retirer
            </BaseButton>
          </li>
        </ol>

        <BaseButton type="button" variant="secondary" class="mt-4" @click="addSurgery">
          + Ajouter une chirurgie
        </BaseButton>
      </fieldset>

      <div class="form-actions">
        <BaseButton type="button" variant="secondary" @click="router.back()">Annuler</BaseButton>
        <BaseButton type="submit" :disabled="referencesLoading" :loading="planning">
          {{ referencesLoading ? 'Chargement des référentiels…' : 'Planifier le programme' }}
        </BaseButton>
      </div>
    </form>
  </PageContainer>
</template>
