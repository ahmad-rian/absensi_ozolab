import { Check } from 'lucide-react';

export type WizardStep = { id: number; title: string };

/** Emerald-toned horizontal step indicator for the card wizards. */
export function StepProgress({ steps, current }: { steps: WizardStep[]; current: number }) {
    return (
        <div className="flex items-center">
            {steps.map((s, i) => {
                const done = s.id < current;
                const active = s.id === current;

                return (
                    <div key={s.id} className="flex flex-1 items-center last:flex-none">
                        <div className="flex flex-col items-center">
                            <span
                                className={`flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold transition ${
                                    done
                                        ? 'bg-emerald-600 text-white'
                                        : active
                                          ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/25'
                                          : 'bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'
                                }`}
                            >
                                {done ? <Check className="size-4" /> : s.id}
                            </span>
                            <span
                                className={`mt-1 hidden max-w-[80px] text-center text-[10px] leading-tight sm:block ${
                                    active ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'
                                }`}
                            >
                                {s.title}
                            </span>
                        </div>
                        {i < steps.length - 1 && (
                            <div className={`mx-1 h-0.5 flex-1 rounded ${done ? 'bg-emerald-600' : 'bg-zinc-200 dark:bg-zinc-800'}`} />
                        )}
                    </div>
                );
            })}
        </div>
    );
}
