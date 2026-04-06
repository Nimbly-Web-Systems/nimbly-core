import theme from "./ext/tailwind.theme.js";
import typography from "@tailwindcss/typography";
import twElements from "tw-elements/dist/plugin.cjs";
import daisyui from "daisyui";

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: "class",
  content: [
    "./core/uri/**/*.tpl",
    "./core/uri/.modal/**/*.tpl",
    "./core/tpl/**/*.tpl",
    "./core/lib/**/*.tpl",
    "./core/modules/**/*.tpl",
    "./ext/uri/**/*.tpl",
    "./ext/uri/.modal/**/*.tpl",
    "./ext/lib/**/*.tpl",
    "./ext/tpl/**/*.tpl",
    "./ext/modules/**/*.tpl",
    "./node_modules/tw-elements/dist/js/**/*.js",
  ],
  theme,
  daisyui: {
    darkTheme: "light", // force dark mode to fall back to light (temporary fix)
    themes: [
      {
        light: {
          "primary":          "#0074D9",
          "primary-content":  "#ffffff",
          "secondary":        "#80baec",
          "secondary-content":"#ffffff",
          "accent":           "#0074D9",
          "accent-content":   "#ffffff",
          "neutral":          "#2a323c",
          "neutral-content":  "#ffffff",
          "base-100":         "#ffffff",
          "base-200":         "#f9fafb",
          "base-300":         "#e5e7eb",
          "base-content":     "#1f2937",
          "info":             "#3abff8",
          "success":          "#36d399",
          "warning":          "#fbbd23",
          "error":            "#f87272",
        },
      },
    ],
  },
  plugins: [typography, twElements, daisyui],
};
