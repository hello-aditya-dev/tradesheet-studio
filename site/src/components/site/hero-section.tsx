import Link from "next/link"
import { ArrowRight, CloudOff, FileCode2, HandCoins, Server } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Reveal } from "@/components/site/reveal"
import { PriceSheetMock } from "@/components/site/price-sheet-mock"

const TRUST_POINTS = [
  { icon: Server, label: "Self-hosted" },
  { icon: CloudOff, label: "No SaaS" },
  { icon: HandCoins, label: "No per-order fees" },
  { icon: FileCode2, label: "GPL-2.0-or-later source included" },
] as const

export function HeroSection() {
  return (
    <section
      id="top"
      aria-labelledby="hero-heading"
      className="relative overflow-hidden border-b border-stone-200/70 bg-gradient-to-b from-teal-50/70 via-white to-white"
    >
      {/* subtle sheet-grid backdrop */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,rgba(120,113,108,0.07)_1px,transparent_1px),linear-gradient(to_bottom,rgba(120,113,108,0.07)_1px,transparent_1px)] [background-size:44px_44px]"
      />
      <div className="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 md:py-24 lg:grid-cols-2 lg:gap-16 lg:px-8">
        <div className="flex min-w-0 flex-col items-start gap-6">
          <Reveal>
            <Badge
              variant="outline"
              className="border-teal-200 bg-teal-50 px-3 py-1 text-xs font-medium text-teal-800"
            >
              Wholesale plugin for WooCommerce
            </Badge>
          </Reveal>

          <Reveal delay={0.05}>
            <h1
              id="hero-heading"
              className="text-4xl font-semibold tracking-tight text-stone-900 text-balance sm:text-5xl sm:leading-[1.12]"
            >
              The wholesale catalog that{" "}
              <span className="text-teal-700 underline decoration-amber-500 decoration-[3px] underline-offset-[6px]">
                becomes an order
              </span>
              .
            </h1>
          </Reveal>

          <Reveal delay={0.1}>
            <p className="max-w-xl text-base leading-relaxed text-stone-600 text-pretty md:text-lg">
              TradeSheet Studio turns your WooCommerce catalog into{" "}
              <strong className="font-semibold text-stone-800">
                DealPackets
              </strong>{" "}
              — versioned, buyer-specific offers where the PDF, the CSV order
              sheet, the buyer portal and the final order all stay anchored to
              one immutable commercial snapshot.
            </p>
          </Reveal>

          <Reveal delay={0.15} className="flex flex-wrap gap-3">
            <Button
              asChild
              size="lg"
              className="h-11 bg-teal-700 px-6 text-white hover:bg-teal-800"
            >
              <Link href="#dealpackets">
                See the workflow
                <ArrowRight aria-hidden="true" />
              </Link>
            </Button>
            <Button
              asChild
              size="lg"
              variant="outline"
              className="h-11 border-stone-300 px-6 hover:bg-stone-100 hover:text-stone-900"
            >
              <Link href="#demo">Try the interactive demo</Link>
            </Button>
          </Reveal>

          <Reveal delay={0.2}>
            <ul className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-stone-500">
              {TRUST_POINTS.map(({ icon: Icon, label }) => (
                <li key={label} className="flex items-center gap-1.5">
                  <Icon
                    className="size-4 text-teal-700"
                    aria-hidden="true"
                  />
                  {label}
                </li>
              ))}
            </ul>
          </Reveal>
        </div>

        <Reveal delay={0.15} className="relative min-w-0 w-full">
          <PriceSheetMock className="relative mx-auto w-full max-w-lg rotate-[0.6deg] transition-transform duration-300 hover:rotate-0 lg:rotate-[1deg]" />
        </Reveal>
      </div>
    </section>
  )
}
