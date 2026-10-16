import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"

const FAQS = [
  {
    q: "What do I need to run TradeSheet Studio?",
    a: "WordPress 6.4+, WooCommerce 8.0+, and PHP 8.1+. Nothing else — no Node.js, no Composer, no paid services. If your WooCommerce hosting is healthy, the plugin drops in.",
  },
  {
    q: "Does it work with High-Performance Order Storage?",
    a: "Yes. The plugin declares HPOS compatibility and interacts with orders exclusively through WooCommerce's CRUD order APIs, so it works on both traditional and HPOS order storage.",
  },
  {
    q: "Where does my data live?",
    a: "Entirely on your server, in your WordPress database. No telemetry, no analytics, and no third-party calls during normal operation. Your buyers' activity stays inside your install.",
  },
  {
    q: "What happens if a price changes mid-negotiation?",
    a: "The Quote Integrity Engine blocks silent substitution. It compares the quote request against the frozen packet snapshot and the live store, produces a discrepancy report, and requires an explicit approved revision that the buyer must accept before anything proceeds.",
  },
  {
    q: "How does payment work?",
    a: "Accepted quotes create a WooCommerce order with the negotiated line prices. Payment runs through your existing checkout and payment gateways — the plugin never charges anyone and has no payment handling of its own.",
  },
  {
    q: "Are CSV uploads safe?",
    a: "Uploads are size-limited and fully validated server-side. Prices found in uploaded files are never trusted — every price is re-resolved from the packet snapshot — and exported cells are sanitized against spreadsheet-formula injection.",
  },
  {
    q: "How are taxes handled?",
    a: "Through WooCommerce's native tax engine. Snapshots record whether prices are tax-inclusive or tax-exclusive so documents state their tax context. TradeSheet Studio is a quotation tool, not an accounting system.",
  },
  {
    q: "What rights do I get for $49?",
    a: "GPL-2.0-or-later: the freedom to run, study, modify and redistribute the software. Your purchase buys the packaged plugin, documentation, updates and support — the license itself guarantees your freedoms either way.",
  },
  {
    q: "What about refunds and support?",
    a: "A fair-use refund window and the support terms are published in the product's policy documents, which ship with every purchase — so you can read the exact terms before relying on them.",
  },
  {
    q: "Which product types are supported?",
    a: "Simple and variable products with individual variations. Subscriptions, bookings, and composite/bundle product types are explicitly out of scope for v1.",
  },
] as const

export function FaqSection() {
  return (
    <section
      id="faq"
      aria-labelledby="faq-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="faq-heading"
          eyebrow="FAQ"
          title="Straight answers, before you ask."
        />

        <Reveal className="mx-auto max-w-3xl">
          <Accordion
            type="single"
            collapsible
            className="rounded-2xl border border-stone-200 bg-white px-5 shadow-sm sm:px-6"
          >
            {FAQS.map((faq, index) => (
              <AccordionItem
                key={faq.q}
                value={`faq-${index}`}
                className="border-stone-200 last:border-b"
              >
                <AccordionTrigger className="py-4.5 text-left text-sm font-medium text-stone-900 hover:text-teal-800 hover:no-underline sm:text-base">
                  {faq.q}
                </AccordionTrigger>
                <AccordionContent className="pb-5 text-sm leading-relaxed text-stone-600">
                  {faq.a}
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </Reveal>
      </div>
    </section>
  )
}
