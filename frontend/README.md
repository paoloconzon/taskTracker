# Frontend – Task Tracker

Vedi il [README principale](../README.md) per la documentazione completa del progetto.

## Configurazione Vite

Prima di avviare, copia il file di ambiente:

```bash
cp .env.example .env.development
```

Nel file `vite.config.js` configura:

- `server.proxy['/api'].target` — indirizzo del backend PHP (es. `http://localhost`)
- `base` — path dove viene servita l'app (`./ ` per XAMPP, `/` per server standard)

## Licenza

[MIT](../LICENSE) — Copyright (c) 2026 Paolo Conzon

## Comandi

```bash
npm install       # installa le dipendenze
npm run dev       # avvia il server di sviluppo
npm run build     # compila per la produzione (output in dist/)
npm run preview   # anteprima del build
```
