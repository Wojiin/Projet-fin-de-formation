<script setup>
/** Vue du compte connecté et formulaire sécurisé de changement de mot de passe. */
import { reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/services/authApi'
import { getApiErrorMessage } from '@/api/axios'
import {
  PASSWORD_PATTERN_SOURCE,
  PASSWORD_REQUIREMENTS,
  validatePasswordChange,
} from '@/utils/password'
import PageContainer from '@/components/ui/PageContainer.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorMessage from '@/components/ui/ErrorMessage.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const authStore = useAuthStore()
const { user, displayName, isAdmin } = storeToRefs(authStore)

const form = reactive({
  currentPassword: '',
  newPassword: '',
  newPasswordConfirmation: '',
})
const fieldErrors = reactive({})
const apiError = ref('')
const success = ref('')
const saving = ref(false)

function replaceErrors(errors) {
  for (const key of Object.keys(fieldErrors)) delete fieldErrors[key]
  Object.assign(fieldErrors, errors)
}

async function submitPassword() {
  apiError.value = ''
  success.value = ''
  replaceErrors(validatePasswordChange(form))
  if (Object.keys(fieldErrors).length > 0) return

  saving.value = true
  try {
    await authApi.changePassword({ ...form })
    form.currentPassword = ''
    form.newPassword = ''
    form.newPasswordConfirmation = ''
    success.value = 'Votre mot de passe a été modifié.'
  } catch (error) {
    apiError.value = getApiErrorMessage(error, 'Le mot de passe n’a pas pu être modifié.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <PageContainer>
    <div class="account-layout">
      <section class="account-panel">
        <div class="account-heading">
          <div aria-hidden="true" class="account-avatar">
            {{ displayName.charAt(0).toUpperCase() }}
          </div>
          <div>
            <h2 class="section-title">Informations du compte</h2>
            <p class="text-muted">{{ user?.email }}</p>
          </div>
        </div>
        <dl class="account-metadata">
          <div>
            <dt>Rôle</dt>
            <dd><StatusBadge :status="isAdmin ? 'Admin' : 'Utilisateur'" /></dd>
          </div>
        </dl>
      </section>

      <form class="form-panel account-password-panel" @submit.prevent="submitPassword">
        <div>
          <h2 class="section-title">Changer le mot de passe</h2>
          <p class="page-description">{{ PASSWORD_REQUIREMENTS }}</p>
        </div>

        <ErrorMessage v-if="apiError" :message="apiError" />
        <div v-if="success" role="status" class="feedback-success">{{ success }}</div>

        <fieldset class="space-y-5" :disabled="saving">
          <legend class="sr-only">Nouveau mot de passe</legend>
          <BaseInput
            v-model="form.currentPassword"
            label="Mot de passe actuel"
            type="password"
            autocomplete="current-password"
            :error="fieldErrors.currentPassword"
            required
          />
          <BaseInput
            v-model="form.newPassword"
            label="Nouveau mot de passe"
            type="password"
            autocomplete="new-password"
            :minlength="12"
            :maxlength="128"
            :pattern="PASSWORD_PATTERN_SOURCE"
            :error="fieldErrors.newPassword"
            required
          />
          <BaseInput
            v-model="form.newPasswordConfirmation"
            label="Confirmer le nouveau mot de passe"
            type="password"
            autocomplete="new-password"
            :minlength="12"
            :maxlength="128"
            :pattern="PASSWORD_PATTERN_SOURCE"
            :error="fieldErrors.newPasswordConfirmation"
            required
          />
        </fieldset>

        <div class="form-actions">
          <BaseButton type="submit" :loading="saving">Modifier le mot de passe</BaseButton>
        </div>
      </form>
    </div>
  </PageContainer>
</template>
