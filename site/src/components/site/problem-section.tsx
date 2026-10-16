import {
  CalendarClock,
  CopyX,
  FileQuestion,
  MessagesSquare,
  Repeat,
  TrendingDown,
} from "lucide-react"

import { Card, CardContent } from "@/components/ui/card"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const PAINS = [
  {
    icon: TrendingDown,
    title: "Price drift",
    body: "The catalog you emailed last month no longer matches your store.",
  },
  {
    icon: Repeat,
    title: "Manual re-entry",
    body: "Rebuilding the buyer's returned sheet as an order, line by line.",
  },
  {
    icon: CopyX,
    title: "Duplicate orders",
    body: "A refreshed page or re-sent email becomes two orders.",
  },
  {
    icon: CalendarClock,
    title: "Stale offers",
    body: "No validity dates, no versions — buyers order expired prices.",
  },
  {
    icon: FileQuestion,
    title: "Unclear terms",
    body: "Minimums, quantity breaks and tax context lost in transit.",
  },
  {
    icon: MessagesSquare,
    title: "Disputes",
    body: "When numbers disagree, trust is what gets refunded.",
  },
] as const

export function ProblemSection() {
  return (
    <section
      id="problem"
      aria-labelledby="problem-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="problem-heading"
          eyebrow="The spreadsheet carousel"
          title="Wholesale quotes die in spreadsheets."
          intro="You export a price list, the buyer marks it up, emails it back, and someone retypes it into WooCommerce. Every lap around that carousel is another chance for the numbers to drift apart — and the buyer is the one who finds out."
        />

        <div className="grid gap-4 sm:grid-cols-2 md:gap-6 lg:grid-cols-3">
          {PAINS.map((pain, index) => (
            <Reveal key={pain.title} delay={index * 0.05} className="h-full">
              <Card className="h-full gap-0 border-stone-200 py-0 transition-shadow duration-300 hover:shadow-md hover:shadow-stone-200/60">
                <CardContent className="flex flex-col gap-3 p-6">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-stone-50">
                    <pain.icon className="size-5 text-stone-700" aria-hidden="true" />
                  </span>
                  <h3 className="text-base font-semibold tracking-tight text-stone-900">
                    {pain.title}
                  </h3>
                  <p className="text-sm leading-relaxed text-stone-600">
                    {pain.body}
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
