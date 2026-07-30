import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";
import { configureApiAuth } from "./services/apiClient";
import { useAuthStore } from "./stores/auth";
import "./assets/main.css";

/** Point d'entrée : restaure la session Pinia avant d'autoriser la première navigation. */
const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

const authStore = useAuthStore(pinia);
configureApiAuth({
  getAccessToken: () => authStore.token,
  setAccessToken: (token) => authStore.setAccessToken(token),
  onSessionExpired: () => authStore.clearSession(),
});

async function bootstrap() {
  await authStore.initialize();
  app.use(router);
  await router.isReady();
  app.mount("#app");
}

bootstrap();
