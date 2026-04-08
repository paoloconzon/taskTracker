// ============================================================
// api/index.js  –  Comunicazione con il backend PHP
// ============================================================
import { useSessionStore } from '../stores/session.js'

const API_URL = (import.meta.env.VITE_API_BASE_URL ?? '/api') + '/index.php'

async function call(func, data = {}, includeSession = true) {
  const payload = { func }

  if (includeSession && func !== 'login') {
    const sess = useSessionStore()
    payload.sessionId = sess.sessionId
  }

  // Per get/put/del i dati vanno dentro payload.data
  // Per login i dati sono payload.data
  payload.data = data

  const resp = await fetch(API_URL, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload),
  })

  if (!resp.ok) throw new Error(`HTTP ${resp.status}`)

  const json = await resp.json()
  if (json.result > 0) throw new Error(json.message || 'Errore server')
  return json  // { result, message, data }
}

// ---- AUTH -------------------------------------------------------
export const apiLogin  = (user, pwd)    => call('login',  { user, pwd }, false)
export const apiLogout = ()             => call('logout', {})

// ---- GET --------------------------------------------------------
export const apiGetArgomenti = (filtro = {}) =>
  call('get', { tab: 'argomenti', filtro })

export const apiGetAzioni = () =>
  call('get', { tab: 'azioni' })

export const apiGetTask = (filtro = {}) =>
  call('get', { tab: 'task', filtro })

export const apiGetUtenti = () =>
  call('get', { tab: 'utenti', filtro: {} })

export const apiGetTaskLog = (filtro = {}) =>
  call('get', { tab: 'task_log', filtro })

export const apiGetTaskAttivo = () =>
  call('get', { tab: 'task_attivo' })

// ---- PUT --------------------------------------------------------
export const apiPutArgomento = (valori) =>
  call('put', { tab: 'argomenti', valori })

export const apiPutAzione = (valori) =>
  call('put', { tab: 'azione', valori })

export const apiPutTask = (valori) =>
  call('put', { tab: 'task', valori })

export const apiPutTaskLog = (valori) =>
  call('put', { tab: 'task_log', valori })

// ---- DEL --------------------------------------------------------
export const apiDelArgomento = (id) =>
  call('del', { tab: 'argomenti', id })

export const apiDelAzione   = (id) =>
  call('del', { tab: 'azione',    id })

export const apiDelTaskLog  = (id) =>
  call('del', { tab: 'task_log',  id })

// ---- MANTIS EXPORT ----------------------------------------------
export const apiGetMantisExport     = (filtro)  => call('get_mantis_export',     { filtro })
export const apiSetEsportatoMantis  = (idsLog)  => call('set_esportato_mantis',  { idsLog })
export const apiMantisImport        = (righe)   => call('mantis_import',         { righe })

// ---- PROFILO UTENTE ---------------------------------------------
export const apiGetProfilo  = ()      => call('get_profilo',  {})
export const apiSaveProfilo = (data)  => call('save_profilo', data)

// ---- CAMBIO PASSWORD --------------------------------------------
export const apiCambioPassword = (vecchia, nuova) =>
  call('cambio_password', { vecchia, nuova })

// ---- ACTIONS ----------------------------------------------------
export const apiPausa       = ()         => call('action', { action: 'pausa' })
export const apiRiprendi    = (idTask)   => call('action', { action: 'riprendi',    idTask })
export const apiChiudiTask  = (idTask)   => call('action', { action: 'chiudi_task', idTask })
export const apiFermaLog    = ()         => call('action', { action: 'ferma_log' })
