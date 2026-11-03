# Nigeria Health Expo 2027

Website for **Nigeria Health Expo 2027** — Connecting Global Innovation With Africa’s Healthcare Needs.

- **Dates:** 26–28 April 2027
- **Venue:** Oriental Hotel, Victoria Island, Lagos
- **Organised by:** Global Health Projects and Resources, in collaboration with the Healthcare Federation Nigeria

## Stack

TanStack Start (React 19, TypeScript), Tailwind CSS 4, Vite, Nitro. The site itself lives in
`public/expo.html` and is embedded by the index route (`src/routes/index.tsx`).

## Development

Requires a current Node.js LTS and [Bun](https://bun.sh) (or npm).

```sh
bun install
bun run dev        # start the dev server
bun run build      # production build
bun run test       # run tests
```

## Before launch

- Connect the registration and enquiry forms in `public/expo.html` to a form service (search for `TODO`).
- Add the social media URLs in the footer and remove the `hidden` attribute from the `.social` block.
- Replace the placeholder photos in `public/images/` with real event photography.
- Set the Nitro deployment preset for your host (see `vite.config.ts`).
