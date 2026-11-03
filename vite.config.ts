import { defineConfig } from "vite";
import { tanstackStart } from "@tanstack/react-start/plugin/vite";
import viteReact from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import tsConfigPaths from "vite-tsconfig-paths";
import { nitro } from "nitro/vite";

// Plugin order matters: viteReact must come after tanstackStart.
export default defineConfig({
  plugins: [
    tsConfigPaths(),
    // Use src/server.ts (our SSR error wrapper) as the server entry.
    tanstackStart({ server: { entry: "server" } }),
    // Set the deployment target (e.g. NITRO_PRESET=vercel) when building for your host.
    nitro(),
    viteReact(),
    tailwindcss(),
  ],
  resolve: {
    dedupe: ["react", "react-dom"],
  },
});
