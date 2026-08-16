# darink

Offline-first health tracking PWA. Built with SvelteKit + adapter-static.

## Modules

- **Check-in** — daily mood, energy, sleep, stress
- **Intake** — food, drink, portions, timing
- **Training** — strength, rings, HIIT, cardio, mobility
- **Signals** — sleep, skin, hair, genital tracking
- **Habits** — cold, sun, fasting, meditation, Wim Hof
- **Supplements** — stack, doses, timing, rotation
- **Experiments** — n=1 diary with hypothesis and results
- **Dashboard** — stats overview
- **Profile** — personal context and targets
- **Data** — export/import JSON, no server, no account

## Dev

```bash
npm install
npm run dev
```

## Build

```bash
npm run build
npm run preview
```

## Deploy

Push to `main` — GitHub Actions builds and deploys to GitHub Pages automatically.

## Rollback

```bash
git checkout v0-vanilla
```
