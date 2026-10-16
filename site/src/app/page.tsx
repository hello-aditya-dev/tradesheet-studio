import { SiteHeader } from "@/components/site/site-header"
import { SiteFooter } from "@/components/site/site-footer"
import { HeroSection } from "@/components/site/hero-section"
import { ProblemSection } from "@/components/site/problem-section"
import { DealPacketSection } from "@/components/site/dealpacket-section"
import { FeaturesSection } from "@/components/site/features-section"
import { DocumentsSection } from "@/components/site/documents-section"
import { CsvSection } from "@/components/site/csv-section"
import { PortalSection } from "@/components/site/portal-section"
import { IntegritySection } from "@/components/site/integrity-section"
import { DemoSection } from "@/components/site/demo-section"
import { CompatibilitySection } from "@/components/site/compatibility-section"
import { PricingSection } from "@/components/site/pricing-section"
import { FaqSection } from "@/components/site/faq-section"
import { LegalSection } from "@/components/site/legal-section"

export default function Home() {
  return (
    <div className="flex min-h-screen flex-col bg-white text-stone-900">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[60] focus:rounded-md focus:bg-teal-700 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
      >
        Skip to content
      </a>

      <SiteHeader />

      <main id="main" className="flex-1">
        <HeroSection />
        <ProblemSection />
        <DealPacketSection />
        <FeaturesSection />
        <DocumentsSection />
        <CsvSection />
        <PortalSection />
        <IntegritySection />
        <DemoSection />
        <CompatibilitySection />
        <PricingSection />
        <FaqSection />
        <LegalSection />
      </main>

      <SiteFooter />
    </div>
  )
}
