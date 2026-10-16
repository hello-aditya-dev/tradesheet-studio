import { GitBranch, Hourglass, ShieldCheck } from "lucide-react"

import { Card, CardContent } from "@/components/ui/card"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"
import { FlowDiagram } from "@/components/site/flow-diagram"

const PILLARS = [
  {
    icon: GitBranch,
    title: "Versioned, never rewritten",
    body: "A published DealPacket is read-only. Need different prices, extra lines, new validity? That's v2, v3… — every revision is a new, traceable version.",
  },
  {
    icon: ShieldCheck,
    title: "Checksummed",
    body: "Each snapshot carries a SHA-256 content checksum. Documents, portal pages and uploads are verified against it — silent tampering or drift becomes detectable.",
  },
  {
    icon: Hourglass,
    title: "Expiring by design",
    body: "Validity windows are enforced end-to-end. A stale offer can't quietly become an order — expired revisions are refused, not quietly honored.",
  },
] as const

export function DealPacketSection() {
  return (
    <section
      id="dealpackets"
      aria-labelledby="dealpackets-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-gradient-to-b from-white to-stone-50 py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="dealpackets-heading"
          eyebrow="DealPacket integrity"
          title="One snapshot. Four representations. Zero drift."
          intro="Every published DealPacket freezes an immutable commercial snapshot — products, variations, SKUs, negotiated prices, quantity rules, currency and validity — sealed with a SHA-256 content checksum. The PDF, the CSV order sheet, the buyer portal, every quote revision and the final order all reference that one version. Prices and terms can't silently drift between catalog, quote and order, because there is only ever one source of truth."
        />

        <Reveal className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-6 md:p-8">
          <FlowDiagram />
        </Reveal>

        <div className="mt-6 grid gap-4 md:mt-8 md:grid-cols-3 md:gap-6">
          {PILLARS.map((pillar, index) => (
            <Reveal key={pillar.title} delay={index * 0.05} className="h-full">
              <Card className="h-full gap-0 border-stone-200 py-0 transition-shadow duration-300 hover:shadow-md hover:shadow-stone-200/60">
                <CardContent className="flex flex-col gap-3 p-6">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-teal-200 bg-teal-50">
                    <pillar.icon className="size-5 text-teal-700" aria-hidden="true" />
                  </span>
                  <h3 className="text-base font-semibold tracking-tight text-stone-900">
                    {pillar.title}
                  </h3>
                  <p className="text-sm leading-relaxed text-stone-600">
                    {pillar.body}
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
