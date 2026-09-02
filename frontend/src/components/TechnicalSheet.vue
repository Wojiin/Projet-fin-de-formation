<script setup>
/** Affiche les fiches techniques ordonnées d'une chirurgie validée. */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { resolveApiAssetUrl } from '@/api/axios'

defineProps({
  sheets: { type: Array, default: () => [] },
})

const selectedSheet = ref(null)
const closeButton = ref(null)
let triggerElement = null
let previousBodyOverflow = ''

function openSheet(sheet, event) {
  triggerElement = event.currentTarget
  selectedSheet.value = sheet
}

function closeSheet() {
  selectedSheet.value = null
}

watch(selectedSheet, async (sheet) => {
  if (sheet) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    closeButton.value?.focus()
    return
  }

  document.body.style.overflow = previousBodyOverflow
  await nextTick()
  triggerElement?.focus()
})

onBeforeUnmount(() => {
  document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <section aria-labelledby="technical-sheet-title">
    <h2 id="technical-sheet-title" class="section-title">Fiche technique</h2>
    <ol class="mt-4 grid gap-4 lg:grid-cols-3">
      <li v-for="sheet in sheets" :key="sheet.id">
        <button
          type="button"
          data-testid="technical-sheet-trigger"
          class="technical-card h-full w-full text-left transition hover:-translate-y-0.5 hover:border-chirorg-400 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chirorg-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950"
          :aria-label="`Afficher la fiche technique ${sheet.titre} en grand`"
          aria-haspopup="dialog"
          @click="openSheet(sheet, $event)"
        >
          <span class="block text-xs font-bold uppercase tracking-wider text-chirorg-700 dark:text-chirorg-500">
            Étape {{ sheet.ordre }}
          </span>
          <span class="item-title mt-2 block">{{ sheet.titre }}</span>
          <span v-if="sheet.contenu" class="mt-3 block whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300">
            {{ sheet.contenu }}
          </span>
          <img
            v-if="sheet.image"
            :src="resolveApiAssetUrl(sheet.image)"
            :alt="`Illustration technique de l’étape ${sheet.ordre} : ${sheet.titre}`"
            class="mt-4 max-h-80 w-full rounded-xl border border-gray-200 bg-white object-contain dark:border-gray-700 dark:bg-gray-900"
          />
          <span class="mt-4 block text-xs font-semibold text-chirorg-700 dark:text-chirorg-300">
            Cliquer pour agrandir
          </span>
        </button>
      </li>
    </ol>

    <Teleport to="body">
      <div
        v-if="selectedSheet"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/80 p-2 backdrop-blur-sm sm:p-5"
        role="presentation"
        @click.self="closeSheet"
        @keydown.esc="closeSheet"
        @keydown.tab.prevent="closeButton?.focus()"
      >
        <section
          role="dialog"
          aria-modal="true"
          aria-labelledby="technical-sheet-dialog-title"
          class="flex h-[min(94vh,70rem)] w-[min(96vw,90rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
        >
          <header class="flex shrink-0 items-start justify-between gap-5 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-7">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-chirorg-700 dark:text-chirorg-400">
                Étape {{ selectedSheet.ordre }}
              </p>
              <h2 id="technical-sheet-dialog-title" class="mt-1 text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">
                {{ selectedSheet.titre }}
              </h2>
            </div>
            <button
              ref="closeButton"
              type="button"
              class="grid size-11 shrink-0 place-items-center rounded-xl border border-gray-300 text-2xl leading-none text-gray-700 transition hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chirorg-500 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              aria-label="Fermer la fiche technique"
              @click="closeSheet"
            >
              <span aria-hidden="true">×</span>
            </button>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-7">
            <p
              v-if="selectedSheet.contenu"
              class="whitespace-pre-line text-base leading-7 text-gray-700 dark:text-gray-200"
            >
              {{ selectedSheet.contenu }}
            </p>
            <img
              v-if="selectedSheet.image"
              :src="resolveApiAssetUrl(selectedSheet.image)"
              :alt="`Illustration technique de l’étape ${selectedSheet.ordre} : ${selectedSheet.titre}`"
              class="mx-auto mt-5 max-h-[calc(94vh-12rem)] w-full rounded-xl bg-white object-contain dark:bg-gray-950"
              :class="{ 'mt-0': !selectedSheet.contenu }"
            />
          </div>
        </section>
      </div>
    </Teleport>
  </section>
</template>
