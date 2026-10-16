import { LifeBuoy, ScrollText, ShieldCheck } from "lucide-react"

import { Card, CardContent } from "@/components/ui/card"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const LEGAL_CARDS = [
  {
    icon: ScrollText,
    title: "License",
    body: "GPL-2.0-or-later. The full license text ships with the product, and the complete source is included — nothing obfuscated.",
  },
  {
    icon: ShieldCheck,
    title: "Privacy",
    body: "No telemetry. Buyers' data — tokens, quote requests, uploads — stays in the merchant's WordPress, never on our servers.",
  },
  {
    icon: LifeBuoy,
    title: "Support & updates",
    body: "12 months of updates and support with purchase, then optional renewals — subject to the published policy documents.",
  },
] as const

export function LegalSection() {
  return (
    <section
      id="legal"
      aria-labelledby="legal-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-stone-50 py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="legal-heading"
          eyebrow="Legal"
          title="The fine print, minus the fine."
        />

        <div className="grid gap-4 md:grid-cols-3 md:gap-6">
          {LEGAL_CARDS.map((card, index) => (
            <Reveal key={card.title} delay={index * 0.05} className="h-full">
              <Card className="h-full gap-0 border-stone-200 bg-white py-0 transition-shadow duration-300 hover:shadow-md hover:shadow-stone-200/60">
                <CardContent className="flex flex-col gap-3 p-6">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-stone-50">
                    <card.icon className="size-5 text-stone-700" aria-hidden="true" />
                  </span>
                  <h3 className="text-base font-semibold tracking-tight text-stone-900">
                    {card.title}
                  </h3>
                  <p className="text-sm leading-relaxed text-stone-600">{card.body}</p>
                </CardContent>
              </Card>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
