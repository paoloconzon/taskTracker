<template>
  <v-app>
    <!-- NAVIGATION DRAWER -->
    <v-navigation-drawer v-if="isLoggedIn" v-model="drawer" rail expand-on-hover>
      <v-list-item
        prepend-icon="mdi-clock-check-outline"
        title="Task Tracker"
        nav
      />
      <v-divider />
      <v-list density="compact" nav>
        <v-list-item to="/"          prepend-icon="mdi-home"               title="Nuovo Task" />
        <v-list-item to="/task-list" prepend-icon="mdi-format-list-checks" title="Task recenti" />
        <v-list-item to="/argomenti" prepend-icon="mdi-tag-multiple"       title="Argomenti" />
        <v-list-item to="/log"          prepend-icon="mdi-table-clock"        title="Log attività" />
        <v-list-item to="/mantis-export" prepend-icon="mdi-bug-check"        title="Esporta Mantis" />
      </v-list>
      <template #append>
        <v-list density="compact" nav>
          <v-list-item to="/profilo"  prepend-icon="mdi-account-cog"       title="Profilo utente" />
          <v-list-item to="/credits" prepend-icon="mdi-information-outline" title="Credits" />
          <v-list-item prepend-icon="mdi-logout" title="Logout" @click="logout" />
        </v-list>
      </template>
    </v-navigation-drawer>

    <!-- APP BAR -->
    <v-app-bar v-if="isLoggedIn" color="primary" elevation="2">
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-app-bar-title>
        <span class="font-weight-bold">Task Tracker</span>
      </v-app-bar-title>
      <template #append>
        <!-- TASK ATTIVO CHIP -->
        <TaskAttivoChip @click="router.push('/task-attivo')" />
        <v-btn icon="mdi-account-circle" variant="text" class="ml-2" />
      </template>
    </v-app-bar>

    <!-- CONTENT -->
    <v-main :class="isLoggedIn ? 'bg-background' : ''">
      <router-view />
    </v-main>

    <!-- SNACKBAR GLOBALE -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000" location="bottom right">
      {{ snackbar.text }}
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter }                from 'vue-router'
import { useSessionStore }          from './stores/session.js'
import { useTaskStore }             from './stores/task.js'
import { apiLogout, apiGetTaskAttivo } from './api/index.js'
import TaskAttivoChip               from './components/TaskAttivoChip.vue'

const router   = useRouter()
const sess     = useSessionStore()
const taskStore = useTaskStore()
const drawer   = ref(true)

const isLoggedIn = computed(() => sess.isLoggedIn())

const snackbar = ref({ show: false, text: '', color: 'success' })

// Espone snackbar globalmente (semplice event bus tramite window)
window.$notify = (text, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

async function logout() {
  try { await apiLogout() } catch {}
  sess.clearSession()
  taskStore.clearTask()
  router.push({ name: 'Login' })
}

onMounted(async () => {
  if (sess.isLoggedIn()) {
    try {
      const r = await apiGetTaskAttivo()
      taskStore.setTaskAttivo(r.data.task)
    } catch {}
  }
})
</script>
