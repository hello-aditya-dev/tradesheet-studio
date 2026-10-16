import Link from "next/link"
import { ExternalLink } from "lucide-react"

import { LogoMark } from "@/components/site/logo"

const FOOTER_LINKS = [
  { href: "#problem", label: "Problem" },
  { href: "#dealpackets", label: "DealPackets" },
  { href: "#features", label: "Features" },
  { href: "#documents", label: "Documents" },
  { href: "#csv", label: "CSV round-trip" },
  { href: "#portal", label: "Buyer portal" },
  { href: "#integrity", label: "Quote integrity" },
  { href: "#demo", label: "Demo" },
  { href: "#compatibility", label: "Compatibility" },
  { href: "#pricing", label: "Pricing" },
  { href: "#faq", label: "FAQ" },
  { href: "#legal", label: "Legal" },
] as const

export function SiteFooter() {
  return (
    <footer className="mt-auto bg-stone-900 text-stone-300">
      <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="flex flex-col gap-10 md:flex-row md:justify-between">
          <div className="max-w-sm">
            <div className="flex items-center gap-2.5">
              <LogoMark className="size-8" />
              <p className="text-lg font-semibold tracking-tight text-white">
                TradeSheet Studio
                <span className="block text-sm font-normal text-stone-400">
                  for WooCommerce
                </span>
              </p>
            </div>
            <p className="mt-4 text-sm leading-relaxed text-stone-400">
              The wholesale catalog that becomes an order. DealPackets keep your
              PDF, CSV order sheet, buyer portal and final WooCommerce order
              anchored to one immutable commercial snapshot.
            </p>
            <p className="mt-4 text-sm">
              <span className="text-stone-400">Built by </span>
              <a
                href="https://dev-aditya.com"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 font-medium text-amber-400 underline-offset-4 transition-colors hover:text-amber-300 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
              >
                Aditya — dev-aditya.com
                <ExternalLink className="size-3.5" aria-hidden="true" />
              </a>
            </p>
          </div>

          <nav aria-label="Footer" className="grid grid-cols-2 gap-x-10 gap-y-2 sm:grid-cols-3">
            {FOOTER_LINKS.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className="min-h-9 py-1.5 text-sm text-stone-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
              >
                {link.label}
              </Link>
            ))}
          </nav>
        </div>

        <div className="mt-10 border-t border-stone-800 pt-8 text-xs leading-relaxed text-stone-500">
          <p>
            © 2026 Aditya. TradeSheet Studio is licensed under GPL-2.0-or-later.
          </p>
          <p className="mt-2">
            TradeSheet Studio is an independent product, not affiliated with,
            endorsed by, or sponsored by WordPress or WooCommerce.
          </p>
          <p className="mt-2">
            Volt &amp; Wire Trading Co., Krishna Electricals and all demo
            figures on this page are synthetic demonstration data.
          </p>
        </div>
      </div>
    </footer>
  )
}
