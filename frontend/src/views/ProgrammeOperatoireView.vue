<script setup>
/** Vue liste des programmes : gère uniquement les filtres d'interface et leur chargement. */
import { onMounted, reactive, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useProgrammeStore } from '@/stores/programme'
import PageContainer from '@/components/layout/PageContainer.vue'
import ProgrammeSummaryCard from '@/components/programme/ProgrammeSummaryCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

const programmeStore = useProgrammeStore()
const {
  filters: storedFilters,
  rooms,
  filteredProgrammes,
  loading,
  error,
} = storeToRefs(programmeStore)

const filters = reactive({ ...storedFilters.value })

/** Recharge la liste en appliquant l'état local des filtres facultatifs. */
function loadProgrammes() {
  return programmeStore.fetchProgrammes({ ...filters })
}

/** Réinitialise les deux filtres et déclenche le rechargement via le watcher. */
function clearFilters() {
  filters.date = ''
  filters.room = ''
}

onMounted(loadProgrammes)
watch(filters, loadProgrammes)
</script>

<template>
  <PageContainer>
    <header class="page-heading">
      <div>
        <p class="page-eyebrow">Vue d’ensemble</p>
        <p class="page-title">Programme opératoire</p>
        <p class="page-description">
          Retrouvez les programmes par chirurgien, date et salle.
        </p>
      </div>
      <RouterLink :to="{ name: 'planification' }" class="primary-link">
        + Planifier un programme
      </RouterLink>
    </header>

    <form
      aria-label="Filtrer les programmes"
      class="programme-filters"
      @submit.prevent="loadProgrammes"
    >
      <BaseInput v-model="filters.date" label="Date" type="date" />
      <BaseSelect
        v-model="filters.room"
        label="Salle"
        placeholder="Toutes les salles"
        :options="rooms"
        allow-empty
      />
      <BaseButton
        v-if="filters.date || filters.room"
        type="button"
        variant="ghost"
        size="sm"
        class="justify-self-start sm:col-span-2"
        @click="clearFilters"
      >
        Effacer les filtres
      </BaseButton>
    </form>

    <ErrorMessage v-if="error" :message="error" />
    <LoadingState v-if="loading" label="Chargement du programme opératoire…" />
    <EmptyState
      v-else-if="!filteredProgrammes.length"
      title="Aucun programme planifié"
      message="Modifiez les filtres ou planifiez un nouveau programme."
    />
    <section v-else aria-label="Programmes opératoires">
      <ol class="programme-summary-list">
        <li v-for="programme in filteredProgrammes" :key="programme.id">
          <ProgrammeSummaryCard :programme="programme" />
        </li>
      </ol>
    </section>
  </PageContainer>
</template>
