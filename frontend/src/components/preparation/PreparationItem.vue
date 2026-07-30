<script setup>
/** Ligne interactive de checklist : affiche un matériel et remonte une intention de bascule au parent. */
defineProps({
  item: { type: Object, required: true },
  disabled: Boolean,
})
defineEmits(['toggle'])
</script>

<template>
  <label
    class="preparation-item"
    :class="item.coche ? 'border-chirorg-300 dark:border-chirorg-700' : 'border-gray-200 dark:border-gray-700'"
  >
    <input
      type="checkbox"
      :checked="item.coche"
      :disabled="disabled"
      class="mt-0.5 size-5 shrink-0 accent-chirorg-600 disabled:cursor-not-allowed"
      @change="$emit('toggle', item)"
    />
    <span class="min-w-0 flex-1">
      <span class="item-title block">{{ item.materiel.intitule }}</span>
      <span class="text-muted mt-1 block">
        Adresse : {{ item.materiel.adresse }} · Type : {{ item.materiel.type }}
      </span>
    </span>
    <span class="text-xs font-semibold" :class="item.coche ? 'text-chirorg-700 dark:text-chirorg-500' : 'text-gray-400'">
      {{ item.coche ? 'Prêt' : 'À faire' }}
    </span>
  </label>
</template>
