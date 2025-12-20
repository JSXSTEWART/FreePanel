import tsParser from "@typescript-eslint/parser";

export default [
  {
    ignores: ["dist", ".vite", "build", "node_modules", "coverage"],
  },
  {
    files: ["**/*.{js,jsx,ts,tsx}"],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2020,
        sourceType: "module",
        ecmaFeatures: {
          jsx: true,
        },
      },
    },
  },
];
