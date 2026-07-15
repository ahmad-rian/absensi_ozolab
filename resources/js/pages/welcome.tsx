import { Head } from '@inertiajs/react';
import { CtaBanner } from '@/components/welcome/cta-banner';
import { DashboardPreview } from '@/components/welcome/dashboard-preview';
import { FaqSection } from '@/components/welcome/faq-section';
import { FeatureGrid } from '@/components/welcome/feature-grid';
import { Footer } from '@/components/welcome/footer';
import { HeroSection } from '@/components/welcome/hero-section';
import { HowItWorks } from '@/components/welcome/how-it-works';
import { InstitutionTypesGrid } from '@/components/welcome/institution-types-grid';
import { LogoCloud } from '@/components/welcome/logo-cloud';
import { MultiTenantSection } from '@/components/welcome/multi-tenant-section';
import { Navbar } from '@/components/welcome/navbar';
import { PricingSection } from '@/components/welcome/pricing-section';
import { ScrollToTop } from '@/components/shared/scroll-to-top';
import { StatsStrip } from '@/components/welcome/stats-strip';
import { TestimonialSection } from '@/components/welcome/testimonial-section';
import { WhatsappPreview } from '@/components/welcome/whatsapp-preview';
import { useLenis } from '@/hooks/use-lenis';

export default function Welcome() {
    useLenis();

    return (
        <>
            <Head title="Platform Absensi Multi-Sekolah" />
            <Navbar />
            <ScrollToTop />

            <main>
                <HeroSection />
                {/* <LogoCloud />
                <StatsStrip /> */}
                <InstitutionTypesGrid />
                <FeatureGrid />
                <HowItWorks />
                {/* <MultiTenantSection /> */}
                <DashboardPreview />
                <WhatsappPreview />
                {/* <PricingSection /> */}
                <TestimonialSection />
                <FaqSection />
                <CtaBanner />
            </main>

            <Footer />
        </>
    );
}
