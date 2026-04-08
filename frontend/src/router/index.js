import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore }                from '../stores/session.js'

const routes = [
  { path: '/login',     name: 'Login',     component: () => import('../views/LoginView.vue'),     meta: { public: true } },
  { path: '/',          name: 'Home',      component: () => import('../views/HomeView.vue') },
  { path: '/argomenti', name: 'Argomenti', component: () => import('../views/ArgomentiView.vue') },
  { path: '/log',       name: 'Log',       component: () => import('../views/LogView.vue') },
  { path: '/task-list',   name: 'TaskList',   component: () => import('../views/TaskListView.vue') },
  { path: '/task-attivo',      name: 'TaskAttivo',      component: () => import('../views/TaskAttivoView.vue') },
  { path: '/mantis-export',   name: 'MantisExport',   component: () => import('../views/MantisExportView.vue') },
  { path: '/cambio-password', redirect: '/profilo' },
  { path: '/profilo',         name: 'Profilo',        component: () => import('../views/ProfiloUtenteView.vue') },
  { path: '/credits',         name: 'Credits',        component: () => import('../views/CreditsView.vue'),      meta: { public: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const sess = useSessionStore()
  if (!to.meta.public && !sess.isLoggedIn()) return { name: 'Login' }
})

export default router
