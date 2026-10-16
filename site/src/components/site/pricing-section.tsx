import { Check, Scale, Tag } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const INCLUDED = [
  "Installable plugin ZIP plus the complete, editable source (GPL-2.0-or-later)",
  "12 months of updates & support included",
  "Full documentation, from price books to the buyer portal",
  "All 3 PDF templates: Trade Table, Visual Catalog, Technical Sheet",
  "Unlimited DealPackets, price books and buyers",
  "No per-order fees · no subscription · no telemetry",
] as const

export function PricingSection() {
  return (
    <section
      id="pricing"
      aria-labelledby="pricing-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-gradient-to-b from-stone-50 to-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="pricing-heading"
          eyebrow="Pricing"
          title="One-time. Self-hosted. Yours."
          intro="Buy it once, run it on your server, keep the source. The launch price is an experiment in honest pricing — everything is in the box."
        />

        <div className="mx-auto grid max-w-4xl gap-4 md:gap-6 lg:grid-cols-2">
          {/* Primary pricing card */}
          <Reveal className="lg:col-span-2">
            <Card className="relative gap-0 overflow-hidden border-2 border-teal-700 py-0 shadow-lg shadow-teal-900/10">
              <div className="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-teal-700 via-teal-600 to-amber-500" />
              <CardHeader className="pt-7 [.border-b]:pb-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <CardTitle className="text-xl tracking-tight text-stone-900">
                    TradeSheet Studio
                  </CardTitle>
                  <Badge className="bg-amber-500 text-amber-950 hover:bg-amber-500">
                    Launch price
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="flex flex-col gap-6">
                <div className="flex flex-wrap items-end gap-x-4 gap-y-2">
                  <p className="text-5xl font-semibold tracking-tight text-stone-900">
                    $49
                  </p>
                  <p className="pb-1.5 text-sm text-stone-500">
                    <span className="text-lg font-medium text-stone-400 line-through">
                      $79
                    </span>{" "}
                    regular price after the launch window
                  </p>
                </div>
                <p className="-mt-3 text-sm font-medium text-teal-800">
                  One-time payment — self-hosted on your WooCommerce store.
                </p>

                <Separator className="bg-stone-200" />

                <ul className="flex flex-col gap-3">
                  {INCLUDED.map((item) => (
                    <li
                      key={item}
                      className="flex items-start gap-2.5 text-sm leading-relaxed text-stone-700"
                    >
                      <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-teal-100">
                        <Check className="size-3 text-teal-800" aria-hidden="true" />
                      </span>
                      {item}
                    </li>
                  ))}
                </ul>
              </CardContent>
              <CardFooter className="flex-col items-start gap-3 pb-7 [.border-t]:pt-6">
                <Button
                  disabled
                  size="lg"
                  className="h-11 w-full bg-teal-700 px-6 text-white opacity-60 sm:w-auto"
                  aria-describedby="pricing-cta-note"
                >
                  Available at launch
                </Button>
                <p id="pricing-cta-note" className="text-xs text-stone-500">
                  Checkout activates only when the product ships publicly.
                </p>
              </CardFooter>
            </Card>
          </Reveal>

          {/* Secondary card: GPL */}
          <Reveal delay={0.05} className="h-full">
            <Card className="h-full gap-0 border-stone-200 py-0">
              <CardContent className="flex flex-col gap-3 p-6">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-stone-50">
                  <Scale className="size-5 text-stone-700" aria-hidden="true" />
                </span>
                <h3 className="text-base font-semibold tracking-tight text-stone-900">
                  What GPL means for you
                </h3>
                <p className="text-sm leading-relaxed text-stone-600">
                  The plugin is licensed GPL-2.0-or-later: your freedoms to run,
                  modify, audit and redistribute it remain intact. What you&apos;re
                  buying is convenience — packaged software, documentation,
                  updates and support.
                </p>
              </CardContent>
            </Card>
          </Reveal>

          {/* Secondary card: honest pricing */}
          <Reveal delay={0.1} className="h-full">
            <Card className="h-full gap-0 border-stone-200 py-0">
              <CardContent className="flex flex-col gap-3 p-6">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50">
                  <Tag className="size-5 text-amber-700" aria-hidden="true" />
                </span>
                <h3 className="text-base font-semibold tracking-tight text-stone-900">
                  Honest pricing note
                </h3>
                <p className="text-sm leading-relaxed text-stone-600">
                  Launch pricing is an experiment; the regular price is planned
                  at $79. No hidden tiers — every feature is in the box.
                </p>
              </CardContent>
            </Card>
          </Reveal>
        </div>
      </div>
    </section>
  )
}
