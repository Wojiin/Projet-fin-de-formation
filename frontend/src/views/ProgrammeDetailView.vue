<script setup>
/** Vue détaillée d'un programme, alimentée par les props de route plutôt que par useRoute. */
import { watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useProgrammeStore } from '@/stores/programme'
import PageContainer from '@/components/layout/PageContainer.vue'
import ProgrammeGroup from '@/components/programme/ProgrammeGroup.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

const props = defineProps({
  date: { type: String, required: true },
  salle: { type: String, required: true },
  chirurgienId: { type: Number, required: true },
})

const programmeStore = useProgrammeStore()
const {
  selectedProgramme: programme,
  loading,
  error,
  savingProgrammeId,
} = storeToRefs(programmeStore)

watch(
  () => [props.date, props.salle, props.chirurgienId],
  ([date, salle, chirurgien]) =>
    programmeStore.loadProgramme({ date, salle, chirurgien }),
  { immediate: true },
)
</script>

<template>
  <PageContainer>
    <header class="page-heading">
      <div>
        <p class="page-eyebrow">Programme opératoire</p>
        <p class="page-title">Détail du programme</p>
        <p class="page-description">
          Consultez les chirurgies et modifiez leur ordre de passage.
        </p>
      </div>
      <RouterLink :to="{ name: 'programme' }" class="secondary-link">
        Retour à la liste des programmes
      </RouterLink>
    </header>

    <LoadingState v-if="loading && !programme" label="Chargement du programme…" />
    <ErrorMessage v-else-if="error" :message="error" />
    <ProgrammeGroup
      v-else-if="programme"
      :programme="programme"
      :saving="savingProgrammeId === programme.id"
      @reorder="programmeStore.reorderProgramme(programme, $event)"
    />
  </PageContainer>
</template>
