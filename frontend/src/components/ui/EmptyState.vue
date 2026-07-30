<script setup>
/** État vide réutilisable qui explique l'absence de résultat et accepte une action optionnelle. */
defineProps({
  title: { type: String, default: 'Aucune donnée' },
  message: { type: String, default: 'Aucun élément ne correspond à votre recherche.' },
})
</script>

<template>
  <div class="empty-state">
    <div aria-hidden="true" class="mx-auto mb-4 grid size-12 place-items-center rounded-full bg-gray-100 text-xl text-gray-500 dark:bg-gray-700">—</div>
    <p class="item-title">{{ title }}</p>
    <p class="text-muted mx-auto mt-2 max-w-md">{{ message }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>
