<script setup>
/** Carte de chirurgie qui synthétise son état et propose la prochaine action autorisée. */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDateTime } from '@/utils/date'

const props = defineProps({
  chirurgie: { type: Object, required: true },
})

const surgeonName = computed(
  () => `Dr ${props.chirurgie.chirurgien?.prenom ?? ''} ${props.chirurgie.chirurgien?.nom ?? ''}`.trim(),
)
const progress = computed(
  () => props.chirurgie.progressionPreparation ?? { total: 0, coches: 0, complete: false },
)
const formattedModificationDate = computed(() => formatDateTime(props.chirurgie.modifieLe))
const isPartial = computed(
  () => props.chirurgie.etatValidation === 'VALIDATION_PARTIELLE' || (progress.value.complete && (progress.value.absents ?? 0) > 0),
)
</script>

<template>
  <article class="programme-card w-full">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h3 class="mt-2 text-lg font-bold leading-snug text-gray-950 dark:text-white">
          {{ chirurgie.chirurgieModele?.intitule }}
        </h3>
      </div>
      <StatusBadge :status="chirurgie.valide ? 'Validée' : isPartial ? 'Validation partielle' : progress.coches ? 'En cours' : 'À préparer'" />
    </div>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ surgeonName }}</p>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
      Dernière modification par {{ chirurgie.modifiePar ?? 'Non renseigné' }} le
      {{ formattedModificationDate }}
    </p>
    <div class="my-5">
      <ProgressBar :total="progress.total" :value="progress.traites ?? progress.coches" />
    </div>
    <div class="mt-auto flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
      <RouterLink
        v-if="!chirurgie.valide"
        :to="{ name: isPartial ? 'validation-partielle' : 'preparation', params: { id: chirurgie.id } }"
        class="compact-primary-link"
      >
        {{ isPartial ? 'Régulariser' : 'Préparer' }}
      </RouterLink>
      <RouterLink
        v-else
        :to="{ name: 'vue-finale', params: { id: chirurgie.id } }"
        class="compact-primary-link"
      >
        Vue finale
      </RouterLink>
    </div>
  </article>
</template>
