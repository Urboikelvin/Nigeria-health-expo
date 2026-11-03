import { createFileRoute } from "@tanstack/react-router";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "Nigeria Health Expo 2027 — 26–28 April 2027, Lagos" },
      { name: "description", content: "Exhibition and conference connecting global innovation with Africa’s healthcare needs. 26–28 April 2027, Oriental Hotel, Victoria Island, Lagos." },
      { property: "og:title", content: "Nigeria Health Expo 2027" },
      { property: "og:description", content: "Exhibition and conference connecting global innovation with Africa’s healthcare needs. 26–28 April 2027, Oriental Hotel, Victoria Island, Lagos." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: Index,
});

function Index() {
  return (
    <iframe
      src="/expo.html"
      title="Nigeria Health Expo"
      className="block h-screen w-full border-0"
    />
  );
}
