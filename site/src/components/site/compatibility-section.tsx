import { BadgeCheck } from "lucide-react"

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const REQUIREMENTS = [
  {
    requirement: "WordPress",
    detail: "6.4 or later",
  },
  {
    requirement: "WooCommerce",
    detail: "8.0 or later — High-Performance Order Storage (HPOS) compatible",
  },
  {
    requirement: "PHP",
    detail: "8.1 or later (8.2+ recommended)",
  },
  {
    requirement: "Database",
    detail: "MySQL or MariaDB, per WordPress requirements",
  },
  {
    requirement: "Runtime dependencies",
    detail: "None — no Node.js, no Composer, no build step on your server",
  },
  {
    requirement: "Translations",
    detail: "Translatable via the standard WordPress i18n APIs",
  },
] as const

export function CompatibilitySection() {
  return (
    <section
      id="compatibility"
      aria-labelledby="compatibility-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="compatibility-heading"
          eyebrow="Compatibility"
          title="Runs where your store already runs."
          intro="A plugin, not a platform. If your WordPress + WooCommerce stack is healthy, TradeSheet Studio drops in without new infrastructure."
        />

        <Reveal className="mx-auto max-w-3xl">
          <div className="overflow-hidden rounded-2xl border border-stone-200">
            <Table>
              <TableHeader>
                <TableRow className="bg-stone-50 hover:bg-transparent">
                  <TableHead className="pl-5 text-stone-600">Requirement</TableHead>
                  <TableHead className="pr-5 text-stone-600">Detail</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {REQUIREMENTS.map((row) => (
                  <TableRow key={row.requirement} className="hover:bg-stone-50">
                    <TableCell className="pl-5 font-medium text-stone-900">
                      {row.requirement}
                    </TableCell>
                    <TableCell className="pr-5 text-stone-600">{row.detail}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <p className="mt-4 flex items-start gap-2 text-xs leading-relaxed text-stone-500">
            <BadgeCheck className="mt-0.5 size-3.5 shrink-0 text-teal-700" aria-hidden="true" />
            Detailed per-version test results are published in the product&apos;s
            compatibility matrix documentation.
          </p>
        </Reveal>
      </div>
    </section>
  )
}
