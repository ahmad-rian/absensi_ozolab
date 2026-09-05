import { usePage } from '@inertiajs/react';
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion';

interface FaqItem {
    question: string;
    answer: string;
}

export function FaqSection() {
    // Isinya dari config/seo.php, bukan ditulis ulang di sini: teks yang sama
    // dipakai skema FAQPage yang dibaca mesin pencari dan ringkasan AI. Dua
    // salinan pasti menyimpang, dan yang menyimpang diam-diam adalah yang
    // dibaca mesin.
    const { faqs = [] } = usePage().props as unknown as { faqs?: FaqItem[] };

    const leftColumn = faqs.slice(0, 3);
    const rightColumn = faqs.slice(3);

    return (
        <section id="faq" className="bg-slate-50/80 py-24 sm:py-32 lg:py-40 dark:bg-slate-900/40">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="text-center animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Pertanyaan yang Sering Diajukan
                    </h2>
                    <p className="text-muted-foreground mx-auto mt-4 max-w-2xl text-lg">
                        Temukan jawaban untuk pertanyaan umum seputar sistem absensi digital kami.
                    </p>
                </div>

                <div className="mt-12 grid gap-x-12 gap-y-0 md:grid-cols-2">
                    <div>
                        <Accordion type="single" collapsible>
                            {leftColumn.map((faq, index) => (
                                <div key={index}>
                                    <AccordionItem value={`left-${index}`}>
                                        <AccordionTrigger className="text-left text-sm font-semibold">
                                            {faq.question}
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <p className="text-muted-foreground leading-relaxed">
                                                {faq.answer}
                                            </p>
                                        </AccordionContent>
                                    </AccordionItem>
                                </div>
                            ))}
                        </Accordion>
                    </div>
                    <div>
                        <Accordion type="single" collapsible>
                            {rightColumn.map((faq, index) => (
                                <div key={index}>
                                    <AccordionItem value={`right-${index}`}>
                                        <AccordionTrigger className="text-left text-sm font-semibold">
                                            {faq.question}
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <p className="text-muted-foreground leading-relaxed">
                                                {faq.answer}
                                            </p>
                                        </AccordionContent>
                                    </AccordionItem>
                                </div>
                            ))}
                        </Accordion>
                    </div>
                </div>
            </div>
        </section>
    );
}
