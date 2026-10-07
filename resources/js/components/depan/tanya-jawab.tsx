import { usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Muncul } from '@/components/depan/muncul';

type Tanya = { question: string; answer: string };

/**
 * Isinya dari config/seo.php: teks yang sama dipakai skema FAQPage untuk
 * mesin pencari. Memakai <details> bawaan browser — terbuka tanpa JS dan
 * terbaca pembaca layar apa adanya.
 */
export function TanyaJawab() {
    const { faqs = [] } = usePage().props as unknown as { faqs?: Tanya[] };

    return (
        <section id="faq" className="scroll-mt-24 px-5 pb-24 sm:pb-32">
            <Muncul
                as="h2"
                className="serif text-center text-[clamp(2rem,4.2vw,3.1rem)] leading-[1.1]"
            >
                Yang sering <em>ditanyakan</em>
            </Muncul>
            <div className="mx-auto mt-12 max-w-2xl space-y-3">
                {faqs.map((faq, i) => (
                    <Muncul key={faq.question} tunda={i * 60}>
                        <details className="group rounded-2xl bg-[var(--kartu)] px-6 py-5 shadow-[0_1px_0_var(--garis)]">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-6 font-semibold [&::-webkit-details-marker]:hidden">
                                {faq.question}
                                <Plus
                                    className="size-5 shrink-0 transition group-open:rotate-45"
                                    strokeWidth={1.6}
                                    aria-hidden="true"
                                />
                            </summary>
                            <p className="mt-3 leading-relaxed text-[var(--tinta-2)]">
                                {faq.answer}
                            </p>
                        </details>
                    </Muncul>
                ))}
            </div>
        </section>
    );
}
