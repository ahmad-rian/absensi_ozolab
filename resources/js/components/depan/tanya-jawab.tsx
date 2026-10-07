import { usePage } from '@inertiajs/react';
import { Muncul } from '@/components/depan/muncul';

type Tanya = { question: string; answer: string };

/**
 * Isinya dari config/seo.php: teks yang sama dipakai skema FAQPage untuk
 * mesin pencari. Memakai <details> bawaan browser — bisa dibuka tanpa JS
 * tambahan dan terbaca pembaca layar apa adanya.
 */
export function TanyaJawab() {
    const { faqs = [] } = usePage().props as unknown as { faqs?: Tanya[] };

    return (
        <section
            id="faq"
            className="scroll-mt-20 border-t border-[var(--garis)] py-20 lg:py-28"
        >
            <div className="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
                <div>
                    <Muncul as="p" className="kode-tepi">
                        Tanya jawab
                    </Muncul>
                    <Muncul
                        as="h2"
                        tunda={100}
                        className="tampil mt-4 text-[clamp(1.7rem,3vw,2.4rem)] leading-tight font-extrabold"
                    >
                        Yang sering ditanyakan orang tua dan sekolah.
                    </Muncul>
                </div>
                <div className="divide-y divide-[var(--garis)] border-y border-[var(--garis)]">
                    {faqs.map((faq, i) => (
                        <Muncul key={faq.question} tunda={i * 70}>
                            <details className="group py-5">
                                <summary className="flex cursor-pointer list-none items-start justify-between gap-6 font-semibold [&::-webkit-details-marker]:hidden">
                                    {faq.question}
                                    <span
                                        className="data mt-0.5 text-[var(--biru)] transition group-open:rotate-45"
                                        aria-hidden="true"
                                    >
                                        +
                                    </span>
                                </summary>
                                <p className="mt-3 max-w-[60ch] leading-relaxed text-[var(--tinta-2)]">
                                    {faq.answer}
                                </p>
                            </details>
                        </Muncul>
                    ))}
                </div>
            </div>
        </section>
    );
}
