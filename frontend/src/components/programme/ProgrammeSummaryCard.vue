<script setup>
/** Carte légère d'un programme : affiche son identité et compose la route de détail. */
import { computed } from 'vue'
import { formatDate } from '@/utils/date'

const props = defineProps({
  programme: { type: Object, required: true },
})

const detailRoute = computed(() => ({
  name: 'programme-detail',
  params: {
    date: props.programme.date,
    salle: props.programme.salle,
    chirurgien: props.programme.chirurgien.id,
  },
}))

const formattedDate = computed(() => formatDate(props.programme.date))
</script>

<template>
  <article class="programme-summary-card">
    <div>
      <h2 class="item-title">
        Dr {{ programme.chirurgien.nom }} {{ programme.chirurgien.prenom }}
      </h2>
      <dl class="programme-summary-data">
        <div>
          <dt>Date</dt>
          <dd>{{ formattedDate }}</dd>
        </div>
        <div>
          <dt>Salle</dt>
          <dd>{{ programme.salle }}</dd>
        </div>
      </dl>
    </div>
    <RouterLink :to="detailRoute" class="secondary-link">
      Voir le détail du programme
    </RouterLink>
  </article>
</template>
