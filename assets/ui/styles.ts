// Classes Tailwind partagées : un seul endroit pour l'allure des boutons et des champs.

export const button = {
  primary:
    'inline-flex items-center gap-2 rounded-lg bg-brique px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brique-fonce focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brique disabled:opacity-50',
  secondary:
    'inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-stone-700 ring-1 ring-stone-300 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-brique disabled:opacity-50 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-800',
  ghost:
    'inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-stone-600 hover:bg-stone-200 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-brique disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
  danger:
    'inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-rose-600 disabled:opacity-40 dark:text-rose-300 dark:hover:bg-rose-950',
}

export const field =
  'w-full rounded-lg border-0 bg-white px-3 py-2 text-stone-900 ring-1 ring-stone-300 placeholder:text-stone-400 focus:ring-2 focus:ring-brique focus:outline-none disabled:bg-stone-100 disabled:text-stone-500 dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700 dark:disabled:bg-slate-800'

export const card = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200 dark:bg-slate-900 dark:ring-slate-800'
