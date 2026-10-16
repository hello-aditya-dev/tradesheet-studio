"use client"

import * as React from "react"
import { Check, FileText, Images, Ruler } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

function TemplateIncludes({ items }: { items: readonly string[] }) {
  return (
    <ul className="flex flex-col gap-3">
      {items.map((item) => (
        <li key={item} className="flex items-start gap-2.5 text-sm leading-relaxed text-stone-700">
          <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-teal-100">
            <Check className="size-3 text-teal-800" aria-hidden="true" />
          </span>
          {item}
        </li>
      ))}
    </ul>
  )
}

function PaperFrame({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="relative min-w-0">
      <div className="rounded-xl border border-stone-200 bg-white p-3 shadow-lg shadow-stone-900/5 sm:p-4">
        <div className="min-w-0 overflow-x-auto rounded-lg border border-stone-200">{children}</div>
      </div>
      <p className="mt-3 text-center text-xs text-stone-500">{label}</p>
    </div>
  )
}

function PaperFooter({ page }: { page: string }) {
  return (
    <div className="flex items-center justify-between border-t border-stone-200 px-4 py-2 text-[10px] text-stone-500">
      <span className="font-mono">TSS-P-1042 · v1</span>
      <span>{page}</span>
    </div>
  )
}

function TradeTablePreview() {
  return (
    <PaperFrame label="Stylized Trade Table preview — synthetic demo data">
      <div className="overflow-hidden rounded-lg">
        <div className="bg-teal-700 px-4 py-3 text-white">
          <p className="text-[13px] font-semibold tracking-tight">
            VOLT &amp; WIRE TRADING CO.
          </p>
          <p className="text-[10px] text-teal-100">
            Trade Table · Krishna Electricals · Distributor Tier A
          </p>
        </div>
        <div className="flex flex-wrap gap-x-3 gap-y-1 border-b border-stone-200 bg-stone-50 px-4 py-2 text-[10px] text-stone-600">
          <span>Valid until 14 Mar 2026</span>
          <span aria-hidden="true">·</span>
          <span>Prices in INR, excl. GST</span>
        </div>
        <table className="w-full text-left">
          <thead>
            <tr className="border-b border-stone-200 text-[9px] tracking-wide text-stone-500 uppercase">
              <th className="px-4 py-1.5 font-medium">SKU</th>
              <th className="py-1.5 font-medium">Item</th>
              <th className="py-1.5 font-medium">Var.</th>
              <th className="py-1.5 font-medium">Buyer price</th>
              <th className="py-1.5 font-medium">Min</th>
              <th className="px-4 py-1.5 font-medium">Qty breaks</th>
            </tr>
          </thead>
          <tbody className="text-[10px] text-stone-700">
            {[
              ["VW-LED-HB150", "LED High Bay 150W", "5000K", "₹4,450.00", "2", "10+ ₹4,180 · 50+ ₹3,950"],
              ["VW-LED-TP36", "LED Triproof Batten 36W", "4000K", "₹1,240.00", "1", "20+ ₹1,150"],
              ["VW-DB-8WAY", "Distribution Board 8-Way", "TPN IP65", "₹3,890.00", "1", "—"],
              ["VW-MCB-C32", "MCB C32 1P 10kA", "—", "₹412.00", "1", "50+ ₹385"],
              ["VW-CAB-2R5", "Copper Cable 2.5mm² 90m", "FR/PVC", "₹5,999.00", "1", "—"],
              ["VW-SKT-IEC32", "Industrial Socket 32A", "3P+E IP67", "₹760.00", "1", "25+ ₹705"],
            ].map((row) => (
              <tr key={row[0]} className="border-b border-stone-100 last:border-b-0">
                <td className="px-4 py-1.5 font-mono text-stone-500">{row[0]}</td>
                <td className="py-1.5">{row[1]}</td>
                <td className="py-1.5 text-stone-500">{row[2]}</td>
                <td className="py-1.5 font-mono font-semibold text-teal-800">{row[3]}</td>
                <td className="py-1.5 text-stone-500">{row[4]}</td>
                <td className="px-4 py-1.5 text-stone-500">{row[5]}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <PaperFooter page="Page 1 of 3" />
      </div>
    </PaperFrame>
  )
}

function VisualCatalogPreview() {
  return (
    <PaperFrame label="Stylized Visual Catalog preview — synthetic demo data">
      <div className="overflow-hidden rounded-lg">
        <div className="bg-teal-700 px-4 py-3 text-white">
          <p className="text-[13px] font-semibold tracking-tight">
            VOLT &amp; WIRE TRADING CO.
          </p>
          <p className="text-[10px] text-teal-100">
            Product Catalog · Krishna Electricals
          </p>
        </div>
        {/* hero product */}
        <div className="flex flex-col gap-3 border-b border-stone-200 p-4 sm:flex-row">
          <div
            aria-hidden="true"
            className="flex aspect-[4/3] w-full items-center justify-center rounded-md bg-gradient-to-br from-teal-100 to-teal-50 sm:w-1/2"
          >
            <svg viewBox="0 0 64 64" className="size-12 text-teal-700/60">
              <circle cx="32" cy="24" r="10" fill="none" stroke="currentColor" strokeWidth="3" />
              <path d="M32 34 v6 M26 40 h12 M28 46 h8" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
              <path d="M18 54 l4-8 M46 54 l-4-8" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
            </svg>
          </div>
          <div className="flex flex-col justify-center gap-1.5 sm:w-1/2">
            <p className="text-sm font-semibold text-stone-900">LED High Bay 150W</p>
            <p className="text-[10px] leading-relaxed text-stone-500">
              150W · 5000K · 120 lm/W · IP65 · surge 10 kV
            </p>
            <p className="font-mono text-sm font-semibold text-teal-800">₹4,450.00</p>
            <p className="text-[10px] text-stone-400">min 2 · 10+ at ₹4,180.00</p>
          </div>
        </div>
        {/* smaller products */}
        <div className="grid grid-cols-2 gap-3 p-4">
          {[
            ["LED Triproof Batten 36W", "36W · 4000K · IP69K", "₹1,240.00"],
            ["Distribution Board 8-Way", "TPN · IP65 · 415V", "₹3,890.00"],
          ].map(([name, spec, price]) => (
            <div key={name} className="flex flex-col gap-1.5">
              <div
                aria-hidden="true"
                className="flex aspect-[4/3] items-center justify-center rounded-md bg-stone-100"
              >
                <svg viewBox="0 0 64 64" className="size-8 text-stone-400">
                  <rect x="10" y="18" width="44" height="28" rx="4" fill="none" stroke="currentColor" strokeWidth="3" />
                  <path d="M20 32 h24" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                </svg>
              </div>
              <p className="text-xs font-semibold text-stone-900">{name}</p>
              <p className="text-[10px] text-stone-500">{spec}</p>
              <p className="font-mono text-xs font-semibold text-teal-800">{price}</p>
            </div>
          ))}
        </div>
        <PaperFooter page="Page 1 of 6" />
      </div>
    </PaperFrame>
  )
}

function TechnicalSheetPreview() {
  return (
    <PaperFrame label="Stylized Technical Sheet preview — synthetic demo data">
      <div className="overflow-hidden rounded-lg">
        <div className="bg-stone-800 px-4 py-3 text-white">
          <p className="text-[13px] font-semibold tracking-tight">
            TECHNICAL SHEET
          </p>
          <p className="font-mono text-[10px] text-stone-300">VW-LED-HB150 · Rev. 2</p>
        </div>
        <div className="flex flex-col gap-4 p-4 sm:flex-row">
          <div className="sm:w-1/2">
            <table className="w-full text-left">
              <tbody className="text-[10px]">
                {[
                  ["Rated power", "150 W"],
                  ["Input voltage", "100–277 V AC"],
                  ["Color temperature", "5000 K"],
                  ["Luminous flux", "22,500 lm"],
                  ["Ingress protection", "IP65"],
                  ["Lifetime (L80)", "≥ 50,000 h"],
                ].map(([k, v]) => (
                  <tr key={k} className="border-b border-stone-100 last:border-b-0">
                    <td className="w-1/2 py-1.5 text-stone-500">{k}</td>
                    <td className="py-1.5 font-medium text-stone-800">{v}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="mt-3 rounded-md border border-teal-200 bg-teal-50 px-3 py-2">
              <p className="text-[9px] tracking-wide text-teal-700 uppercase">
                Buyer price (packet v1)
              </p>
              <p className="font-mono text-sm font-semibold text-teal-800">
                ₹4,450.00
              </p>
            </div>
          </div>
          <div
            aria-hidden="true"
            className="flex items-center justify-center rounded-md border border-dashed border-stone-300 bg-stone-50 sm:w-1/2"
          >
            <svg viewBox="0 0 96 72" className="w-3/4 text-stone-400">
              <rect x="14" y="14" width="68" height="44" fill="none" stroke="currentColor" strokeWidth="2" />
              <path d="M14 14 L34 8 M82 14 L62 8 M14 58 L34 64 M82 58 L62 64" stroke="currentColor" strokeWidth="1.5" />
              <circle cx="48" cy="36" r="9" fill="none" stroke="currentColor" strokeWidth="2" />
              <path d="M48 27 v-6 M48 45 v6 M39 36 h-6 M57 36 h6" stroke="currentColor" strokeWidth="1.5" />
            </svg>
          </div>
        </div>
        <PaperFooter page="Sheet 1 of 2" />
      </div>
    </PaperFrame>
  )
}

export function DocumentsSection() {
  return (
    <section
      id="documents"
      aria-labelledby="documents-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-stone-50 py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="documents-heading"
          eyebrow="Documents"
          title="Three documents, one truth."
          intro="Pick the layout that matches how your industry buys. Every template is rendered from the same frozen packet snapshot — same SKUs, same negotiated prices, same validity — so the paper can never disagree with the portal or the order."
        />

        <Reveal>
          <Tabs defaultValue="trade-table" className="w-full">
            <TabsList className="mx-auto h-11 w-full max-w-xl border border-stone-200 bg-white p-1 sm:w-auto">
              <TabsTrigger
                value="trade-table"
                className="h-full flex-1 px-2 py-2 text-xs sm:px-3 sm:text-sm data-[state=active]:bg-teal-50 data-[state=active]:text-teal-900"
              >
                <FileText aria-hidden="true" />
                Trade Table
              </TabsTrigger>
              <TabsTrigger
                value="visual-catalog"
                className="h-full flex-1 px-2 py-2 text-xs sm:px-3 sm:text-sm data-[state=active]:bg-teal-50 data-[state=active]:text-teal-900"
              >
                <Images aria-hidden="true" />
                Visual Catalog
              </TabsTrigger>
              <TabsTrigger
                value="technical-sheet"
                className="h-full flex-1 px-2 py-2 text-xs sm:px-3 sm:text-sm data-[state=active]:bg-teal-50 data-[state=active]:text-teal-900"
              >
                <Ruler aria-hidden="true" />
                Technical Sheet
              </TabsTrigger>
            </TabsList>

            <TabsContent value="trade-table" className="mt-6 grid items-start gap-8 md:mt-8 lg:grid-cols-[1.25fr_1fr]">
              <TradeTablePreview />
              <div className="flex min-w-0 flex-col gap-4">
                <Badge
                  variant="outline"
                  className="w-fit border-teal-200 bg-teal-50 text-teal-800"
                >
                  Dense industrial price list
                </Badge>
                <p className="text-sm leading-relaxed text-stone-600">
                  Built for electrical &amp; hardware distributors who need many
                  SKUs per page: rows stay scannable, breaks and minimums stay
                  attached to the price they belong to.
                </p>
                <TemplateIncludes
                  items={[
                    "Columns for SKU, item, variation, buyer price, minimum quantity and quantity breaks",
                    "Your logo and contact block in the header",
                    "DealPacket ID, version and validity printed on every page",
                    "Currency symbol and tax context (incl./excl.) printed with prices",
                    "Page numbering for multi-page price lists",
                  ]}
                />
              </div>
            </TabsContent>

            <TabsContent value="visual-catalog" className="mt-6 grid items-start gap-8 md:mt-8 lg:grid-cols-[1.25fr_1fr]">
              <VisualCatalogPreview />
              <div className="flex min-w-0 flex-col gap-4">
                <Badge
                  variant="outline"
                  className="w-fit border-teal-200 bg-teal-50 text-teal-800"
                >
                  Editorial product presentation
                </Badge>
                <p className="text-sm leading-relaxed text-stone-600">
                  For merchants whose buyers choose with their eyes: product
                  imagery, names and short specs with the buyer's own pricing
                  underneath — still anchored to the same snapshot.
                </p>
                <TemplateIncludes
                  items={[
                    "Editorial layout with product imagery, names and short specs",
                    "Buyer-specific pricing under each product",
                    "Your logo and brand color in the header",
                    "Version and validity stated in the catalog footer",
                    "Currency and tax context on the pricing page, with page numbering",
                  ]}
                />
              </div>
            </TabsContent>

            <TabsContent value="technical-sheet" className="mt-6 grid items-start gap-8 md:mt-8 lg:grid-cols-[1.25fr_1fr]">
              <TechnicalSheetPreview />
              <div className="flex min-w-0 flex-col gap-4">
                <Badge
                  variant="outline"
                  className="w-fit border-teal-200 bg-teal-50 text-teal-800"
                >
                  Specification-driven layout
                </Badge>
                <p className="text-sm leading-relaxed text-stone-600">
                  Made for component manufacturers: parameters, tolerances and
                  drawings lead; commercial terms follow, tied to the packet
                  version so engineering and purchasing read the same numbers.
                </p>
                <TemplateIncludes
                  items={[
                    "Parameter/value tables with revision control",
                    "Space for technical drawings and regulatory marks",
                    "Buyer price block tied to the packet version",
                    "Validity and currency/tax context on the sheet",
                    "Logo and contact block, with page numbering",
                  ]}
                />
              </div>
            </TabsContent>
          </Tabs>
        </Reveal>
      </div>
    </section>
  )
}
