// stores/session.js
import { defineStore } from 'pinia'
import { ref }         from 'vue'

export const useSessionStore = defineStore('session', () => {
  const sessionId   = ref(localStorage.getItem('sessionId')   || '')
  const idUtente    = ref(Number(localStorage.getItem('idUtente')) || 0)
  const user        = ref(localStorage.getItem('user')        || '')
  const gruppo      = ref(localStorage.getItem('gruppo')      || '')
  const descrizione = ref(localStorage.getItem('descrizione') || '')

  function setSession(data) {
    sessionId.value   = data.sessionId
    idUtente.value    = data.idUtente
    user.value        = data.user        || ''
    gruppo.value      = data.gruppo      || ''
    descrizione.value = data.descrizione || ''
    localStorage.setItem('sessionId',   data.sessionId)
    localStorage.setItem('idUtente',    data.idUtente)
    localStorage.setItem('user',        data.user        || '')
    localStorage.setItem('gruppo',      data.gruppo      || '')
    localStorage.setItem('descrizione', data.descrizione || '')
  }

  function clearSession() {
    sessionId.value   = ''
    idUtente.value    = 0
    user.value        = ''
    gruppo.value      = ''
    descrizione.value = ''
    localStorage.removeItem('sessionId')
    localStorage.removeItem('idUtente')
    localStorage.removeItem('user')
    localStorage.removeItem('gruppo')
    localStorage.removeItem('descrizione')
  }

  const isLoggedIn = () => !!sessionId.value
  const isAdmin    = () => gruppo.value === 'admin'

  return { sessionId, idUtente, user, gruppo, descrizione, setSession, clearSession, isLoggedIn, isAdmin }
})
