import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { FlatTopic } from './guide-registry';

/**
 * Daftar isi bernomor.
 *
 * Nomornya diberikan SETELAH penyaringan role, jadi "Langkah 3 dari 12" selalu
 * cocok dengan apa yang benar-benar terlihat pembacanya — bukan nomor topik
 * yang sebagian tersembunyi.
 */
export function GuideToc({
    topics,
    activeId,
    onSelect,
}: {
    topics: FlatTopic[];
    activeId: string;
    onSelect: (id: string) => void;
}) {
    let lastChapter = '';

    return (
        <Card className="lg:sticky lg:top-6">
            <CardHeader>
                <CardTitle className="text-base">Daftar Isi</CardTitle>
            </CardHeader>
            <CardContent className="max-h-[calc(100vh-16rem)] overflow-y-auto">
                <nav className="flex flex-col gap-0.5">
                    {topics.map((topic) => {
                        const active = topic.id === activeId;
                        const newChapter = topic.chapterTitle !== lastChapter;
                        lastChapter = topic.chapterTitle;

                        return (
                            <div key={topic.id}>
                                {newChapter && (
                                    <p className="text-muted-foreground mt-3 mb-1.5 px-2 text-[11px] font-semibold tracking-wider uppercase first:mt-0">
                                        {topic.chapterTitle}
                                    </p>
                                )}
                                <button
                                    type="button"
                                    onClick={() => onSelect(topic.id)}
                                    aria-current={active ? 'step' : undefined}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm transition-colors',
                                        active
                                            ? 'bg-primary/10 text-foreground font-medium'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                            active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {topic.step}
                                    </span>
                                    <span className="leading-snug">{topic.title}</span>
                                </button>
                            </div>
                        );
                    })}
                </nav>
            </CardContent>
        </Card>
    );
}
