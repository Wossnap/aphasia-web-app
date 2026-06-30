# Project Guidelines

## Git commits
- Do NOT add any AI/Claude signature, attribution, or "Co-Authored-By" trailer to commit messages or PR bodies.
- Keep commit messages plain and descriptive.

## Front-end builds
- The front-end is built with Vite (`npm run build`). The compiled output lives in `public/build/` and IS committed to the repo.
- After changing any file under `resources/js/` or `resources/css/` (e.g. `.vue` components, styles), you MUST run `npm run build` and commit the regenerated `public/build/` assets together with the source change. Production serves the built bundle, so source-only changes will not take effect otherwise.
