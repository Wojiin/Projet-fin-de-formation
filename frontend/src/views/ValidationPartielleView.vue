<script setup>
/** Vue dédiée à la régularisation des matériels déclarés absents. */
import { computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { usePreparationStore } from '@/stores/preparation'
import { formatDate } from '@/utils/date'
import PageContainer from '@/components/ui/PageContainer.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  id: { type: Number, required: true },
})

const router = useRouter()
const preparationStore = usePreparationStore()
const { preparation, loading, savingId, error, isComplete } = storeToRefs(preparationStore)
const surgery = computed(() => preparation.value?.chirurgie)
const absentMaterials = computed(
  () => preparation.value?.preparations.filter((item) => item.absent) ?? [],
)

watch(
  () => props.id,
  async (id) => {
    const loaded = await preparationStore.loadPreparation(id)
    if (!loaded) return
    if (loaded.chirurgie.valide) {
      await router.replace({ name: 'vue-finale', params: { id } })
    } else if (loaded.chirurgie.etatValidation !== 'VALIDATION_PARTIELLE') {
      await router.replace({ name: 'preparation', params: { id } })
    }
  },
  { immediate: true },
)

/** Passe un absent à prêt et clôt automatiquement la chirurgie après le dernier. */
async function markReady(item) {
  if (!await preparationStore.setMaterialState(item, 'ready')) return
  if (!isComplete.value) return

  const result = await preparationStore.validateSurgery()
  if (result === 'final') {
    await router.push({ name: 'vue-finale', params: { id: props.id } })
  }
}
</script>

<template>
  <PageContainer>
    <LoadingState v-if="loading && !preparation" label="Chargement de la validation partielle…" />
    <ErrorMessage v-else-if="error && !preparation" :message="error" />
    <template v-else-if="surgery">
      <p class="feedback-error-soft">
        La chirurgie reste modifiable tant que du matériel est absent.
      </p>

      <section class="final-overview">
        <div class="final-overview-heading">
          <div>
            <StatusBadge status="Validation partielle" />
            <h2 class="page-title mt-4">{{ surgery.chirurgieModele.intitule }}</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
              Dr {{ surgery.chirurgien.prenom }} {{ surgery.chirurgien.nom }}
            </p>
          </div>
          <dl class="final-metadata">
            <div>
              <dt>Salle</dt>
              <dd>{{ surgery.salle }}</dd>
            </div>
            <div>
              <dt>Date</dt>
              <dd>{{ formatDate(surgery.date) }}</dd>
            </div>
          </dl>
        </div>
      </section>

      <ErrorMessage v-if="error" :message="error" />

      <section aria-labelledby="absent-materials-title">
        <h2 id="absent-materials-title" class="section-title">Matériel absent</h2>
        <p class="page-description">
          Cliquez sur un matériel dès qu’il est disponible pour le passer à « Prêt ».
        </p>
        <ul class="absent-material-list">
          <li v-for="item in absentMaterials" :key="item.id">
            <button
              type="button"
              class="absent-material-action"
              :disabled="Boolean(savingId) || loading"
              @click="markReady(item)"
            >
              <span>
                <span class="item-title block">{{ item.materiel.intitule }}</span>
                <span class="mt-1 block text-sm">
                  {{ item.materiel.adresse }} · {{ item.materiel.type }}
                </span>
              </span>
              <span class="absent-material-ready-label">Passer à « Prêt »</span>
            </button>
          </li>
        </ul>
      </section>
    </template>
  </PageContainer>
</template>
