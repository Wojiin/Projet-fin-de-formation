<script setup>
/** Affiche les fiches techniques ordonnées d'une chirurgie validée. */
defineProps({
  sheets: { type: Array, default: () => [] },
})
</script>

<template>
  <section aria-labelledby="technical-sheet-title">
    <h2 id="technical-sheet-title" class="section-title">Fiche technique</h2>
    <ol class="mt-4 grid gap-4 lg:grid-cols-3">
      <li
        v-for="sheet in sheets"
        :key="sheet.id"
      >
        <article class="technical-card h-full">
          <p class="text-xs font-bold uppercase tracking-wider text-chirorg-700 dark:text-chirorg-500">
            Étape {{ sheet.ordre }}
          </p>
          <h3 class="item-title mt-2">{{ sheet.titre }}</h3>
          <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ sheet.contenu }}</p>
          <div
            v-if="sheet.image"
            role="img"
            :aria-label="`Illustration technique de l’étape ${sheet.ordre} : ${sheet.titre}`"
            class="mt-4 grid aspect-video place-items-center rounded-xl border border-dashed border-gray-300 bg-gray-100 text-xs text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400"
          >
            Illustration technique
          </div>
        </article>
      </li>
    </ol>
  </section>
</template>
