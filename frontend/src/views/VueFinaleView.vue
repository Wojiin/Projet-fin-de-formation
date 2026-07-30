<script setup>
/** Vue en lecture seule de la chirurgie validée, de son matériel et de ses fiches techniques. */
import { watch } from 'vue'
import { storeToRefs } from 'pinia'
import { usePreparationStore } from '@/stores/preparation'
import { formatDate } from '@/utils/date'
import PageContainer from '@/components/layout/PageContainer.vue'
import TechnicalSheet from '@/components/finale/TechnicalSheet.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  id: { type: Number, required: true },
})

const preparationStore = usePreparationStore()
const { finalView: view, loading, error } = storeToRefs(preparationStore)

watch(
  () => props.id,
  (id) => preparationStore.loadFinalView(id),
  { immediate: true },
)
</script>

<template>
  <PageContainer>
    <LoadingState v-if="loading" label="Chargement de la vue finale…" />
    <ErrorMessage v-else-if="error" :message="error" />
    <template v-else-if="view">
      <p class="feedback-info">
        Cette vue est en lecture seule une fois la préparation validée.
      </p>

      <section class="final-overview">
        <div class="final-overview-heading">
          <div>
            <StatusBadge status="Validée" />
            <h2 class="page-title mt-4">
              {{ view.chirurgie.chirurgieModele.intitule }}
            </h2>
            <p class="mt-2 text-gray-600 dark:text-gray-300">
              Dr {{ view.chirurgie.chirurgien.prenom }} {{ view.chirurgie.chirurgien.nom }}
            </p>
          </div>
          <dl class="final-metadata">
            <div>
              <dt>Salle</dt>
              <dd>{{ view.chirurgie.salle }}</dd>
            </div>
            <div>
              <dt>Date</dt>
              <dd>{{ formatDate(view.chirurgie.date) }}</dd>
            </div>
            <div class="col-span-2">
              <dt>Validée le</dt>
              <dd>{{ formatDate(view.chirurgie.valideLe) }}</dd>
            </div>
          </dl>
        </div>
      </section>

      <section aria-labelledby="validated-materials-title">
        <h2 id="validated-materials-title" class="section-title">Matériel validé</h2>
        <ul class="validated-material-list">
          <li v-for="item in view.materiels" :key="item.id" class="validated-material">
            <p class="item-title">{{ item.materiel.intitule }}</p>
            <p class="text-muted mt-1">
              {{ item.materiel.adresse }} · {{ item.materiel.type }}
            </p>
          </li>
        </ul>
      </section>

      <TechnicalSheet :sheets="view.fichesTechniques" />
    </template>
  </PageContainer>
</template>
