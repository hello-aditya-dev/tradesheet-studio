import {
  BookOpen,
  FileText,
  Gauge,
  Layers,
  Scale,
  ShieldCheck,
  ShoppingCart,
  Table2,
} from "lucide-react"

import { Card, CardContent } from "@/components/ui/card"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const FEATURES = [
  {
    icon: Layers,
    title: "DealPackets",
    body: "Versioned, buyer-specific offers with a full lifecycle: draft → issued → submitted → approved → accepted → ordered. Nothing in between is improvised.",
  },
  {
    icon: BookOpen,
    title: "Wholesale price books",
    body: "Fixed price overrides, percentage discounts, quantity breaks, minimum quantities — assigned per customer or customer group.",
  },
  {
    icon: FileText,
    title: "PDF catalog engine",
    body: "Real PDFs from an embedded renderer: Trade Table, Visual Catalog and Technical Sheet templates. A4/Letter, your logo, pagination, Unicode currency symbols.",
  },
  {
    icon: Table2,
    title: "CSV order sheets",
    body: "Durable row IDs, variation-aware column mapping and server-side validation. Exports are sanitized against spreadsheet-formula injection.",
  },
  {
    icon: ShieldCheck,
    title: "Protected buyer portal",
    body: "Token-gated, no-index, no-cache pages hosted on the merchant's own site — quantities, quote status and authorized files in one place.",
  },
  {
    icon: Scale,
    title: "Quote integrity engine",
    body: "Detects changed prices, removed products, SKU drift, quantity-rule violations and expired offers — before anything becomes an order.",
  },
  {
    icon: ShoppingCart,
    title: "Idempotent order bridge",
    body: "Accepted quotes convert to WooCommerce orders at negotiated line prices. Concurrent or duplicate submissions cannot create two orders.",
  },
  {
    icon: Gauge,
    title: "Built for WooCommerce scale",
    body: "High-Performance Order Storage compatible, background processing via Action Scheduler, and WooCommerce CRUD throughout.",
  },
] as const

export function FeaturesSection() {
  return (
    <section
      id="features"
      aria-labelledby="features-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="features-heading"
          eyebrow="Features"
          title="Everything the deal needs — in one plugin."
          intro="TradeSheet Studio is built around the lifecycle of a wholesale offer, not around documents. Each capability exists to keep the chain from catalog to order honest."
        />

        <div className="grid gap-4 sm:grid-cols-2 md:gap-6 lg:grid-cols-4">
          {FEATURES.map((feature, index) => (
            <Reveal key={feature.title} delay={(index % 4) * 0.05} className="h-full">
              <Card className="h-full gap-0 border-stone-200 py-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md hover:shadow-stone-200/60">
                <CardContent className="flex flex-col gap-3 p-5 md:p-6">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-teal-700 text-white">
                    <feature.icon className="size-5" aria-hidden="true" />
                  </span>
                  <h3 className="text-base font-semibold tracking-tight text-stone-900">
                    {feature.title}
                  </h3>
                  <p className="text-sm leading-relaxed text-stone-600">
                    {feature.body}
                  </p>
                </CardContent>
              </Card>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
